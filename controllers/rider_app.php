<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
if (!empty($_SESSION['admin_id']) && (!isset($_SESSION['login_source']) || $_SESSION['login_source'] === 'admin')) {
    $_SESSION['login_source'] = 'admin';
    $_SESSION['rider_id'] = (int) $_SESSION['admin_id'];
    $_SESSION['rider_name'] = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
    $_SESSION['rider_email'] = $_SESSION['admin_email'] ?? '';
}
header('Content-Type: application/json; charset=utf-8');
$modelsPath = __DIR__ . '/../models';
set_include_path($modelsPath . PATH_SEPARATOR . get_include_path());
require_once $modelsPath . '/functions.php';
require_once $modelsPath . '/franchise_dates.php';
require_once $modelsPath . '/notifications.php';
require_once $modelsPath . '/notification_triggers.php';
require_once $modelsPath . '/upload.php';

function respond($payload, $status = 200) { http_response_code($status); echo json_encode($payload); exit; }
function requestData() { $data = json_decode(file_get_contents('php://input'), true); return is_array($data) ? $data : $_POST; }
function ensureOwnershipTables() { global $conn; mysqli_query($conn, 'CREATE TABLE IF NOT EXISTS franchise_driver (assignment_id INT NOT NULL AUTO_INCREMENT, franchise_id INT NOT NULL, driver_id INT NOT NULL, PRIMARY KEY (assignment_id), UNIQUE KEY franchise_driver (franchise_id, driver_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'); }
function ensureFranchiseDocumentsTable() {
    global $conn;
    $sql = 'CREATE TABLE IF NOT EXISTS franchise_documents (document_id INT NOT NULL AUTO_INCREMENT, franchise_id INT NOT NULL, receipt_photo VARCHAR(255) DEFAULT NULL, cedula_photo VARCHAR(255) DEFAULT NULL, drivers_license VARCHAR(100) DEFAULT NULL, drivers_license_photo VARCHAR(255) DEFAULT NULL, drivers_license_back_photo VARCHAR(255) DEFAULT NULL, PRIMARY KEY (document_id), UNIQUE KEY franchise_id (franchise_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    if (!mysqli_query($conn, $sql)) throw new RuntimeException('Unable to prepare franchise document storage: ' . mysqli_error($conn));
    foreach (['cedula_photo' => 'VARCHAR(255) NULL', 'drivers_license' => 'VARCHAR(100) NULL', 'drivers_license_photo' => 'VARCHAR(255) NULL', 'drivers_license_back_photo' => 'VARCHAR(255) NULL'] as $column => $definition) {
        $result = mysqli_query($conn, "SHOW COLUMNS FROM franchise_documents LIKE '$column'");
        if (!$result) throw new RuntimeException('Unable to inspect franchise document storage: ' . mysqli_error($conn));
        $columnExists = mysqli_num_rows($result) > 0;
        mysqli_free_result($result);
        if (!$columnExists && !mysqli_query($conn, "ALTER TABLE franchise_documents ADD COLUMN $column $definition")) {
            throw new RuntimeException('Unable to prepare franchise document storage: ' . mysqli_error($conn));
        }
    }
}
function ensureApplicationsTable() {
    global $conn;
    $sql = "CREATE TABLE IF NOT EXISTS franchise_applications (application_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, rider_id INT NULL, rider_name VARCHAR(150) NOT NULL, rider_email VARCHAR(255) NOT NULL, franchise_id INT NULL, franchise_name VARCHAR(150) NULL, application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending', admin_comments TEXT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, address TEXT NULL, issue_date DATE NULL, expiry_date DATE NULL, receipt_photo VARCHAR(255) NULL, cedula_document VARCHAR(255) NULL, drivers_license VARCHAR(100) NULL, drivers_license_photo VARCHAR(255) NULL, drivers_license_back_photo VARCHAR(255) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!mysqli_query($conn, $sql)) throw new RuntimeException('Unable to prepare franchise application storage: ' . mysqli_error($conn));
    foreach (['cedula_document' => 'VARCHAR(255) NULL', 'drivers_license' => 'VARCHAR(100) NULL', 'drivers_license_photo' => 'VARCHAR(255) NULL', 'drivers_license_back_photo' => 'VARCHAR(255) NULL'] as $column => $definition) {
        $result = mysqli_query($conn, "SHOW COLUMNS FROM franchise_applications LIKE '$column'");
        if (!$result) throw new RuntimeException('Unable to inspect franchise application document storage: ' . mysqli_error($conn));
        $columnExists = mysqli_num_rows($result) > 0;
        mysqli_free_result($result);
        if (!$columnExists && !mysqli_query($conn, "ALTER TABLE franchise_applications ADD COLUMN $column $definition")) {
            throw new RuntimeException('Unable to prepare franchise application document storage: ' . mysqli_error($conn));
        }
    }
}
function ensureTricycleStatusColumn() {
    global $conn;
    $check = mysqli_query($conn, "SHOW COLUMNS FROM tricycles LIKE 'status'");
    if ($check && mysqli_num_rows($check) === 0) {
        mysqli_query($conn, "ALTER TABLE tricycles ADD COLUMN status ENUM('Active','Inactive','Pending') NOT NULL DEFAULT 'Pending' AFTER plate_number");
    }
}
function ensureDriverStatusColumn() {
    global $conn;
    $check = mysqli_query($conn, "SHOW COLUMNS FROM drivers LIKE 'status'");
    if ($check && mysqli_num_rows($check) > 0) {
        mysqli_query($conn, "ALTER TABLE drivers MODIFY COLUMN status ENUM('Pending','For Review','Approved','Inactive') DEFAULT 'Pending'");
    }
}
function ensureDriverLicenseBackColumn() {
    global $conn;
    $check = mysqli_query($conn, "SHOW COLUMNS FROM drivers LIKE 'driver_license_back'");
    if (!$check) throw new RuntimeException('Unable to inspect driver license image storage: ' . mysqli_error($conn));
    $columnExists = mysqli_num_rows($check) > 0;
    mysqli_free_result($check);
    if (!$columnExists && !mysqli_query($conn, 'ALTER TABLE drivers ADD COLUMN driver_license_back VARCHAR(255) NULL AFTER driver_license')) {
        throw new RuntimeException('Unable to prepare driver license image storage: ' . mysqli_error($conn));
    }
}
function ensureTricycleDocumentColumns() {
    global $conn;
    foreach (['or_document', 'cr_document', 'or_number'] as $column) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM tricycles LIKE '$column'");
        if (!$check) throw new Exception('Unable to inspect tricycle document fields: ' . mysqli_error($conn));
        $columnExists = mysqli_num_rows($check) > 0;
        mysqli_free_result($check);
        if (!$columnExists && !mysqli_query($conn, "ALTER TABLE tricycles ADD COLUMN $column VARCHAR(255) NULL")) {
            throw new Exception('Unable to prepare tricycle document storage: ' . mysqli_error($conn));
        }
    }
}
function ensureTricycleImageColumns() {
    global $conn;
    foreach (['tricycles', 'franchise_owner_tricycle'] as $table) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'vehicle_image'");
        if (!$check) throw new RuntimeException('Unable to inspect vehicle image storage: ' . mysqli_error($conn));
        $columnExists = mysqli_num_rows($check) > 0;
        mysqli_free_result($check);
        if (!$columnExists && !mysqli_query($conn, "ALTER TABLE `$table` ADD COLUMN vehicle_image VARCHAR(255) NULL")) {
            throw new RuntimeException('Unable to prepare vehicle image storage: ' . mysqli_error($conn));
        }
    }
}
function ensureFranchiseOwnerTricycleTable() {
    global $conn;
    $sql = "CREATE TABLE IF NOT EXISTS franchise_owner_tricycle (
        owner_tricycle_id INT NOT NULL AUTO_INCREMENT,
        franchise_id INT NOT NULL,
        brand VARCHAR(100) NOT NULL,
        engine_number VARCHAR(100) NOT NULL,
        chassis_number VARCHAR(100) NOT NULL,
        color VARCHAR(50) NOT NULL,
        plate_number VARCHAR(30) NOT NULL,
        sticker_number VARCHAR(50) NOT NULL,
        or_number VARCHAR(255) DEFAULT NULL,
        or_cr_document VARCHAR(255) DEFAULT NULL,
        vehicle_image VARCHAR(255) DEFAULT NULL,
        status ENUM('Pending','Active','Inactive','Archived') NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (owner_tricycle_id),
        UNIQUE KEY uq_franchise_owner_tricycle_plate (plate_number),
        UNIQUE KEY uq_franchise_owner_tricycle_engine (engine_number),
        UNIQUE KEY uq_franchise_owner_tricycle_chassis (chassis_number),
        UNIQUE KEY uq_franchise_owner_tricycle_sticker (sticker_number),
        KEY idx_franchise_owner_tricycle_franchise (franchise_id),
        CONSTRAINT fk_franchise_owner_tricycle_franchise
            FOREIGN KEY (franchise_id) REFERENCES franchises (franchise_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!mysqli_query($conn, $sql)) {
        throw new RuntimeException('Unable to prepare owner tricycle storage: ' . mysqli_error($conn));
    }
    $imageColumn = mysqli_query($conn, "SHOW COLUMNS FROM franchise_owner_tricycle LIKE 'vehicle_image'");
    if (!$imageColumn) throw new RuntimeException('Unable to inspect owner tricycle image storage: ' . mysqli_error($conn));
    $hasImageColumn = mysqli_num_rows($imageColumn) > 0;
    mysqli_free_result($imageColumn);
    if (!$hasImageColumn && !mysqli_query($conn, 'ALTER TABLE franchise_owner_tricycle ADD COLUMN vehicle_image VARCHAR(255) NULL')) {
        throw new RuntimeException('Unable to prepare owner tricycle image storage: ' . mysqli_error($conn));
    }

    $statusColumn = mysqli_query($conn, "SHOW COLUMNS FROM franchise_owner_tricycle LIKE 'status'");
    if (!$statusColumn) throw new RuntimeException('Unable to inspect owner tricycle status: ' . mysqli_error($conn));
    $statusDefinition = mysqli_fetch_assoc($statusColumn);
    mysqli_free_result($statusColumn);
    if (!$statusDefinition
        || stripos($statusDefinition['Type'], "'Pending'") === false
        || stripos($statusDefinition['Type'], "'Active'") === false
        || stripos($statusDefinition['Type'], "'Inactive'") === false
        || stripos($statusDefinition['Type'], "'Archived'") === false) {
        if (!mysqli_query($conn, "ALTER TABLE franchise_owner_tricycle MODIFY status ENUM('Pending','Active','Inactive','Archived') NOT NULL DEFAULT 'Pending'")) {
            throw new RuntimeException('Unable to enable owner tricycle approval status: ' . mysqli_error($conn));
        }
    }
    $documentColumn = mysqli_query($conn, "SHOW COLUMNS FROM franchise_owner_tricycle LIKE 'or_cr_document'");
    if (!$documentColumn) throw new RuntimeException('Unable to inspect owner tricycle OR/CR proof storage: ' . mysqli_error($conn));
    $hasDocumentColumn = mysqli_num_rows($documentColumn) > 0;
    mysqli_free_result($documentColumn);
    if (!$hasDocumentColumn && !mysqli_query($conn, "ALTER TABLE franchise_owner_tricycle ADD COLUMN or_cr_document VARCHAR(255) DEFAULT NULL AFTER or_number")) {
        throw new RuntimeException('Unable to prepare owner tricycle OR/CR proof storage: ' . mysqli_error($conn));
    }
}
function normalizeDriverStatus($status) {
    $status = trim((string) ($status ?? 'Pending'));
    if ($status === 'Approved') return 'Active';
    if ($status === 'For Review') return 'Pending';
    return in_array($status, ['Active', 'Inactive', 'Pending'], true) ? $status : 'Pending';
}
function normalizeTricycleStatus($status) {
    $status = trim((string) ($status ?? 'Pending'));
    if ($status === 'Approved') return 'Active';
    return in_array($status, ['Active', 'Inactive', 'Pending'], true) ? $status : 'Pending';
}
function currentAccountEmail() {
    $loginSource = $_SESSION['login_source'] ?? 'rider';
    if ($loginSource === 'admin') return trim($_SESSION['admin_email'] ?? '');
    return trim($_SESSION['rider_email'] ?? '');
}
function currentAccountName() {
    $loginSource = $_SESSION['login_source'] ?? 'rider';
    if ($loginSource === 'admin') return trim($_SESSION['admin_name'] ?? 'Admin');
    return trim($_SESSION['rider_name'] ?? 'Rider');
}
function ensureProfileMiddleNameColumn($table, $afterColumn) {
    global $conn;
    if (!in_array($table, ['admins', 'riders'], true)) throw new InvalidArgumentException('Invalid profile table.');
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'middle_name'");
    if (!$result) throw new RuntimeException('Unable to inspect profile middle-name storage: ' . mysqli_error($conn));
    $columnExists = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);
    if (!$columnExists && !mysqli_query($conn, "ALTER TABLE `$table` ADD COLUMN middle_name varchar(100) DEFAULT NULL AFTER `$afterColumn`")) {
        throw new RuntimeException('Unable to prepare profile middle-name storage: ' . mysqli_error($conn));
    }
}
function ownedFranchise() {
    $email = currentAccountEmail();
    if (!$email) return null;
    return getRecord('franchises', 'owner_email = ? ORDER BY franchise_id DESC LIMIT 1', [$email]);
}
function pendingApplication() { ensureApplicationsTable(); return getRecord('franchise_applications', 'rider_id = ? AND status = ? ORDER BY application_id DESC LIMIT 1', [(int) $_SESSION['rider_id'], 'Pending']); }
function currentNotifications() {
    $email = currentAccountEmail();
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) return [];
    return getNotificationsForEmail($email, 50);
}
function getRenewalsForCurrentRider() {
    $franchise = ownedFranchise();
    if (!$franchise) return [];
    $rows = getAllRecords('renewals', 'WHERE franchise_id = ? ORDER BY renewal_id DESC', [(int) $franchise['franchise_id']]);
    return array_map(function ($row) {
        return [
            'id' => (int) $row['renewal_id'],
            'year' => (int) ($row['renewal_year'] ?? date('Y')),
            'status' => $row['receipt_status'] ?? 'Submitted',
            'submittedAt' => $row['receipt_submitted_at'] ?? $row['created_at'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
            'confirmedAt' => $row['receipt_confirmed_at'] ?? null,
            'confirmedBy' => $row['receipt_confirmed_by'] ?? null,
            'nextRenewalDate' => $row['due_date'] ?? null,
            'receiptPhoto' => $row['receipt_photo'] ?? null
        ];
    }, $rows);
}
function ensureRiderProfilePhotoColumn() {
    global $conn;
    $table = ($_SESSION['login_source'] ?? 'rider') === 'admin' ? 'admins' : 'riders';
    $result = mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE 'profile_photo'");
    if (!$result) throw new RuntimeException('Unable to inspect profile photo storage.');
    $exists = mysqli_num_rows($result) > 0;
    mysqli_free_result($result);
    if (!$exists && !mysqli_query($conn, "ALTER TABLE $table ADD COLUMN profile_photo VARCHAR(255) NULL")) {
        throw new RuntimeException('Unable to prepare profile photo storage.');
    }
}
function updateRiderProfilePhoto($data) {
    $isAdmin = ($_SESSION['login_source'] ?? 'rider') === 'admin';
    $table = $isAdmin ? 'admins' : 'riders';
    $idColumn = $isAdmin ? 'admin_id' : 'rider_id';
    ensureRiderProfilePhotoColumn();
    $id = (int) ($isAdmin ? $_SESSION['admin_id'] : $_SESSION['rider_id']);
    $rider = getRecord($table, $idColumn . ' = ? AND status = ?', [$id, 'Active']);
    if (!$rider) respond(['success' => false, 'message' => 'Rider account not found.'], 404);
    $photo = $data['photoDataUrl'] ?? '';
    if (!is_string($photo) || $photo === '') throw new InvalidArgumentException('Choose a profile picture.');
    $path = saveImageDataUrlUpload($photo, 'rider_profile');
    try {
        if (!updateRecord($table, ['profile_photo' => $path], $idColumn . ' = ?', [$id])) throw new RuntimeException('Unable to save profile picture.');
    } catch (Throwable $error) {
        @unlink(__DIR__ . '/../' . $path);
        throw $error;
    }
    $oldPath = $rider['profile_photo'] ?? '';
    if (preg_match('~^uploads/rider_profile_[a-f0-9]+\.(jpg|png|gif|webp)$~D', $oldPath)) @unlink(__DIR__ . '/../' . $oldPath);
    respond(['success' => true, 'photoUrl' => uploadUrl($path)]);
}

function listData() {
    ensureRiderProfilePhotoColumn();
    ensureOwnershipTables();
    ensureDriverStatusColumn();
    ensureDriverLicenseBackColumn();
    ensureTricycleStatusColumn();
    ensureTricycleDocumentColumns();
    ensureFranchiseOwnerTricycleTable();
    ensureTricycleImageColumns();
    ensureFranchiseDocumentsTable();
    markExpiredFranchises();
    try {
        checkAndTriggerRenewalNotifications();
    } catch (Throwable $error) {
        error_log('Rider renewal notification check failed: ' . $error->getMessage());
    }
    $loginSource = $_SESSION['login_source'] ?? 'rider';
    ensureProfileMiddleNameColumn($loginSource === 'admin' ? 'admins' : 'riders', $loginSource === 'admin' ? 'first_name' : 'full_name');
    $franchise = ownedFranchise();
    $application = $franchise ? null : pendingApplication();
    $franchiseId = (int) ($franchise['franchise_id'] ?? 0);
    $franchiseDocuments = [];
    if ($franchise) {
        $documentRecord = getRecord('franchise_documents', 'franchise_id = ?', [$franchiseId]) ?: [];
        $franchiseDocuments = array_values(array_filter([
            !empty($documentRecord['receipt_photo']) ? ['name' => 'Application Receipt', 'url' => uploadUrl($documentRecord['receipt_photo'])] : null,
            !empty($documentRecord['cedula_photo']) ? ['name' => 'Cedula', 'url' => uploadUrl($documentRecord['cedula_photo'])] : null,
            !empty($documentRecord['drivers_license_photo']) ? ["name" => "Driver's License (Front)", 'url' => uploadUrl($documentRecord['drivers_license_photo'])] : null,
            !empty($documentRecord['drivers_license_back_photo']) ? ["name" => "Driver's License (Back)", 'url' => uploadUrl($documentRecord['drivers_license_back_photo'])] : null
        ]));
    } elseif ($application) {
        $franchiseDocuments = array_values(array_filter([
            !empty($application['receipt_photo']) ? ['name' => 'Application Receipt', 'url' => uploadUrl($application['receipt_photo'])] : null,
            !empty($application['cedula_document']) ? ['name' => 'Cedula', 'url' => uploadUrl($application['cedula_document'])] : null,
            !empty($application['drivers_license_photo']) ? ["name" => "Driver's License (Front)", 'url' => uploadUrl($application['drivers_license_photo'])] : null,
            !empty($application['drivers_license_back_photo']) ? ["name" => "Driver's License (Back)", 'url' => uploadUrl($application['drivers_license_back_photo'])] : null
        ]));
    }
    $assignments = $franchiseId ? getAllRecords('franchise_tricycle', 'WHERE franchise_id = ?', [$franchiseId]) : [];
    $tricycleIds = array_map(fn($row) => (int) $row['tricycle_id'], $assignments);
    $riderId = $loginSource === 'rider' ? (int) ($_SESSION['rider_id'] ?? 0) : 0;
    if ($riderId && $tricycleIds) {
        $tricycleQuery = 'WHERE rider_id = ? OR tricycle_id IN (' . implode(',', $tricycleIds) . ') ORDER BY tricycle_id DESC';
        $tricycles = getAllRecords('tricycles', $tricycleQuery, [$riderId]);
    } elseif ($riderId) {
        $tricycles = getAllRecords('tricycles', 'WHERE rider_id = ? ORDER BY tricycle_id DESC', [$riderId]);
    } else {
        $tricycles = $tricycleIds ? getAllRecords('tricycles', 'WHERE tricycle_id IN (' . implode(',', $tricycleIds) . ') ORDER BY tricycle_id DESC') : [];
    }
    $tricycleById = [];
    foreach ($tricycles as $tricycle) $tricycleById[(int) $tricycle['tricycle_id']] = $tricycle;
    $franchiseDrivers = $franchiseId ? getAllRecords('franchise_driver', 'WHERE franchise_id = ?', [$franchiseId]) : [];
    $driverIds = array_map(fn($row) => (int) $row['driver_id'], $franchiseDrivers);
    $drivers = $driverIds ? getAllRecords('drivers', 'WHERE driver_id IN (' . implode(',', $driverIds) . ') ORDER BY driver_id DESC') : [];
    $driverTricycleAssignments = getAllRecords('driver_tricycle');
    $assignedTricycleByDriver = [];
    foreach ($driverTricycleAssignments as $assignment) $assignedTricycleByDriver[(int) $assignment['driver_id']] = (int) $assignment['tricycle_id'];
    $ownerTricycles = $franchiseId
        ? getAllRecords('franchise_owner_tricycle', 'WHERE franchise_id = ? AND status <> ? ORDER BY owner_tricycle_id DESC', [$franchiseId, 'Archived'])
        : [];
    $ownerTricycleRows = array_map(fn($tricycle) => [
        'id' => (int) $tricycle['owner_tricycle_id'],
        'brand' => $tricycle['brand'],
        'unit' => $tricycle['sticker_number'],
        'plate' => $tricycle['plate_number'],
        'engine' => $tricycle['engine_number'],
        'chassis' => $tricycle['chassis_number'],
        'color' => $tricycle['color'],
        'orNumber' => $tricycle['or_number'] ?? '',
        'image' => !empty($tricycle['vehicle_image']) ? uploadUrl($tricycle['vehicle_image']) : '',
        'orCrDocument' => !empty($tricycle['or_cr_document']) ? uploadUrl($tricycle['or_cr_document']) : '',
        'docs' => !empty($tricycle['or_cr_document'])
            ? [['name' => 'OR/CR Proof', 'url' => uploadUrl($tricycle['or_cr_document'])]]
            : [],
        'status' => $tricycle['status']
    ], $ownerTricycles);

    if ($loginSource === 'admin') {
        $admin = getRecord('admins', 'admin_id = ?', [(int) ($_SESSION['admin_id'] ?? 0)]);
        $baseName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));
        $middleName = trim((string) ($admin['middle_name'] ?? ''));
        $profile = [
            'id' => (int) ($_SESSION['admin_id'] ?? 0),
            'name' => trim(($admin['first_name'] ?? '') . ' ' . $middleName . ' ' . ($admin['last_name'] ?? '')),
            'baseName' => $baseName ?: currentAccountName(),
            'middleName' => $middleName,
            'role' => $_SESSION['admin_role'] ?? 'Admin',
            'photoUrl' => uploadUrl($admin['profile_photo'] ?? ''),
            'email' => currentAccountEmail(),
            'contact' => '',
            'franchise' => $franchise['franchise_name'] ?? 'Not assigned'
        ];
        $franchisePayload = $franchise
            ? ['id' => $franchiseId, 'name' => $franchise['franchise_name'], 'owner' => $franchise['owner_name'], 'status' => $franchise['renewal_status'], 'expiry' => $franchise['expiry_date'], 'registered' => true, 'documents' => $franchiseDocuments]
            : ['id' => '', 'name' => $application['franchise_name'] ?? '', 'owner' => $profile['name'], 'status' => $application['status'] ?? '', 'registered' => false, 'hasApplication' => (bool) $application, 'documents' => $franchiseDocuments];
        $driverRows = array_map(function ($driver) use ($assignedTricycleByDriver, $tricycleById, $franchiseId) {
            $tricycleId = $assignedTricycleByDriver[(int) $driver['driver_id']] ?? null;
            $tricycle = $tricycleId ? ($tricycleById[$tricycleId] ?? null) : null;
            return ['id' => (int) $driver['driver_id'], 'franchiseId' => $franchiseId, 'name' => $driver['full_name'], 'license' => $driver['driver_license'] ?: 'Not provided', 'tricycleId' => $tricycleId, 'tricycle' => $tricycle ? (($tricycle['brand'] ?: 'Tricycle') . ' - ' . ($tricycle['sticker_number'] ?: $tricycle['plate_number'])) : 'Unassigned', 'status' => normalizeDriverStatus($driver['status'] ?? 'Pending'), 'dob' => 'Age ' . ($driver['age'] ?: '-'), 'gender' => $driver['gender'] ?? '', 'address' => $driver['address'] ?? '-', 'contact' => $driver['contact_number'] ?? '-', 'licenseExp' => 'Not recorded',             'docs' => array_values(array_filter([!empty($driver['driver_license']) ? ['name' => "Driver's License (Front)", 'url' => uploadUrl($driver['driver_license'])] : null, !empty($driver['driver_license_back']) ? ['name' => "Driver's License (Back)", 'url' => uploadUrl($driver['driver_license_back'])] : null, !empty($driver['or_cr']) ? ['name' => 'OR/CR', 'url' => uploadUrl($driver['or_cr'])] : null, !empty($driver['president_certificate']) ? ['name' => "President's Certificate", 'url' => uploadUrl($driver['president_certificate'])] : null]))];
        }, $drivers);
        foreach ($driverRows as &$driverRow) {
            foreach ($drivers as $driver) {
                if ((int) $driver['driver_id'] === (int) $driverRow['id']) {
                    $driverRow['licenseNumber'] = $driver['driver_license_number'] ?? '';
                    $driverRow['orCrNumber'] = $driver['or_cr_number'] ?? '';
                    break;
                }
            }
        }
        unset($driverRow);
        $tricycleRows = array_map(fn($tricycle) => ['id' => (int) $tricycle['tricycle_id'], 'franchiseId' => $franchiseId, 'unit' => $tricycle['sticker_number'] ?: 'Not specified', 'brand' => $tricycle['brand'] ?: 'Not specified', 'plate' => $tricycle['plate_number'] ?: 'No plate', 'orNumber' => $tricycle['or_number'] ?? '', 'image' => !empty($tricycle['vehicle_image']) ? uploadUrl($tricycle['vehicle_image']) : '', 'driver' => 'Unassigned', 'status' => normalizeTricycleStatus($tricycle['status'] ?? 'Pending'), 'engine' => $tricycle['engine_number'], 'chassis' => $tricycle['chassis_number'], 'color' => $tricycle['color'] ?: 'Not specified', 'docs' => array_values(array_filter([!empty($tricycle['or_document']) ? ['name' => 'OR Document', 'url' => uploadUrl($tricycle['or_document'])] : null]))], $tricycles);
        respond(['success' => true, 'franchise' => $franchisePayload, 'drivers' => $driverRows, 'tricycles' => $tricycleRows, 'franchiseOwnerTricycles' => $ownerTricycleRows, 'notifications' => array_map(function ($row) { return ['id' => (int) $row['notification_id'], 'type' => $row['type'] ?? 'Franchise', 'severity' => $row['severity'] ?? 'info', 'title' => $row['title'] ?? '', 'message' => $row['message'] ?? '', 'isRead' => (bool) $row['is_read'], 'created_at' => str_replace(' ', 'T', $row['created_at'])]; }, currentNotifications()), 'renewals' => getRenewalsForCurrentRider(), 'profile' => $profile]);
    }

    $rider = getRecord('riders', 'rider_id = ? AND status = ?', [(int) $_SESSION['rider_id'], 'Active']);
    $driverRows = array_map(function ($driver) use ($assignedTricycleByDriver, $tricycleById, $franchiseId) {
        $tricycleId = $assignedTricycleByDriver[(int) $driver['driver_id']] ?? null;
        $tricycle = $tricycleId ? ($tricycleById[$tricycleId] ?? null) : null;
        return ['id' => (int) $driver['driver_id'], 'franchiseId' => $franchiseId, 'name' => $driver['full_name'], 'license' => $driver['driver_license'] ?: 'Not provided', 'tricycleId' => $tricycleId, 'tricycle' => $tricycle ? (($tricycle['brand'] ?: 'Tricycle') . ' - ' . ($tricycle['sticker_number'] ?: $tricycle['plate_number'])) : 'Unassigned', 'status' => normalizeDriverStatus($driver['status'] ?? 'Pending'), 'dob' => 'Age ' . ($driver['age'] ?: '-'), 'gender' => $driver['gender'] ?? '', 'address' => $driver['address'] ?? '-', 'contact' => $driver['contact_number'] ?? '-', 'licenseExp' => 'Not recorded', 'docs' => array_values(array_filter([!empty($driver['driver_license']) ? ['name' => "Driver's License (Front)", 'url' => uploadUrl($driver['driver_license'])] : null, !empty($driver['driver_license_back']) ? ['name' => "Driver's License (Back)", 'url' => uploadUrl($driver['driver_license_back'])] : null, !empty($driver['or_cr']) ? ['name' => 'OR/CR', 'url' => uploadUrl($driver['or_cr'])] : null, !empty($driver['president_certificate']) ? ['name' => "President's Certificate", 'url' => uploadUrl($driver['president_certificate'])] : null]))];
    }, $drivers);
    foreach ($driverRows as &$driverRow) {
        foreach ($drivers as $driver) {
            if ((int) $driver['driver_id'] === (int) $driverRow['id']) {
                $driverRow['licenseNumber'] = $driver['driver_license_number'] ?? '';
                $driverRow['orCrNumber'] = $driver['or_cr_number'] ?? '';
                break;
            }
        }
    }
    unset($driverRow);
    $tricycleRows = array_map(fn($tricycle) => ['id' => (int) $tricycle['tricycle_id'], 'franchiseId' => $franchiseId, 'unit' => $tricycle['sticker_number'] ?: 'Not specified', 'brand' => $tricycle['brand'] ?: 'Not specified', 'plate' => $tricycle['plate_number'] ?: 'No plate', 'orNumber' => $tricycle['or_number'] ?? '', 'image' => !empty($tricycle['vehicle_image']) ? uploadUrl($tricycle['vehicle_image']) : '', 'driver' => 'Unassigned', 'status' => normalizeTricycleStatus($tricycle['status'] ?? 'Pending'), 'engine' => $tricycle['engine_number'], 'chassis' => $tricycle['chassis_number'], 'color' => $tricycle['color'] ?: 'Not specified', 'docs' => array_values(array_filter([!empty($tricycle['or_document']) ? ['name' => 'OR Document', 'url' => uploadUrl($tricycle['or_document'])] : null]))], $tricycles);
    $profileFranchise = $franchise['franchise_name'] ?? ($application['franchise_name'] ?? 'Not registered');
    if (!$franchise && $application) $profileFranchise .= ' (application ' . strtolower($application['status']) . ')';
    respond(['success' => true, 'franchise' => $franchise ? ['id' => $franchiseId, 'name' => $franchise['franchise_name'], 'owner' => $franchise['owner_name'], 'status' => $franchise['renewal_status'], 'expiry' => $franchise['expiry_date'], 'registered' => true, 'documents' => $franchiseDocuments] : ['id' => '', 'name' => $application['franchise_name'] ?? '', 'owner' => $_SESSION['rider_name'], 'status' => $application['status'] ?? '', 'registered' => false, 'hasApplication' => (bool) $application, 'documents' => $franchiseDocuments], 'drivers' => $driverRows, 'tricycles' => $tricycleRows, 'franchiseOwnerTricycles' => $ownerTricycleRows, 'notifications' => array_map(function ($row) { return ['id' => (int) $row['notification_id'], 'type' => $row['type'] ?? 'Franchise', 'severity' => $row['severity'] ?? 'info', 'title' => $row['title'] ?? '', 'message' => $row['message'] ?? '', 'isRead' => (bool) $row['is_read'], 'created_at' => str_replace(' ', 'T', $row['created_at'])]; }, currentNotifications()), 'renewals' => getRenewalsForCurrentRider(), 'profile' => ['id' => (int) $rider['rider_id'], 'name' => $rider['full_name'], 'baseName' => $rider['full_name'], 'middleName' => trim((string) ($rider['middle_name'] ?? '')), 'role' => 'Rider', 'photoUrl' => uploadUrl($rider['profile_photo'] ?? ''), 'email' => $rider['email'], 'contact' => $rider['contact_number'] ?? '', 'franchise' => $profileFranchise]]);
}
function createFranchiseApplication($data) {
    ensureApplicationsTable();
    if (ownedFranchise() || pendingApplication()) respond(['success' => false, 'message' => 'You already have a franchise or an application awaiting review.'], 422);
    $name = trim($data['name'] ?? '');
    if ($name === '') respond(['success' => false, 'message' => 'Franchise name is required.'], 422);
    $driversLicense = trim((string) ($data['driversLicense'] ?? ''));
    if ($driversLicense === '' || strlen($driversLicense) > 100) {
        respond(['success' => false, 'message' => 'A valid driver’s license number is required.'], 422);
    }
    $cedulaDataUrl = $data['cedulaDataUrl'] ?? '';
    $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $validatedImages = [];
    foreach (['Cedula' => $cedulaDataUrl, "Driver's license front" => ($data['driversLicensePhotoDataUrl'] ?? ''), "Driver's license back" => ($data['driversLicenseBackPhotoDataUrl'] ?? '')] as $label => $imageDataUrl) {
        if (!is_string($imageDataUrl) || !preg_match('/^data:image\/(jpeg|png|gif|webp);base64,([A-Za-z0-9+\/=]+)$/', $imageDataUrl, $matches)) {
            respond(['success' => false, 'message' => "$label image is required. Upload a JPEG, PNG, GIF, or WebP image."], 422);
        }
        $contents = base64_decode($matches[2], true);
        if ($contents === false || strlen($contents) > 5 * 1024 * 1024) {
            respond(['success' => false, 'message' => "$label image must be valid and no larger than 5 MB."], 422);
        }
        $imageInfo = getimagesizefromstring($contents);
        if (!$imageInfo || !in_array($imageInfo['mime'] ?? '', $allowedImageTypes, true) || $imageInfo['mime'] !== 'image/' . $matches[1]) {
            respond(['success' => false, 'message' => "$label must be a valid JPEG, PNG, GIF, or WebP image."], 422);
        }
        $validatedImages[$label] = $imageDataUrl;
    }
    $issue = date('Y-m-d');
    $expiry = franchiseAnniversary($issue);
    $receipt = saveDataUrlUpload($data['receiptDataUrl'] ?? '', 'franchise_application_receipt');
    $cedula = saveDataUrlUpload($validatedImages['Cedula'], 'franchise_application_cedula');
    $licensePhoto = saveDataUrlUpload($validatedImages["Driver's license front"], 'franchise_application_drivers_license_front');
    $licenseBackPhoto = saveDataUrlUpload($validatedImages["Driver's license back"], 'franchise_application_drivers_license_back');
    $id = insertSomething('franchise_applications', ['rider_id' => (int) $_SESSION['rider_id'], 'rider_name' => $_SESSION['rider_name'], 'rider_email' => $_SESSION['rider_email'], 'franchise_name' => $name, 'address' => trim($data['address'] ?? ''), 'issue_date' => $issue, 'expiry_date' => $expiry, 'receipt_photo' => $receipt, 'cedula_document' => $cedula, 'drivers_license' => $driversLicense, 'drivers_license_photo' => $licensePhoto, 'drivers_license_back_photo' => $licenseBackPhoto, 'status' => 'Pending']);
    notifySuperAdminsOfSubmission('Franchise', 'New Franchise Application', $_SESSION['rider_name'] . " submitted a franchise application for '$name'.", $id);
    respond(['success' => true, 'id' => $id], 201);
}
function applyRenewal($data) {
    $franchise = ownedFranchise();
    if (!$franchise) respond(['success' => false, 'message' => 'You need a registered franchise before applying for renewal.'], 422);

    $franchiseId = (int) ($franchise['franchise_id'] ?? 0);
    if ($franchiseId <= 0) respond(['success' => false, 'message' => 'Franchise not found.'], 404);

    $term = franchiseRenewalTerm($franchise);
    $year = $term['year'];
    $renewalOpeningDate = (new DateTimeImmutable($term['start']))->modify('-20 days');
    if (new DateTimeImmutable('today') < $renewalOpeningDate) {
        respond(['success' => false, 'message' => 'Renewal applications open on ' . $renewalOpeningDate->format('F j, Y') . '.'], 422);
    }
    $existingRenewal = getRecord('renewals', 'franchise_id = ? AND renewal_year = ? ORDER BY renewal_id DESC LIMIT 1', [$franchiseId, $year]);
    if ($existingRenewal) {
        respond(['success' => false, 'message' => "A renewal application for {$year} has already been submitted. Please wait for the LGU to process it."], 422);
    }
    $receipt = saveDataUrlUpload($data['receiptDataUrl'] ?? '', 'renewal_receipt');
    if (!$receipt) respond(['success' => false, 'message' => 'Please upload a valid receipt photo.'], 422);

    $renewalDate = $term['start'];
    $dueDate = $term['end'];

    $renewalId = insertSomething('renewals', [
        'franchise_id' => $franchiseId,
        'renewal_year' => $year,
        'renewal_date' => $renewalDate,
        'due_date' => $dueDate,
        'penalty' => 0,
        'remarks' => '',
        'receipt_photo' => $receipt,
        'receipt_submitted_at' => date('Y-m-d H:i:s'),
        'receipt_status' => 'Submitted',
        'receipt_confirmed_at' => null,
        'receipt_confirmed_by' => null
    ]);

    $riderEmail = trim($_SESSION['rider_email'] ?? '');
    $renewalMessage = "Your renewal application for {$franchise['franchise_name']} has been submitted successfully. Our admin team is reviewing the payment receipt and will update you once the verification is complete.";
    if ($riderEmail && filter_var($riderEmail, FILTER_VALIDATE_EMAIL)) {
        createNotification(
            'Renewal Submitted Successfully',
            $renewalMessage,
            'Renewal',
            'info',
            $riderEmail,
            $renewalId,
            'renewal_submission'
        );
    }

    notifySuperAdminsOfSubmission('Renewal', 'New Renewal Application', $_SESSION['rider_name'] . " submitted a renewal receipt for franchise '{$franchise['franchise_name']}'.", $renewalId);
    respond(['success' => true, 'id' => $renewalId], 201);
}
function createDriver($data) {
    $franchise = ownedFranchise();
    if (!$franchise) respond(['success' => false, 'message' => 'Wait for franchise approval before adding drivers.'], 422);
    $name = trim($data['name'] ?? '');
    $dob = trim((string) ($data['dob'] ?? ''));
    $gender = trim((string) ($data['gender'] ?? ''));
    $birthDate = $dob !== '' ? DateTime::createFromFormat('!Y-m-d', $dob) : false;
    $dateErrors = DateTime::getLastErrors();
    $hasDateErrors = is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0);
    $licenseNumber = trim((string) ($data['driver_license_number'] ?? ''));
    $orCrNumber = trim((string) ($data['or_cr_number'] ?? ''));
    if ($name === '' || $birthDate === false || $hasDateErrors || !in_array($gender, ['Male', 'Female'], true) || $licenseNumber === '' || $orCrNumber === '') respond(['success' => false, 'message' => 'Please enter valid driver information, including license and OR/CR numbers.'], 422);
    $age = $birthDate->diff(new DateTime('today'))->y;
    if ($age < 18 || $age > 80) respond(['success' => false, 'message' => 'The driver must be between 18 and 80 years old.'], 422);
    ensureOwnershipTables();
    $tricycleId = filter_var($data['tricycle_id'] ?? null, FILTER_VALIDATE_INT);
    if ($tricycleId !== false && $tricycleId !== null) validateActiveTricycle($tricycleId, $franchise['franchise_id']);
    ensureDriverStatusColumn();
    ensureDriverLicenseBackColumn();
    $licenseBackData = $data['licenseBackData'] ?? '';
    $licenseBack = saveImageDataUrlUpload($licenseBackData, 'driver_license_back');
    if (!$licenseBack) respond(['success' => false, 'message' => "The driver's license back image is required."], 422);
    $id = insertSomething('drivers', ['full_name' => trim($data['name']), 'contact_number' => trim($data['contact'] ?? ''), 'age' => $age, 'gender' => $gender, 'driver_license_number' => $licenseNumber, 'or_cr_number' => $orCrNumber, 'address' => trim($data['address'] ?? ''), 'driver_license' => saveDataUrlUpload($data['licenseData'] ?? '', 'driver_license') ?: 'Not provided', 'driver_license_back' => $licenseBack, 'or_cr' => saveDataUrlUpload($data['orcrData'] ?? '', 'or_cr'), 'president_certificate' => saveDataUrlUpload($data['presidentsData'] ?? '', 'president_certificate'), 'status' => 'Pending']);
    insertSomething('franchise_driver', ['franchise_id' => $franchise['franchise_id'], 'driver_id' => $id]);
    if ($tricycleId !== false && $tricycleId !== null) {
        deleteRecord('driver_tricycle', 'tricycle_id = ?', [$tricycleId]);
        insertSomething('driver_tricycle', ['driver_id' => $id, 'tricycle_id' => $tricycleId]);
    }
    notifySuperAdminsOfSubmission('Driver', 'New Driver Registration', $_SESSION['rider_name'] . " submitted driver registration for '$name'.", $id);
    respond(['success' => true, 'id' => $id], 201);
}

