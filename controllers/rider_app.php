<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
$modelsPath = __DIR__ . '/../models';
set_include_path($modelsPath . PATH_SEPARATOR . get_include_path());
require_once $modelsPath . '/functions.php';
require_once $modelsPath . '/notifications.php';
require_once $modelsPath . '/notification_triggers.php';
require_once $modelsPath . '/upload.php';

function respond($payload, $status = 200) { http_response_code($status); echo json_encode($payload); exit; }
function requestData() { $data = json_decode(file_get_contents('php://input'), true); return is_array($data) ? $data : $_POST; }
function ensureOwnershipTables() { global $conn; mysqli_query($conn, 'CREATE TABLE IF NOT EXISTS franchise_driver (assignment_id INT NOT NULL AUTO_INCREMENT, franchise_id INT NOT NULL, driver_id INT NOT NULL, PRIMARY KEY (assignment_id), UNIQUE KEY franchise_driver (franchise_id, driver_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'); }
function ensureApplicationsTable() { global $conn; mysqli_query($conn, "CREATE TABLE IF NOT EXISTS franchise_applications (application_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, rider_id INT NULL, rider_name VARCHAR(150) NOT NULL, rider_email VARCHAR(255) NOT NULL, franchise_id INT NULL, franchise_name VARCHAR(150) NULL, application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending', admin_comments TEXT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, address TEXT NULL, issue_date DATE NULL, expiry_date DATE NULL, receipt_photo VARCHAR(255) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }
function ensureTricycleStatusColumn() {
    global $conn;
    $check = mysqli_query($conn, "SHOW COLUMNS FROM tricycles LIKE 'status'");
    if ($check && mysqli_num_rows($check) === 0) {
        mysqli_query($conn, "ALTER TABLE tricycles ADD COLUMN status ENUM('Active','Inactive','Pending') NOT NULL DEFAULT 'Pending' AFTER plate_number");
    }
}
function ensureTricycleDocumentColumns() {
    global $conn;
    foreach (['or_document', 'cr_document'] as $column) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM tricycles LIKE '$column'");
        if ($check && mysqli_num_rows($check) === 0) {
            mysqli_query($conn, "ALTER TABLE tricycles ADD COLUMN $column VARCHAR(255) NULL");
        }
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
            'receiptPhoto' => $row['receipt_photo'] ?? null
        ];
    }, $rows);
}
function listData() {
    ensureOwnershipTables();
    ensureTricycleStatusColumn();
    ensureTricycleDocumentColumns();
    try {
        checkAndTriggerRenewalNotifications();
    } catch (Throwable $error) {
        error_log('Rider renewal notification check failed: ' . $error->getMessage());
    }
    $loginSource = $_SESSION['login_source'] ?? 'rider';
    $franchise = ownedFranchise();
    $application = $loginSource === 'admin' ? null : ($franchise ? null : pendingApplication());
    $franchiseId = (int) ($franchise['franchise_id'] ?? 0);
    $assignments = $franchiseId ? getAllRecords('franchise_tricycle', 'WHERE franchise_id = ?', [$franchiseId]) : [];
    $tricycleIds = array_map(fn($row) => (int) $row['tricycle_id'], $assignments);
    $tricycles = $tricycleIds ? getAllRecords('tricycles', 'WHERE tricycle_id IN (' . implode(',', $tricycleIds) . ') ORDER BY tricycle_id DESC') : [];
    $tricycleById = [];
    foreach ($tricycles as $tricycle) $tricycleById[(int) $tricycle['tricycle_id']] = $tricycle;
    $franchiseDrivers = $franchiseId ? getAllRecords('franchise_driver', 'WHERE franchise_id = ?', [$franchiseId]) : [];
    $driverIds = array_map(fn($row) => (int) $row['driver_id'], $franchiseDrivers);
    $drivers = $driverIds ? getAllRecords('drivers', 'WHERE driver_id IN (' . implode(',', $driverIds) . ') ORDER BY driver_id DESC') : [];
    $driverTricycleAssignments = getAllRecords('driver_tricycle');
    $assignedTricycleByDriver = [];
    foreach ($driverTricycleAssignments as $assignment) $assignedTricycleByDriver[(int) $assignment['driver_id']] = (int) $assignment['tricycle_id'];

    if ($loginSource === 'admin') {
        $profile = ['id' => (int) ($_SESSION['admin_id'] ?? 0), 'name' => currentAccountName(), 'role' => 'Admin', 'email' => currentAccountEmail(), 'contact' => ''];
        $franchisePayload = $franchise ? ['id' => $franchiseId, 'name' => $franchise['franchise_name'], 'owner' => $franchise['owner_name'], 'status' => $franchise['renewal_status'], 'expiry' => $franchise['expiry_date'], 'registered' => true] : ['id' => '', 'name' => '', 'owner' => $profile['name'], 'status' => '', 'registered' => false, 'hasApplication' => false];
        $driverRows = array_map(function ($driver) use ($assignedTricycleByDriver, $tricycleById, $franchiseId) {
            $tricycleId = $assignedTricycleByDriver[(int) $driver['driver_id']] ?? null;
            $tricycle = $tricycleId ? ($tricycleById[$tricycleId] ?? null) : null;
            return ['id' => (int) $driver['driver_id'], 'franchiseId' => $franchiseId, 'name' => $driver['full_name'], 'license' => $driver['driver_license'] ?: 'Not provided', 'tricycleId' => $tricycleId, 'tricycle' => $tricycle ? (($tricycle['brand'] ?: 'Tricycle') . ' - ' . ($tricycle['sticker_number'] ?: $tricycle['plate_number'])) : 'Unassigned', 'status' => normalizeDriverStatus($driver['status'] ?? 'Pending'), 'dob' => 'Age ' . ($driver['age'] ?: '-'), 'address' => $driver['address'] ?? '-', 'contact' => $driver['contact_number'] ?? '-', 'licenseExp' => 'Not recorded', 'docs' => array_values(array_filter([!empty($driver['driver_license']) ? ['name' => "Driver's License", 'url' => uploadUrl($driver['driver_license'])] : null, !empty($driver['or_cr']) ? ['name' => 'OR/CR', 'url' => uploadUrl($driver['or_cr'])] : null, !empty($driver['president_certificate']) ? ['name' => "President's Certificate", 'url' => uploadUrl($driver['president_certificate'])] : null]))];
        }, $drivers);
        $tricycleRows = array_map(fn($tricycle) => ['id' => (int) $tricycle['tricycle_id'], 'franchiseId' => $franchiseId, 'unit' => $tricycle['sticker_number'] ?: 'Not specified', 'brand' => $tricycle['brand'] ?: 'Not specified', 'plate' => $tricycle['plate_number'] ?: 'No plate', 'driver' => 'Unassigned', 'status' => normalizeTricycleStatus($tricycle['status'] ?? 'Pending'), 'engine' => $tricycle['engine_number'], 'chassis' => $tricycle['chassis_number'], 'color' => $tricycle['color'] ?: 'Not specified', 'docs' => array_values(array_filter([!empty($tricycle['or_document']) ? ['name' => 'OR Document', 'url' => uploadUrl($tricycle['or_document'])] : null]))], $tricycles);
        respond(['success' => true, 'franchise' => $franchisePayload, 'drivers' => $driverRows, 'tricycles' => $tricycleRows, 'notifications' => array_map(function ($row) { return ['id' => (int) $row['notification_id'], 'type' => $row['type'] ?? 'Franchise', 'severity' => $row['severity'] ?? 'info', 'title' => $row['title'] ?? '', 'message' => $row['message'] ?? '', 'isRead' => (bool) $row['is_read'], 'created_at' => str_replace(' ', 'T', $row['created_at'])]; }, currentNotifications()), 'profile' => $profile]);
    }

    $rider = getRecord('riders', 'rider_id = ? AND status = ?', [(int) $_SESSION['rider_id'], 'Active']);
    $driverRows = array_map(function ($driver) use ($assignedTricycleByDriver, $tricycleById, $franchiseId) {
        $tricycleId = $assignedTricycleByDriver[(int) $driver['driver_id']] ?? null;
        $tricycle = $tricycleId ? ($tricycleById[$tricycleId] ?? null) : null;
        return ['id' => (int) $driver['driver_id'], 'franchiseId' => $franchiseId, 'name' => $driver['full_name'], 'license' => $driver['driver_license'] ?: 'Not provided', 'tricycleId' => $tricycleId, 'tricycle' => $tricycle ? (($tricycle['brand'] ?: 'Tricycle') . ' - ' . ($tricycle['sticker_number'] ?: $tricycle['plate_number'])) : 'Unassigned', 'status' => normalizeDriverStatus($driver['status'] ?? 'Pending'), 'dob' => 'Age ' . ($driver['age'] ?: '-'), 'address' => $driver['address'] ?? '-', 'contact' => $driver['contact_number'] ?? '-', 'licenseExp' => 'Not recorded', 'docs' => array_values(array_filter([!empty($driver['driver_license']) ? ['name' => "Driver's License", 'url' => uploadUrl($driver['driver_license'])] : null, !empty($driver['or_cr']) ? ['name' => 'OR/CR', 'url' => uploadUrl($driver['or_cr'])] : null, !empty($driver['president_certificate']) ? ['name' => "President's Certificate", 'url' => uploadUrl($driver['president_certificate'])] : null]))];
    }, $drivers);
    $tricycleRows = array_map(fn($tricycle) => ['id' => (int) $tricycle['tricycle_id'], 'franchiseId' => $franchiseId, 'unit' => $tricycle['sticker_number'] ?: 'Not specified', 'brand' => $tricycle['brand'] ?: 'Not specified', 'plate' => $tricycle['plate_number'] ?: 'No plate', 'driver' => 'Unassigned', 'status' => normalizeTricycleStatus($tricycle['status'] ?? 'Pending'), 'engine' => $tricycle['engine_number'], 'chassis' => $tricycle['chassis_number'], 'color' => $tricycle['color'] ?: 'Not specified', 'docs' => array_values(array_filter([!empty($tricycle['or_document']) ? ['name' => 'OR Document', 'url' => uploadUrl($tricycle['or_document'])] : null]))], $tricycles);
    respond(['success' => true, 'franchise' => $franchise ? ['id' => $franchiseId, 'name' => $franchise['franchise_name'], 'owner' => $franchise['owner_name'], 'status' => $franchise['renewal_status'], 'expiry' => $franchise['expiry_date'], 'registered' => true] : ['id' => '', 'name' => $application['franchise_name'] ?? '', 'owner' => $_SESSION['rider_name'], 'status' => $application['status'] ?? '', 'registered' => false, 'hasApplication' => (bool) $application], 'drivers' => $driverRows, 'tricycles' => $tricycleRows, 'notifications' => array_map(function ($row) { return ['id' => (int) $row['notification_id'], 'type' => $row['type'] ?? 'Franchise', 'severity' => $row['severity'] ?? 'info', 'title' => $row['title'] ?? '', 'message' => $row['message'] ?? '', 'isRead' => (bool) $row['is_read'], 'created_at' => str_replace(' ', 'T', $row['created_at'])]; }, currentNotifications()), 'renewals' => getRenewalsForCurrentRider(), 'profile' => ['id' => (int) $rider['rider_id'], 'name' => $rider['full_name'], 'role' => 'Rider', 'email' => $rider['email'], 'contact' => $rider['contact_number'] ?? '']]);
}
function createFranchiseApplication($data) {
    ensureApplicationsTable();
    if (ownedFranchise() || pendingApplication()) respond(['success' => false, 'message' => 'You already have a franchise or an application awaiting review.'], 422);
    $name = trim($data['name'] ?? '');
    if ($name === '') respond(['success' => false, 'message' => 'Franchise name is required.'], 422);
    $issueYear = (int) date('Y') + 1;
    $issue = $issueYear . '-01-01';
    $expiry = ($issueYear + 1) . '-01-01';
    $receipt = saveDataUrlUpload($data['receiptDataUrl'] ?? '', 'franchise_application_receipt');
    $id = insertSomething('franchise_applications', ['rider_id' => (int) $_SESSION['rider_id'], 'rider_name' => $_SESSION['rider_name'], 'rider_email' => $_SESSION['rider_email'], 'franchise_name' => $name, 'address' => trim($data['address'] ?? ''), 'issue_date' => $issue, 'expiry_date' => $expiry, 'receipt_photo' => $receipt, 'status' => 'Pending']);
    notifySuperAdminsOfSubmission('Franchise', 'New Franchise Application', $_SESSION['rider_name'] . " submitted a franchise application for '$name'.", $id);
    respond(['success' => true, 'id' => $id], 201);
}
function applyRenewal($data) {
    $franchise = ownedFranchise();
    if (!$franchise) respond(['success' => false, 'message' => 'You need a registered franchise before applying for renewal.'], 422);

    $franchiseId = (int) ($franchise['franchise_id'] ?? 0);
    if ($franchiseId <= 0) respond(['success' => false, 'message' => 'Franchise not found.'], 404);

    $receipt = saveDataUrlUpload($data['receiptDataUrl'] ?? '', 'renewal_receipt');
    if (!$receipt) respond(['success' => false, 'message' => 'Please upload a valid receipt photo.'], 422);

    $year = !empty($franchise['expiry_date']) ? max((int) date('Y'), (int) date('Y', strtotime($franchise['expiry_date']))) : (int) date('Y');
    $renewalDate = $year . '-01-01';
    $dueDate = ($year + 1) . '-01-01';

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
    $birthDate = $dob !== '' ? DateTime::createFromFormat('!Y-m-d', $dob) : false;
    $dateErrors = DateTime::getLastErrors();
    $hasDateErrors = is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0);
    if ($name === '' || $birthDate === false || $hasDateErrors) respond(['success' => false, 'message' => 'Please enter your full name and a valid date of birth.'], 422);
    $age = $birthDate->diff(new DateTime('today'))->y;
    if ($age < 18 || $age > 80) respond(['success' => false, 'message' => 'The driver must be between 18 and 80 years old.'], 422);
    ensureOwnershipTables();
    $tricycleId = filter_var($data['tricycle_id'] ?? null, FILTER_VALIDATE_INT);
    if ($tricycleId !== false && $tricycleId !== null) validateActiveTricycle($tricycleId, $franchise['franchise_id']);
    $id = insertSomething('drivers', ['full_name' => trim($data['name']), 'contact_number' => trim($data['contact'] ?? ''), 'age' => $age, 'address' => trim($data['address'] ?? ''), 'driver_license' => saveDataUrlUpload($data['licenseData'] ?? '', 'driver_license') ?: 'Not provided', 'or_cr' => saveDataUrlUpload($data['orcrData'] ?? '', 'or_cr'), 'president_certificate' => saveDataUrlUpload($data['presidentsData'] ?? '', 'president_certificate'), 'status' => 'Pending']);
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
function updateDriver($data) {
    $franchise = ownedFranchise();
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$franchise || !$id) respond(['success' => false, 'message' => 'Driver submission not found.'], 404);
    $assignment = getRecord('franchise_driver', 'franchise_id = ? AND driver_id = ?', [$franchise['franchise_id'], $id]);
    $existing = getRecord('drivers', 'driver_id = ?', [$id]);
    if (!$assignment || !$existing) respond(['success' => false, 'message' => 'Driver submission not found.'], 404);
    if (($existing['status'] ?? 'Pending') !== 'Pending') respond(['success' => false, 'message' => 'Only pending driver submissions can be edited.'], 422);

    $name = trim($data['name'] ?? '');
    $contact = trim($data['contact'] ?? '');
    $address = trim($data['address'] ?? '');
    $birthDate = DateTime::createFromFormat('Y-m-d', $data['dob'] ?? '');
    $age = $birthDate ? $birthDate->diff(new DateTime('today'))->y : (int) ($existing['age'] ?? 0);
    if ($name === '' || $contact === '' || $age === null || $age < 18 || $age > 80) respond(['success' => false, 'message' => 'Please provide valid driver information.'], 422);

    $license = saveDataUrlUpload($data['licenseData'] ?? '', 'driver_license', $existing['driver_license'] ?? null);
    $orCr = saveDataUrlUpload($data['orcrData'] ?? '', 'or_cr', $existing['or_cr'] ?? null);
    $certificate = saveDataUrlUpload($data['presidentsData'] ?? '', 'president_certificate', $existing['president_certificate'] ?? null);
    if (!$license) respond(['success' => false, 'message' => 'The driver license document is required.'], 422);
    $tricycleId = filter_var($data['tricycle_id'] ?? null, FILTER_VALIDATE_INT);
    if ($tricycleId !== false && $tricycleId !== null) validateActiveTricycle($tricycleId, $franchise['franchise_id']);
    updateRecord('drivers', ['full_name' => $name, 'contact_number' => $contact, 'age' => $age, 'address' => $address, 'driver_license' => $license, 'or_cr' => $orCr, 'president_certificate' => $certificate], 'driver_id = ?', [$id]);
    deleteRecord('driver_tricycle', 'driver_id = ?', [$id]);
    if ($tricycleId !== false && $tricycleId !== null) {
        deleteRecord('driver_tricycle', 'tricycle_id = ?', [$tricycleId]);
        insertSomething('driver_tricycle', ['driver_id' => $id, 'tricycle_id' => $tricycleId]);
    }

    $adminEmail = getAdminEmailByFranchiseId($franchise['franchise_id']) ?: getAdminEmail();
    createNotification('Driver Submission Edited', "The pending driver submission for $name was edited by the rider.", 'Driver', 'warning', $adminEmail, $id, 'driver_submission_edited');
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
    $driverId = filter_var($data['driver_id'] ?? null, FILTER_VALIDATE_INT);
    $sticker = trim($data['sticker'] ?? $data['sticker_number'] ?? $data['body_number'] ?? '');
    $plate = trim($data['plate'] ?? '');
    $engine = trim($data['engine'] ?? '');
    $chassis = trim($data['chassis'] ?? '');
    if ($brand === '' || $sticker === '' || $plate === '' || $engine === '' || $chassis === '' || $color === '') respond(['success' => false, 'message' => 'Brand, body number/sticker, plate, engine, chassis number, and color are required.'], 422);
    validateUniqueTricycleFields($plate, $engine, $chassis, $sticker);
    $orDocument = saveDataUrlUpload($data['orDocumentData'] ?? '', 'tricycle_or');
    if (!$orDocument) respond(['success' => false, 'message' => 'The OR document image is required.'], 422);
    if ($driverId !== false && $driverId !== null) {
        $driver = getRecord('drivers', 'driver_id = ? AND status = ?', [$driverId, 'Approved']);
        $driverAssignment = getRecord('franchise_driver', 'driver_id = ? AND franchise_id = ?', [$driverId, $franchise['franchise_id']]);
        if (!$driver || !$driverAssignment) respond(['success' => false, 'message' => 'Only an active driver from your franchise can be assigned.'], 422);
    }
    ensureTricycleStatusColumn();
    ensureTricycleDocumentColumns();
    $id = insertSomething('tricycles', ['brand' => $brand, 'sticker_number' => $sticker, 'plate_number' => $plate, 'engine_number' => $engine, 'chassis_number' => $chassis, 'color' => $color, 'or_document' => $orDocument, 'status' => 'Pending']);
    insertSomething('franchise_tricycle', ['franchise_id' => $franchise['franchise_id'], 'tricycle_id' => $id]);
    if ($driverId !== false && $driverId !== null) insertSomething('driver_tricycle', ['driver_id' => $driverId, 'tricycle_id' => $id]);
    notifySuperAdminsOfSubmission('Tricycle', 'New Tricycle Registration', $_SESSION['rider_name'] . " submitted tricycle registration for plate '" . trim($data['plate']) . "'.", $id);
    respond(['success' => true, 'id' => $id], 201);
}
function updateTricycle($data) {
    $franchise = ownedFranchise();
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$franchise || !$id) respond(['success' => false, 'message' => 'Tricycle submission not found.'], 404);
    $assignment = getRecord('franchise_tricycle', 'franchise_id = ? AND tricycle_id = ?', [$franchise['franchise_id'], $id]);
    $existing = getRecord('tricycles', 'tricycle_id = ?', [$id]);
    if (!$assignment || !$existing) respond(['success' => false, 'message' => 'Tricycle submission not found.'], 404);
    if (($existing['status'] ?? 'Pending') !== 'Pending') respond(['success' => false, 'message' => 'Only pending tricycle submissions can be edited.'], 422);

    $brand = trim($data['brand'] ?? '');
    $color = trim($data['color'] ?? '');
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
    if (!$orDocument) respond(['success' => false, 'message' => 'The OR document image is required.'], 422);
    updateRecord('tricycles', ['brand' => $brand, 'sticker_number' => $sticker, 'plate_number' => $plate, 'engine_number' => $engine, 'chassis_number' => $chassis, 'color' => $color, 'or_document' => $orDocument], 'tricycle_id = ?', [$id]);
    deleteRecord('driver_tricycle', 'tricycle_id = ?', [$id]);
    if ($driverId !== false && $driverId !== null) insertSomething('driver_tricycle', ['driver_id' => $driverId, 'tricycle_id' => $id]);

    $adminEmail = getAdminEmailByFranchiseId($franchise['franchise_id']) ?: getAdminEmail();
    createNotification('Tricycle Submission Edited', "The pending tricycle submission for $brand ($plate) was edited by the rider.", 'Tricycle', 'warning', $adminEmail, $id, 'tricycle_submission_edited');
    respond(['success' => true]);
}
try {
    $loginSource = $_SESSION['login_source'] ?? 'rider';
    $accountEmail = $loginSource === 'admin' ? trim($_SESSION['admin_email'] ?? '') : trim($_SESSION['rider_email'] ?? '');
    if (empty($_SESSION['rider_id']) || $accountEmail === '') respond(['success' => false, 'message' => 'Valid account login required.'], 401);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') listData();
    $data = requestData();
    if (($data['action'] ?? '') === 'create-franchise') createFranchiseApplication($data);
    if (($data['action'] ?? '') === 'apply-renewal') applyRenewal($data);
    if (($data['action'] ?? '') === 'create') createDriver($data);
    if (($data['action'] ?? '') === 'update-driver') updateDriver($data);
    if (($data['action'] ?? '') === 'assign-driver') assignDriver($data);
    if (($data['action'] ?? '') === 'create-tricycle') createTricycle($data);
    if (($data['action'] ?? '') === 'update-tricycle') updateTricycle($data);
    if (($data['action'] ?? '') === 'update-profile') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id !== (int) $_SESSION['rider_id']) respond(['success' => false, 'message' => 'Profile not found.'], 404);
        updateRecord('riders', ['full_name' => trim($data['name'] ?? ''), 'email' => trim($data['email'] ?? ''), 'contact_number' => trim($data['contact'] ?? '')], 'rider_id = ?', [$id]);
        $_SESSION['rider_name'] = trim($data['name'] ?? ''); $_SESSION['rider_email'] = trim($data['email'] ?? '');
        respond(['success' => true]);
    }
    respond(['success' => false, 'message' => 'Invalid request.'], 400);
} catch (Throwable $error) {
    $errorMessage = $error->getMessage();
    error_log('Rider app error: ' . $errorMessage);
    if (stripos($errorMessage, 'Duplicate entry') !== false || stripos($errorMessage, 'duplicate key') !== false) {
        respond(['success' => false, 'message' => 'Some submitted information is already registered. Please use unique details and try again.'], 409);
    }
    respond(['success' => false, 'message' => 'Unable to process rider request.'], 500);
}
