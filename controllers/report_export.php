<?php
require_once __DIR__ . '/../vendor/autoload.php';

$sheetPassword = $_POST['sheet_password'] ?? null;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_string($sheetPassword) || strlen($sheetPassword) < 8) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('A sheet password of at least 8 characters is required.');
}

require_once __DIR__ . '/../models/functions.php';

function reportDateRange($type, $value)
{
    $type = strtolower(trim((string) $type));
    $value = trim((string) $value);

    if ($type === 'day') {
        $date = $value !== '' ? new DateTime($value) : new DateTime('today');
        return [$date->format('Y-m-d 00:00:00'), $date->format('Y-m-d 23:59:59')];
    }

    if ($type === 'month') {
        if ($value === '') {
            $value = date('Y-m');
        }

        $date = DateTime::createFromFormat('Y-m', $value) ?: new DateTime($value);
        return [$date->format('Y-m-01 00:00:00'), $date->format('Y-m-t 23:59:59')];
    }

    if ($type === 'year') {
        $year = $value !== '' ? (int) $value : (int) date('Y');
        return [sprintf('%04d-01-01 00:00:00', $year), sprintf('%04d-12-31 23:59:59', $year)];
    }

    $date = new DateTime('today');
    return [$date->format('Y-m-d 00:00:00'), $date->format('Y-m-d 23:59:59')];
}

function normalizeDateValue($value)
{
    if (empty($value)) {
        return null;
    }

    $date = date_create($value);
    return $date ? $date->format('Y-m-d H:i:s') : null;
}

function matchesRange($value, $start, $end)
{
    if (empty($value)) {
        return false;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return false;
    }

    return $timestamp >= strtotime($start) && $timestamp <= strtotime($end);
}

function safeValue($record, $keys)
{
    foreach ($keys as $key) {
        if (isset($record[$key]) && $record[$key] !== null && $record[$key] !== '') {
            return $record[$key];
        }
    }

    return '';
}

function getLatestDateForAssignment($assignedDates)
{
    $dates = array_values(array_filter($assignedDates, fn($value) => $value !== null && $value !== ''));
    if (empty($dates)) {
        return null;
    }

    usort($dates, fn($a, $b) => strcmp($a, $b));
    return end($dates);
}

function assignmentDateMap($records, $idField, $dateField)
{
    $map = [];
    foreach ($records as $record) {
        $recordId = (int) ($record[$idField] ?? 0);
        $date = normalizeDateValue($record[$dateField] ?? null);
        if ($recordId > 0 && $date) {
            if (!isset($map[$recordId]) || $date > $map[$recordId]) {
                $map[$recordId] = $date;
            }
        }
    }
    return $map;
}

$type = $_POST['type'] ?? 'month';
$value = $_POST['value'] ?? '';
[$start, $end] = reportDateRange($type, $value);

$tricycles = getAllRecords('tricycles', 'ORDER BY tricycle_id DESC');
$drivers = [];
foreach (getAllRecords('drivers') as $driver) {
    $drivers[(int) $driver['driver_id']] = $driver;
}

$franchises = [];
foreach (getAllRecords('franchises') as $franchise) {
    $franchises[(int) $franchise['franchise_id']] = $franchise;
}

$driverAssignments = getAllRecords('driver_tricycle');
$franchiseAssignments = getAllRecords('franchise_tricycle');

$driverByTricycle = [];
foreach ($driverAssignments as $assignment) {
    $tricycleId = (int) ($assignment['tricycle_id'] ?? 0);
    $driverId = (int) ($assignment['driver_id'] ?? 0);
    if ($tricycleId > 0 && $driverId > 0) {
        $driverByTricycle[$tricycleId] = $driverId;
    }
}

$franchiseByTricycle = [];
foreach ($franchiseAssignments as $assignment) {
    $tricycleId = (int) ($assignment['tricycle_id'] ?? 0);
    $franchiseId = (int) ($assignment['franchise_id'] ?? 0);
    if ($tricycleId > 0 && $franchiseId > 0) {
        $franchiseByTricycle[$tricycleId] = $franchiseId;
    }
}