function validateActiveTricycle($tricycleId, $franchiseId) {
    $tricycle = getRecord('tricycles', 'tricycle_id = ? AND status = ?', [(int) $tricycleId, 'Active']);
    $assignment = getRecord('franchise_tricycle', 'tricycle_id = ? AND franchise_id = ?', [(int) $tricycleId, $franchiseId]);
    if (!$tricycle || !$assignment) respond(['success' => false, 'message' => 'Only an active tricycle from your franchise can be assigned.'], 422);
}
function validateUniqueTricycleFields($plate, $engine, $chassis, $sticker, $excludeId = null) {
    $fields = [
        'plate_number' => $plate,
        'engine_number' => $engine,
        'chassis_number' => $chassis,
        'sticker_number' => $sticker
    ];
    foreach ($fields as $column => $value) {
        $condition = "$column = ?";
        $params = [$value];
        if ($excludeId) {
            $condition .= ' AND tricycle_id <> ?';
            $params[] = $excludeId;
        }
        if (getRecord('tricycles', $condition, $params)) {
            $labels = [
                'plate_number' => 'plate number',
                'engine_number' => 'engine number',
                'chassis_number' => 'chassis number',
                'sticker_number' => 'body number/sticker'
            ];
            respond(['success' => false, 'message' => 'That ' . $labels[$column] . ' is already registered. Please enter a unique value.'], 409);
        }
    }
}
function saveVehicleImage($imageData, $category, $existingPath = null) {
    if ($imageData === '' || $imageData === null) return $existingPath;
    if (!is_string($imageData) || !preg_match('/^data:image\/(jpeg|png|gif|webp);base64,([A-Za-z0-9+\/=]+)$/D', $imageData, $matches)) {
        respond(['success' => false, 'message' => 'Vehicle image must be a JPEG, PNG, GIF, or WebP image.'], 422);
    }
    $contents = base64_decode($matches[2], true);
    if ($contents === false || strlen($contents) > 5 * 1024 * 1024) {
        respond(['success' => false, 'message' => 'Vehicle image must be valid and no larger than 5 MB.'], 422);
    }
    return saveDataUrlUpload($imageData, $category, $existingPath);
}
function updateDriver($data) {
    $franchise = ownedFranchise();
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$franchise || !$id) respond(['success' => false, 'message' => 'Driver submission not found.'], 404);
    $assignment = getRecord('franchise_driver', 'franchise_id = ? AND driver_id = ?', [$franchise['franchise_id'], $id]);
    $existing = getRecord('drivers', 'driver_id = ?', [$id]);
    if (!$assignment || !$existing) respond(['success' => false, 'message' => 'Driver submission not found.'], 404);
    ensureDriverLicenseBackColumn();

    $name = trim($data['name'] ?? '');
    $contact = trim($data['contact'] ?? '');
    $gender = trim((string) ($data['gender'] ?? ''));
    $licenseNumber = trim((string) ($data['driver_license_number'] ?? ''));
    $orCrNumber = trim((string) ($data['or_cr_number'] ?? ''));
    $address = trim($data['address'] ?? '');
    $birthDate = DateTime::createFromFormat('Y-m-d', $data['dob'] ?? '');
    $age = $birthDate ? $birthDate->diff(new DateTime('today'))->y : (int) ($existing['age'] ?? 0);
    if ($name === '' || $contact === '' || !in_array($gender, ['Male', 'Female'], true) || $licenseNumber === '' || $orCrNumber === '' || $age === null || $age < 18 || $age > 80) respond(['success' => false, 'message' => 'Please provide valid driver information, including license and OR/CR numbers.'], 422);

    $license = saveDataUrlUpload($data['licenseData'] ?? '', 'driver_license', $existing['driver_license'] ?? null);
    $licenseBack = saveImageDataUrlUpload($data['licenseBackData'] ?? '', 'driver_license_back', $existing['driver_license_back'] ?? null);
    $orCr = saveDataUrlUpload($data['orcrData'] ?? '', 'or_cr', $existing['or_cr'] ?? null);
    $certificate = saveDataUrlUpload($data['presidentsData'] ?? '', 'president_certificate', $existing['president_certificate'] ?? null);
    if (!$license) respond(['success' => false, 'message' => 'The driver license document is required.'], 422);
    $tricycleId = filter_var($data['tricycle_id'] ?? null, FILTER_VALIDATE_INT);
    if ($tricycleId !== false && $tricycleId !== null) validateActiveTricycle($tricycleId, $franchise['franchise_id']);
    updateRecord('drivers', ['full_name' => $name, 'contact_number' => $contact, 'age' => $age, 'gender' => $gender, 'address' => $address, 'driver_license_number' => $licenseNumber, 'or_cr_number' => $orCrNumber, 'driver_license' => $license, 'driver_license_back' => $licenseBack, 'or_cr' => $orCr, 'president_certificate' => $certificate], 'driver_id = ?', [$id]);
    deleteRecord('driver_tricycle', 'driver_id = ?', [$id]);
    if ($tricycleId !== false && $tricycleId !== null) {
        deleteRecord('driver_tricycle', 'tricycle_id = ?', [$tricycleId]);
        insertSomething('driver_tricycle', ['driver_id' => $id, 'tricycle_id' => $tricycleId]);
    }

    $ownerName = currentAccountName();
    $franchiseName = $franchise['franchise_name'] ?? 'the franchise';
    $adminEmail = getAdminEmailByFranchiseId($franchise['franchise_id']) ?: getAdminEmail();
    createNotification('Driver Details Updated', "$ownerName changed driver details for \"$name\" at \"$franchiseName\".", 'Driver', 'warning', $adminEmail, $id, 'driver_updated');
    respond(['success' => true]);
}
function assignDriver($data) {
    $franchise = ownedFranchise();
    $driverId = filter_var($data['driver_id'] ?? null, FILTER_VALIDATE_INT);
    $tricycleId = filter_var($data['tricycle_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$franchise || !$driverId || !$tricycleId) respond(['success' => false, 'message' => 'Driver and tricycle selection are required.'], 422);
    $driverAssignment = getRecord('franchise_driver', 'franchise_id = ? AND driver_id = ?', [$franchise['franchise_id'], $driverId]);
    $driver = getRecord('drivers', 'driver_id = ?', [$driverId]);
    if (!$driverAssignment || !$driver) respond(['success' => false, 'message' => 'Driver not found in your franchise.'], 404);
    if (getRecord('driver_tricycle', 'driver_id = ?', [$driverId])) respond(['success' => false, 'message' => 'This driver already has a tricycle assigned.'], 422);
    validateActiveTricycle($tricycleId, $franchise['franchise_id']);
    if (getRecord('driver_tricycle', 'tricycle_id = ?', [$tricycleId])) respond(['success' => false, 'message' => 'This tricycle is already assigned to another driver.'], 422);
    insertSomething('driver_tricycle', ['driver_id' => $driverId, 'tricycle_id' => $tricycleId]);
    respond(['success' => true]);
}
function createTricycle($data) {
    $franchise = ownedFranchise();
    if (!$franchise) respond(['success' => false, 'message' => 'Wait for franchise approval before adding tricycles.'], 422);
    $brand = trim($data['brand'] ?? '');
    $color = trim($data['color'] ?? '');
    $orNumber = trim($data['orNumber'] ?? $data['or_number'] ?? '');
    $driverId = filter_var($data['driver_id'] ?? null, FILTER_VALIDATE_INT);
    $sticker = trim($data['sticker'] ?? $data['sticker_number'] ?? $data['body_number'] ?? '');
    $plate = trim($data['plate'] ?? '');
    $engine = trim($data['engine'] ?? '');
    $chassis = trim($data['chassis'] ?? '');
    if ($brand === '' || $sticker === '' || $plate === '' || $engine === '' || $chassis === '' || $color === '') respond(['success' => false, 'message' => 'Brand, body number/sticker, plate, engine, chassis number, and color are required.'], 422);
    validateUniqueTricycleFields($plate, $engine, $chassis, $sticker);
    $orDocument = saveDataUrlUpload($data['orDocumentData'] ?? '', 'tricycle_or');
    if (!$orDocument) respond(['success' => false, 'message' => 'The OR document image is required.'], 422);
    $vehicleImage = saveVehicleImage($data['vehicleImageData'] ?? '', 'tricycle_image');
    if ($driverId !== false && $driverId !== null) {
        $driver = getRecord('drivers', 'driver_id = ? AND status = ?', [$driverId, 'Approved']);
        $driverAssignment = getRecord('franchise_driver', 'driver_id = ? AND franchise_id = ?', [$driverId, $franchise['franchise_id']]);
        if (!$driver || !$driverAssignment) respond(['success' => false, 'message' => 'Only an active driver from your franchise can be assigned.'], 422);
    }
    ensureTricycleStatusColumn();
    ensureTricycleDocumentColumns();
    ensureFranchiseOwnerTricycleTable();
    ensureTricycleImageColumns();
    $riderId = ($_SESSION['login_source'] ?? 'rider') === 'rider' ? (int) ($_SESSION['rider_id'] ?? 0) : null;
    $id = insertSomething('tricycles', ['rider_id' => $riderId ?: null, 'brand' => $brand, 'sticker_number' => $sticker, 'plate_number' => $plate, 'engine_number' => $engine, 'chassis_number' => $chassis, 'color' => $color, 'or_number' => $orNumber, 'or_document' => $orDocument, 'vehicle_image' => $vehicleImage, 'status' => 'Pending']);
    insertSomething('franchise_tricycle', ['franchise_id' => $franchise['franchise_id'], 'tricycle_id' => $id]);
    if ($driverId !== false && $driverId !== null) insertSomething('driver_tricycle', ['driver_id' => $driverId, 'tricycle_id' => $id]);
    notifySuperAdminsOfSubmission('Tricycle', 'New Tricycle Registration', $_SESSION['rider_name'] . " submitted tricycle registration for plate '" . trim($data['plate']) . "'.", $id);
    respond(['success' => true, 'id' => $id], 201);
}
function createFranchiseOwnerTricycle($data) {
    $franchise = ownedFranchise();
    if (!$franchise) {
        if (($_SESSION['login_source'] ?? 'rider') === 'admin') {
            respond(['success' => false, 'message' => 'This account is not registered as a franchise owner. Sign in with the account associated with the franchise owner email.'], 403);
        }
        respond(['success' => false, 'message' => 'Wait for your franchise registration to be approved before adding owner tricycles.'], 422);
    }
    ensureFranchiseOwnerTricycleTable();
    ensureTricycleImageColumns();

    $brand = trim(is_string($data['brand'] ?? null) ? $data['brand'] : '');
    $sticker = trim(is_string($data['sticker'] ?? null) ? $data['sticker'] : '');
    $plate = trim(is_string($data['plate'] ?? null) ? $data['plate'] : '');
    $engine = trim(is_string($data['engine'] ?? null) ? $data['engine'] : '');
    $chassis = trim(is_string($data['chassis'] ?? null) ? $data['chassis'] : '');
    $color = trim(is_string($data['color'] ?? null) ? $data['color'] : '');
    $orNumber = trim(is_string($data['orNumber'] ?? null) ? $data['orNumber'] : '');
    $orCrDocumentData = $data['orCrDocumentData'] ?? null;
    if ($brand === '' || $sticker === '' || $plate === '' || $engine === '' || $chassis === '' || $color === '') {
        respond(['success' => false, 'message' => 'Brand, body number/sticker, plate, engine, chassis number, and color are required.'], 422);
    }
    if (strlen($brand) > 100 || strlen($sticker) > 50 || strlen($plate) > 30 || strlen($engine) > 100 || strlen($chassis) > 100 || strlen($color) > 50 || strlen($orNumber) > 255) {
        respond(['success' => false, 'message' => 'One or more tricycle details exceed the allowed length.'], 422);
    }
    if (!is_string($orCrDocumentData) || !preg_match('/^data:image\/(jpeg|png|gif|webp);base64,/', $orCrDocumentData)) {
        respond(['success' => false, 'message' => 'An OR/CR proof image is required. Please upload a JPEG, PNG, GIF, or WebP image.'], 422);
    }

    foreach ([
        'plate_number' => $plate,
        'engine_number' => $engine,
        'chassis_number' => $chassis,
        'sticker_number' => $sticker
    ] as $column => $value) {
        if (getRecord('tricycles', "$column = ?", [$value]) || getRecord('franchise_owner_tricycle', "$column = ?", [$value])) {
            respond(['success' => false, 'message' => 'That vehicle identifier is already registered. Please check the plate, engine, chassis, and body number.'], 409);
        }
    }

    $orCrDocument = saveDataUrlUpload($orCrDocumentData, 'owner_tricycle_orcr');
    if (!$orCrDocument) respond(['success' => false, 'message' => 'Unable to save the OR/CR proof image. Please upload it again.'], 422);
    $vehicleImage = saveVehicleImage($data['vehicleImageData'] ?? '', 'owner_tricycle_image');
    $id = insertSomething('franchise_owner_tricycle', [
        'franchise_id' => (int) $franchise['franchise_id'],
        'brand' => $brand,
        'sticker_number' => $sticker,
        'plate_number' => $plate,
        'engine_number' => $engine,
        'chassis_number' => $chassis,
        'color' => $color,
        'or_number' => $orNumber !== '' ? $orNumber : null,
        'or_cr_document' => $orCrDocument,
        'vehicle_image' => $vehicleImage,
        'status' => 'Pending'
    ]);
    $ownerName = trim((string) ($franchise['owner_name'] ?? currentAccountName()));
    $franchiseName = trim((string) ($franchise['franchise_name'] ?? 'your franchise'));
    $ownerEmail = trim((string) ($franchise['owner_email'] ?? currentAccountEmail()));
    $submissionMessage = "$ownerName submitted owner tricycle plate $plate for $franchiseName. It is waiting for Super Admin review.";
    notifySuperAdminsOfSubmission('Tricycle', 'New Franchise-Owner Tricycle Submission', $submissionMessage, $id);
    if (filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
        createNotification(
            'Owner Tricycle Submitted',
            "Your tricycle ($plate) was submitted successfully and is pending Super Admin approval.",
            'Tricycle',
            'info',
            $ownerEmail,
            $id,
            'owner_tricycle_status'
        );
    }
    respond(['success' => true, 'id' => $id], 201);
}
function updateTricycle($data) {
    $franchise = ownedFranchise();
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$franchise || !$id) respond(['success' => false, 'message' => 'Tricycle submission not found.'], 404);
    ensureTricycleDocumentColumns();
    ensureFranchiseOwnerTricycleTable();
    ensureTricycleImageColumns();
    $assignment = getRecord('franchise_tricycle', 'franchise_id = ? AND tricycle_id = ?', [$franchise['franchise_id'], $id]);
    $existing = getRecord('tricycles', 'tricycle_id = ?', [$id]);
    if (!$assignment || !$existing) respond(['success' => false, 'message' => 'Tricycle submission not found.'], 404);

    $brand = trim($data['brand'] ?? '');
    $color = trim($data['color'] ?? '');
    $orNumber = trim($data['orNumber'] ?? $data['or_number'] ?? '');
    $sticker = trim($data['sticker'] ?? $data['sticker_number'] ?? $data['body_number'] ?? '');
    $plate = trim($data['plate'] ?? '');
    $engine = trim($data['engine'] ?? '');
    $chassis = trim($data['chassis'] ?? '');
    if ($brand === '' || $sticker === '' || $plate === '' || $engine === '' || $chassis === '' || $color === '') {
        respond(['success' => false, 'message' => 'Brand, body number/sticker, plate, engine, chassis number, and color are required.'], 422);
    }
    validateUniqueTricycleFields($plate, $engine, $chassis, $sticker, $id);

    $driverId = filter_var($data['driver_id'] ?? null, FILTER_VALIDATE_INT);
    if ($driverId !== false && $driverId !== null) {
        $driver = getRecord('drivers', 'driver_id = ? AND status = ?', [$driverId, 'Approved']);
        $driverAssignment = getRecord('franchise_driver', 'driver_id = ? AND franchise_id = ?', [$driverId, $franchise['franchise_id']]);
        if (!$driver || !$driverAssignment) respond(['success' => false, 'message' => 'Only an active driver from your franchise can be assigned.'], 422);
    }

    $orDocument = saveDataUrlUpload($data['orDocumentData'] ?? '', 'tricycle_or', $existing['or_document'] ?? null);
    $vehicleImage = saveVehicleImage($data['vehicleImageData'] ?? '', 'tricycle_image', $existing['vehicle_image'] ?? null);
    if (!$orDocument) respond(['success' => false, 'message' => 'The OR document image is required.'], 422);
    updateRecord('tricycles', ['brand' => $brand, 'sticker_number' => $sticker, 'plate_number' => $plate, 'engine_number' => $engine, 'chassis_number' => $chassis, 'color' => $color, 'or_number' => $orNumber, 'or_document' => $orDocument, 'vehicle_image' => $vehicleImage], 'tricycle_id = ?', [$id]);
    deleteRecord('driver_tricycle', 'tricycle_id = ?', [$id]);
    if ($driverId !== false && $driverId !== null) insertSomething('driver_tricycle', ['driver_id' => $driverId, 'tricycle_id' => $id]);

    $ownerName = currentAccountName();
    $franchiseName = $franchise['franchise_name'] ?? 'the franchise';
    $adminEmail = getAdminEmailByFranchiseId($franchise['franchise_id']) ?: getAdminEmail();
    createNotification('Tricycle Details Updated', "$ownerName changed tricycle details for \"$brand ($plate)\" at \"$franchiseName\".", 'Tricycle', 'warning', $adminEmail, $id, 'tricycle_updated');
    respond(['success' => true]);
}
function updateVehicleImage($data) {
    $franchise = ownedFranchise();
    $type = trim((string) ($data['type'] ?? ''));
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$franchise || !$id || !in_array($type, ['tricycle', 'owner_tricycle'], true)) {
        respond(['success' => false, 'message' => 'Vehicle not found in your franchise.'], 404);
    }
    ensureFranchiseOwnerTricycleTable();
    ensureTricycleImageColumns();

    if ($type === 'owner_tricycle') {
        $vehicle = getRecord('franchise_owner_tricycle', 'owner_tricycle_id = ? AND franchise_id = ? AND status <> ?', [$id, (int) $franchise['franchise_id'], 'Archived']);
        $table = 'franchise_owner_tricycle';
        $where = 'owner_tricycle_id = ? AND franchise_id = ?';
        $whereParams = [$id, (int) $franchise['franchise_id']];
    } else {
        $assignment = getRecord('franchise_tricycle', 'franchise_id = ? AND tricycle_id = ?', [(int) $franchise['franchise_id'], $id]);
        $vehicle = $assignment ? getRecord('tricycles', 'tricycle_id = ?', [$id]) : null;
        $table = 'tricycles';
        $where = 'tricycle_id = ?';
        $whereParams = [$id];
    }
    if (!$vehicle) respond(['success' => false, 'message' => 'Vehicle not found in your franchise.'], 404);

    if (!empty($data['removeImage'])) {
        $imagePath = null;
    } else {
        $imageData = $data['vehicleImageData'] ?? '';
        if ($imageData === '') respond(['success' => false, 'message' => 'Choose a vehicle image to upload.'], 422);
        $imagePath = saveVehicleImage($imageData, $type === 'owner_tricycle' ? 'owner_tricycle_image' : 'tricycle_image');
    }
    if (!updateRecord($table, ['vehicle_image' => $imagePath], $where, $whereParams)) {
        throw new RuntimeException('Unable to update vehicle image.');
    }
    respond(['success' => true, 'image' => $imagePath ? uploadUrl($imagePath) : '']);
}
function cancelPendingSubmission($data) {
    $franchise = ownedFranchise();
    $type = trim((string) ($data['type'] ?? ''));
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$franchise || !$id || !in_array($type, ['driver', 'tricycle'], true)) respond(['success' => false, 'message' => 'Pending submission not found.'], 404);

    $franchiseName = $franchise['franchise_name'] ?? 'your franchise';
    if ($type === 'driver') {
        $assignment = getRecord('franchise_driver', 'franchise_id = ? AND driver_id = ?', [$franchise['franchise_id'], $id]);
        $driver = getRecord('drivers', "driver_id = ? AND status IN (?, ?)", [$id, 'Pending', 'For Review']);
        if (!$assignment || !$driver) respond(['success' => false, 'message' => 'Only pending driver submissions can be cancelled.'], 422);
        deleteRecord('driver_tricycle', 'driver_id = ?', [$id]);
        deleteRecord('franchise_driver', 'franchise_id = ? AND driver_id = ?', [$franchise['franchise_id'], $id]);
        deleteRecord('drivers', 'driver_id = ?', [$id]);
        $subject = 'rider ' . $driver['full_name'];
        $relatedType = 'driver_submission_cancelled';
    } else {
        $assignment = getRecord('franchise_tricycle', 'franchise_id = ? AND tricycle_id = ?', [$franchise['franchise_id'], $id]);
        $tricycle = getRecord('tricycles', 'tricycle_id = ?', [$id]);
        $tricycleStatus = strtolower(trim((string) ($tricycle['status'] ?? '')));
        if (!$assignment || !$tricycle || $tricycleStatus !== 'pending') respond(['success' => false, 'message' => 'Only pending tricycle submissions can be cancelled.'], 422);
        deleteRecord('driver_tricycle', 'tricycle_id = ?', [$id]);
        deleteRecord('franchise_tricycle', 'franchise_id = ? AND tricycle_id = ?', [$franchise['franchise_id'], $id]);
        deleteRecord('tricycles', 'tricycle_id = ?', [$id]);
        $subject = 'tricycle ' . ($tricycle['plate_number'] ?: 'Unit ' . $id);
        $relatedType = 'tricycle_submission_cancelled';
    }

    $message = currentAccountName() . " cancelled application for \"$subject\" on \"$franchiseName\".";
    foreach (getSuperAdminEmails() as $email) {
        createNotification('Application Cancelled', $message, ucfirst($type), 'warning', $email, $id, $relatedType);
    }
    respond(['success' => true]);
}
function notifySuperAdminsOfOwnerTricycleAction($emails, $franchise, $tricycle, $action) {
    $plate = trim((string) ($tricycle['plate_number'] ?? '')) ?: 'Unit ' . (int) $tricycle['owner_tricycle_id'];
    $franchiseName = trim((string) ($franchise['franchise_name'] ?? 'the franchise'));
    $ownerName = currentAccountName();
    $title = "Owner Tricycle $action: $plate";
    $message = "$ownerName $action owner tricycle $plate for \"$franchiseName\".";
    foreach ($emails as $email) {
        createNotification($title, $message, 'Tricycle', 'warning', $email, (int) $tricycle['owner_tricycle_id'], 'owner_tricycle_rider_action');
    }
}
function updateOwnedStatus($data) {
    $franchise = ownedFranchise();
    $type = trim((string) ($data['type'] ?? ''));
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    $status = trim((string) ($data['status'] ?? ''));
    if (!$franchise || !$id || !in_array($type, ['driver', 'tricycle', 'owner_tricycle'], true) || !in_array($status, ['Active', 'Inactive'], true)) respond(['success' => false, 'message' => 'Invalid status request.'], 422);

    if ($type === 'owner_tricycle') {
        ensureFranchiseOwnerTricycleTable();
        $existing = getRecord('franchise_owner_tricycle', 'owner_tricycle_id = ? AND franchise_id = ?', [$id, (int) $franchise['franchise_id']]);
        if (!$existing) respond(['success' => false, 'message' => 'Tricycle not found in your units.'], 404);
        if (($existing['status'] ?? '') === 'Pending') respond(['success' => false, 'message' => 'Pending tricycles cannot be disabled or re-enabled.'], 422);
        if (($existing['status'] ?? '') === 'Archived') respond(['success' => false, 'message' => 'Archived tricycles cannot be changed.'], 422);
        if (($existing['status'] ?? '') === $status) respond(['success' => false, 'message' => 'This tricycle is already ' . strtolower($status) . '.'], 409);
        $superAdminEmails = getSuperAdminEmails();
        if (!$superAdminEmails) respond(['success' => false, 'message' => 'No active Super Admin is available to receive this change. Please try again later.'], 503);
        if (!updateRecord('franchise_owner_tricycle', ['status' => $status], 'owner_tricycle_id = ? AND franchise_id = ?', [$id, (int) $franchise['franchise_id']])) {
            throw new RuntimeException('Unable to update owner tricycle status.');
        }
        $action = $status === 'Active' ? 're-enabled' : 'disabled';
        notifySuperAdminsOfOwnerTricycleAction($superAdminEmails, $franchise, $existing, $action);
        respond(['success' => true, 'label' => 'tricycle ' . ($existing['plate_number'] ?: 'Unit ' . $id)]);
    } elseif ($type === 'driver') {
        ensureDriverStatusColumn();
        $assignment = getRecord('franchise_driver', 'franchise_id = ? AND driver_id = ?', [$franchise['franchise_id'], $id]);
        $existing = getRecord('drivers', 'driver_id = ?', [$id]);
        if (!$assignment || !$existing) respond(['success' => false, 'message' => 'Driver not found in your franchise.'], 404);
        if (($existing['status'] ?? '') === 'Pending' || ($existing['status'] ?? '') === 'For Review') respond(['success' => false, 'message' => 'Pending driver submissions must be cancelled instead.'], 422);
        $storedStatus = $status === 'Active' ? 'Approved' : 'Inactive';
        updateRecord('drivers', ['status' => $storedStatus], 'driver_id = ?', [$id]);
        if ($storedStatus === 'Inactive') deleteRecord('driver_tricycle', 'driver_id = ?', [$id]);
        $label = 'driver ' . $existing['full_name'];
    } else {
        ensureTricycleStatusColumn();
        $assignment = getRecord('franchise_tricycle', 'franchise_id = ? AND tricycle_id = ?', [$franchise['franchise_id'], $id]);
        $existing = getRecord('tricycles', 'tricycle_id = ?', [$id]);
        if (!$assignment || !$existing) respond(['success' => false, 'message' => 'Tricycle not found in your franchise.'], 404);
        if (($existing['status'] ?? '') === 'Pending') respond(['success' => false, 'message' => 'Pending tricycle submissions must be cancelled instead.'], 422);
        updateRecord('tricycles', ['status' => $status], 'tricycle_id = ?', [$id]);
        if ($status === 'Inactive') deleteRecord('driver_tricycle', 'tricycle_id = ?', [$id]);
        $label = 'tricycle ' . ($existing['plate_number'] ?: 'Unit ' . $id);
    }
    respond(['success' => true, 'label' => $label]);
}
function removeOwnedRecord($data) {
    $franchise = ownedFranchise();
    $type = trim((string) ($data['type'] ?? ''));
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$franchise || !$id || !in_array($type, ['driver', 'tricycle', 'owner_tricycle'], true)) respond(['success' => false, 'message' => 'Record not found.'], 404);

    if ($type === 'owner_tricycle') {
        ensureFranchiseOwnerTricycleTable();
        $existing = getRecord('franchise_owner_tricycle', 'owner_tricycle_id = ? AND franchise_id = ?', [$id, (int) $franchise['franchise_id']]);
        if (!$existing || ($existing['status'] ?? '') === 'Archived') respond(['success' => false, 'message' => 'Tricycle not found in your units.'], 404);
        $superAdminEmails = getSuperAdminEmails();
        if (!$superAdminEmails) respond(['success' => false, 'message' => 'No active Super Admin is available to receive this change. Please try again later.'], 503);
        if (!updateRecord('franchise_owner_tricycle', ['status' => 'Archived'], 'owner_tricycle_id = ? AND franchise_id = ?', [$id, (int) $franchise['franchise_id']])) {
            throw new RuntimeException('Unable to archive owner tricycle.');
        }
        notifySuperAdminsOfOwnerTricycleAction($superAdminEmails, $franchise, $existing, 'archived');
        respond(['success' => true]);
    }

    $ownerName = currentAccountName();
    $franchiseName = $franchise['franchise_name'] ?? 'the franchise';

    if ($type === 'driver') {
        $assignment = getRecord('franchise_driver', 'franchise_id = ? AND driver_id = ?', [$franchise['franchise_id'], $id]);
        $existing = getRecord('drivers', 'driver_id = ?', [$id]);
        if (!$assignment || !$existing) respond(['success' => false, 'message' => 'Driver not found in your franchise.'], 404);
        deleteRecord('driver_tricycle', 'driver_id = ?', [$id]);
        deleteRecord('franchise_driver', 'franchise_id = ? AND driver_id = ?', [$franchise['franchise_id'], $id]);
        deleteRecord('drivers', 'driver_id = ?', [$id]);
        $subject = 'driver "' . $existing['full_name'] . '"';
        $relatedType = 'driver_removed';
    } else {
        $assignment = getRecord('franchise_tricycle', 'franchise_id = ? AND tricycle_id = ?', [$franchise['franchise_id'], $id]);
        $existing = getRecord('tricycles', 'tricycle_id = ?', [$id]);
        if (!$assignment || !$existing) respond(['success' => false, 'message' => 'Tricycle not found in your franchise.'], 404);
        deleteRecord('driver_tricycle', 'tricycle_id = ?', [$id]);
        deleteRecord('franchise_tricycle', 'franchise_id = ? AND tricycle_id = ?', [$franchise['franchise_id'], $id]);
        deleteRecord('tricycles', 'tricycle_id = ?', [$id]);
        $subject = 'tricycle "' . ($existing['plate_number'] ?: 'Unit ' . $id) . '"';
        $relatedType = 'tricycle_removed';
    }

    $message = "$ownerName removed $subject from \"$franchiseName\".";
    foreach (getSuperAdminEmails() as $email) {
        createNotification('Record Removed', $message, ucfirst($type), 'urgent', $email, $id, $relatedType);
    }
    respond(['success' => true]);
}
function changeAccountPassword($data) {
    $currentPassword = $data['currentPassword'] ?? '';
    $newPassword = $data['newPassword'] ?? '';
    $confirmPassword = $data['confirmPassword'] ?? '';
    if (!is_string($currentPassword) || !is_string($newPassword) || !is_string($confirmPassword)
        || $currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        respond(['success' => false, 'message' => 'Please complete all password fields.'], 422);
    }
    if (strlen($newPassword) < 8) {
        respond(['success' => false, 'message' => 'New password must be at least 8 characters.'], 422);
    }
    if (!hash_equals($newPassword, $confirmPassword)) {
        respond(['success' => false, 'message' => 'New passwords do not match.'], 422);
    }

    $loginSource = $_SESSION['login_source'] ?? 'rider';
    $isAdmin = $loginSource === 'admin';
    $accountId = (int) ($_SESSION[$isAdmin ? 'admin_id' : 'rider_id'] ?? 0);
    $table = $isAdmin ? 'admins' : 'riders';
    $idColumn = $isAdmin ? 'admin_id' : 'rider_id';
    $account = $accountId > 0 ? getRecord($table, "$idColumn = ? AND status = ?", [$accountId, 'Active']) : null;
    $currentPasswordMatches = $account && !empty($account['password']) && (
        password_verify($currentPassword, $account['password'])
        || ($isAdmin && hash_equals((string) $account['password'], $currentPassword))
    );
    if (!$currentPasswordMatches) respond(['success' => false, 'message' => 'Current password is incorrect.'], 422);
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if ($passwordHash === false) throw new RuntimeException('Unable to hash account password.');
    if (!updateRecord($table, ['password' => $passwordHash], "$idColumn = ? AND status = ?", [$accountId, 'Active'])) {
        throw new RuntimeException('Unable to save the new account password.');
    }
    $accountType = $isAdmin ? 'Admin' : 'Rider';
    $accountName = $isAdmin
        ? (trim((string) ($account['first_name'] ?? '') . ' ' . (string) ($account['last_name'] ?? '')) ?: "Admin #$accountId")
        : (trim((string) ($account['full_name'] ?? '')) ?: "Rider #$accountId");
    $accountEmail = trim((string) ($account['email'] ?? ''));
    if (!notifyAccountPasswordChanged($accountId, $accountType, $accountName, $accountEmail)) {
        respond([
            'success' => true,
            'message' => 'Your password has been changed.',
            'notificationWarning' => 'Your password changed, but one or more notification emails or Super Admin alerts could not be delivered.'
        ]);
    }
    respond(['success' => true, 'message' => 'Your password has been changed.']);
}
try {
    $loginSource = $_SESSION['login_source'] ?? 'rider';
    $hasAdminSession = $loginSource === 'admin' && !empty($_SESSION['admin_id']);
    $hasRiderSession = $loginSource === 'rider'
        && !empty($_SESSION['rider_id'])
        && trim((string) ($_SESSION['rider_email'] ?? '')) !== '';
    if (!$hasAdminSession && !$hasRiderSession) respond(['success' => false, 'message' => 'Valid account login required.'], 401);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') listData();
    $data = requestData();
    if (($data['action'] ?? '') === 'update-profile-photo') updateRiderProfilePhoto($data);
    if (($data['action'] ?? '') === 'change-password') changeAccountPassword($data);
    if (($data['action'] ?? '') === 'create-franchise') createFranchiseApplication($data);
    if (($data['action'] ?? '') === 'apply-renewal') applyRenewal($data);
    if (($data['action'] ?? '') === 'create') createDriver($data);
    if (($data['action'] ?? '') === 'update-driver') updateDriver($data);
    if (($data['action'] ?? '') === 'assign-driver') assignDriver($data);
    if (($data['action'] ?? '') === 'create-tricycle') createTricycle($data);
    if (($data['action'] ?? '') === 'create-franchise-owner-tricycle') createFranchiseOwnerTricycle($data);
    if (($data['action'] ?? '') === 'update-tricycle') updateTricycle($data);
    if (($data['action'] ?? '') === 'update-vehicle-image') updateVehicleImage($data);
    if (($data['action'] ?? '') === 'cancel-submission') cancelPendingSubmission($data);
    if (($data['action'] ?? '') === 'update-status') updateOwnedStatus($data);
    if (($data['action'] ?? '') === 'remove-record') removeOwnedRecord($data);
    if (($data['action'] ?? '') === 'update-profile') {
        ensureProfileMiddleNameColumn($loginSource === 'admin' ? 'admins' : 'riders', $loginSource === 'admin' ? 'first_name' : 'full_name');
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id !== (int) $_SESSION['rider_id']) respond(['success' => false, 'message' => 'Profile not found.'], 404);
        $name = trim((string) ($data['name'] ?? ''));
        $middleName = trim((string) ($data['middleName'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(['success' => false, 'message' => 'Enter a name and a valid email address.'], 422);
        }
        if ($loginSource === 'admin') {
            $adminId = (int) $_SESSION['admin_id'];
            $admin = getRecord('admins', 'admin_id = ? AND status = ?', [$adminId, 'Active']);
            if (!$admin) respond(['success' => false, 'message' => 'Admin account not found.'], 404);
            $duplicateEmail = getRecord('admins', 'admin_id != ? AND email = ? LIMIT 1', [$adminId, $email]);
            if ($duplicateEmail) respond(['success' => false, 'message' => 'Another account is already using that email address.'], 409);
            $nameParts = preg_split('/\\s+/', $name, 2);
            $updated = updateRecord('admins', [
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? $admin['last_name'],
                'middle_name' => $middleName,
                'email' => $email
            ], 'admin_id = ?', [$adminId]);
            if (!$updated) throw new RuntimeException('Unable to update admin profile.');
            $_SESSION['admin_name'] = trim($nameParts[0] . ' ' . $middleName . ' ' . ($nameParts[1] ?? $admin['last_name']));
            $_SESSION['admin_email'] = $email;
        } else {
            global $conn;
            $rider = getRecord('riders', 'rider_id = ? AND status = ?', [$id, 'Active']);
            if (!$rider) respond(['success' => false, 'message' => 'Rider account not found.'], 404);
            $duplicateEmail = getRecord('riders', 'rider_id != ? AND email = ? LIMIT 1', [$id, $email]);
            if ($duplicateEmail) respond(['success' => false, 'message' => 'Another rider account is already using that email address.'], 409);
            if ($email !== $rider['email']) ensureApplicationsTable();
            mysqli_begin_transaction($conn);
            try {
                $updated = updateRecord('riders', [
                    'full_name' => $name,
                    'middle_name' => $middleName,
                    'email' => $email,
                    'contact_number' => trim((string) ($data['contact'] ?? ''))
                ], 'rider_id = ?', [$id]);
                if (!$updated) throw new RuntimeException('Unable to update rider profile.');
                if ($email !== $rider['email']) {
                    if (!updateRecord('franchises', ['owner_email' => $email], 'owner_email = ?', [$rider['email']])) {
                        throw new RuntimeException('Unable to update rider franchise contact details.');
                    }
                    if (!updateRecord('franchise_applications', ['rider_email' => $email], 'rider_id = ?', [$id])) {
                        throw new RuntimeException('Unable to update rider application contact details.');
                    }
                }
                mysqli_commit($conn);
            } catch (Throwable $error) {
                mysqli_rollback($conn);
                throw $error;
            }
        }
        $_SESSION['rider_name'] = $name;
        $_SESSION['rider_email'] = $email;
        respond(['success' => true]);
    }
    respond(['success' => false, 'message' => 'Invalid request.'], 400);
} catch (Throwable $error) {
    $errorMessage = $error->getMessage();
    error_log('Rider app error: ' . $errorMessage);
    if ($error instanceof InvalidArgumentException) {
        respond(['success' => false, 'message' => $errorMessage], 422);
    }
    if (stripos($errorMessage, 'Duplicate entry') !== false || stripos($errorMessage, 'duplicate key') !== false) {
        respond(['success' => false, 'message' => 'Some submitted information is already registered. Please use unique details and try again.'], 409);
    }
    respond(['success' => false, 'message' => 'Unable to process rider request.'], 500);
}