$driverAssignmentDates = assignmentDateMap($driverAssignments, 'tricycle_id', 'assigned_date');
$franchiseAssignmentDates = assignmentDateMap($franchiseAssignments, 'tricycle_id', 'assigned_date');

$reportRows = [];
$franchiseAddressRows = [];

foreach ($tricycles as $tricycle) {
    $tricycleId = (int) $tricycle['tricycle_id'];
    $driverId = $driverByTricycle[$tricycleId] ?? null;
    $franchiseId = $franchiseByTricycle[$tricycleId] ?? null;
    
    $driver = $driverId !== null ? ($drivers[$driverId] ?? null) : null;
    $franchise = $franchiseId !== null ? ($franchises[$franchiseId] ?? null) : null;

    $registrationDate = getLatestDateForAssignment([
        $driverAssignmentDates[$tricycleId] ?? null,
        $franchiseAssignmentDates[$tricycleId] ?? null
    ]);
    if (!$registrationDate && $franchise && $franchise['issue_date']) {
        $registrationDate = normalizeDateValue($franchise['issue_date']);
    }
    if (!$registrationDate && !empty($tricycle['created_at'])) {
        $registrationDate = normalizeDateValue($tricycle['created_at']);
    }

    // Apply date range filter only if we have a registration date
    if ($registrationDate && !matchesRange($registrationDate, $start, $end)) {
        continue;
    }

    // Add row even if driver or franchise is missing
    $reportRows[] = [
        'record_date' => $registrationDate ?: date('Y-m-d H:i:s'),
        'operator_name' => $franchise['owner_name'] ?? '',
        'franchise_address' => $franchise['address'] ?? '',
        'brand' => $tricycle['brand'] ?? '',
        'engine_number' => $tricycle['engine_number'] ?? '',
        'chassis_number' => $tricycle['chassis_number'] ?? '',
        'color' => $tricycle['color'] ?? '',
        'plate_number' => $tricycle['plate_number'] ?? '',
        'registration_date' => $registrationDate ? date('Y-m-d', strtotime($registrationDate)) : '',
        'driver_name' => $driver['full_name'] ?? '',
        'driver_license_number' => $driver['driver_license_number'] ?? '',
        'or_cr_number' => $driver['or_cr_number'] ?? '',
        'driver_address' => $driver['address'] ?? '',
        'toda' => $franchise['franchise_name'] ?? '',
    ];
    if ($franchiseId && $franchise) {
        $franchiseAddressRows[$franchiseId] = [
            'name' => $franchise['franchise_name'] ?? '',
            'address' => $franchise['address'] ?? ''
        ];
    }
}

usort($reportRows, fn($left, $right) => strcmp($left['record_date'], $right['record_date']));

$filename = 'tricycle_driver_franchise_report_' . $type . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $value ?: date('Y-m-d')) . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$headers = [
    'Operator Name',
    'Brand of Tricycle',
    'Engine Number',
    'Chassis Number',
    'Color',
    'Plate Number',
    'Date of Registration',
    'Driver',
    "Driver's License Number",
    'OR/CR Number',
    'Driver Address',
    'Toda',
    'TODA Address'
];

$exportRows = [$headers];

foreach ($reportRows as $row) {
    $exportRows[] = [
        $row['operator_name'],
        $row['brand'],
        $row['engine_number'],
        $row['chassis_number'],
        $row['color'],
        $row['plate_number'],
        $row['registration_date'],
        $row['driver_name'],
        $row['driver_license_number'],
        $row['or_cr_number'],
        $row['driver_address'],
        $row['toda'],
        $row['franchise_address']
    ];
}

foreach ($franchiseAddressRows as $franchiseAddress) {
    $exportRows[] = [
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        $franchiseAddress['name'],
        $franchiseAddress['address']
    ];
}

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Report');
$sheet->fromArray($exportRows, null, 'A1');

$lastCell = $sheet->getHighestColumn() . $sheet->getHighestRow();
$sheet->getStyle('A1:' . $lastCell)->getProtection()->setLocked(
    \PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_PROTECTED
);

$sheet->getProtection()->setPassword($sheetPassword);
unset($sheetPassword);
$sheet->getProtection()->setSheet(true);

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;
