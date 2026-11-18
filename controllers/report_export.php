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
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
            throw new InvalidArgumentException('Select a valid day for the report.');
        }
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
try {
    [$start, $end] = reportDateRange($type, $value);
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    exit($error->getMessage());
}

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

foreach ($tricycles as $tricycle) {
    $tricycleId = (int) $tricycle['tricycle_id'];
    $driverId = $driverByTricycle[$tricycleId] ?? null;
    $franchiseId = $franchiseByTricycle[$tricycleId] ?? null;
    
    $driver = $driverId !== null ? ($drivers[$driverId] ?? null) : null;
    $franchise = $franchiseId !== null ? ($franchises[$franchiseId] ?? null) : null;

    $registrationDates = array_filter([
        $driverAssignmentDates[$tricycleId] ?? null,
        $franchiseAssignmentDates[$tricycleId] ?? null,
        !empty($franchise['issue_date']) ? normalizeDateValue($franchise['issue_date']) : null,
        !empty($tricycle['created_at']) ? normalizeDateValue($tricycle['created_at']) : null
    ]);

    $matchingRegistrationDates = array_values(array_filter(
        $registrationDates,
        fn($date) => matchesRange($date, $start, $end)
    ));
    if (empty($matchingRegistrationDates)) {
        continue;
    }
    $registrationDate = getLatestDateForAssignment($matchingRegistrationDates);

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
}

$ownedTricycles = getAllRecords('franchise_owner_tricycle', 'ORDER BY owner_tricycle_id DESC');
$franchiseDocumentsById = [];
foreach (getAllRecords('franchise_documents') as $franchiseDocument) {
    $franchiseDocumentsById[(int) ($franchiseDocument['franchise_id'] ?? 0)] = $franchiseDocument;
}
foreach ($ownedTricycles as $ownedTricycle) {
    $franchiseId = (int) ($ownedTricycle['franchise_id'] ?? 0);
    $franchise = $franchises[$franchiseId] ?? null;
    $registrationDate = normalizeDateValue($ownedTricycle['created_at'] ?? null);
    if (!$registrationDate || !matchesRange($registrationDate, $start, $end)) {
        continue;
    }
    $operatorName = $franchise['owner_name'] ?? '';
    $franchiseDocuments = $franchiseDocumentsById[$franchiseId] ?? [];

    $reportRows[] = [
        'record_date' => $registrationDate,
        'operator_name' => $operatorName,
        'franchise_address' => $franchise['address'] ?? '',
        'brand' => $ownedTricycle['brand'] ?? '',
        'engine_number' => $ownedTricycle['engine_number'] ?? '',
        'chassis_number' => $ownedTricycle['chassis_number'] ?? '',
        'color' => $ownedTricycle['color'] ?? '',
        'plate_number' => $ownedTricycle['plate_number'] ?? '',
        'registration_date' => date('Y-m-d', strtotime($registrationDate)),
        'driver_name' => $operatorName,
        'driver_license_number' => $franchiseDocuments['drivers_license'] ?? '',
        'or_cr_number' => $ownedTricycle['or_number'] ?? '',
        'driver_address' => $franchise['address'] ?? '',
        'toda' => $franchise['franchise_name'] ?? '',
    ];

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

$hasReportData = count($exportRows) > 1;
$noRecordsMessage = '';
if (!$hasReportData) {
    $periodLabel = $type === 'day'
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $value)->format('d/m/Y')
        : ($type === 'month' ? $value : (string) $value);
    $noRecordsMessage = 'No records found for ' . $type . ': ' . $periodLabel;
    $exportRows[] = [$noRecordsMessage];
}

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('Reina Mercedes TODA Management System')
    ->setTitle('Reina Mercedes TODA Management System Report')
    ->setSubject('Tricycle, driver, and franchise report');

$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Report');
$sheet->mergeCells('A1:M1');
$sheet->setCellValue('A1', 'Reina Mercedes TODA Management System');
$sheet->mergeCells('A2:M2');
$sheet->setCellValue('A2', 'Report generated on: ' . date('d/m/Y'));
$sheet->fromArray($exportRows, null, 'A4');

$lastCell = $sheet->getHighestColumn() . $sheet->getHighestRow();
$lastRow = $sheet->getHighestRow();
$noRecordsRow = $hasReportData ? null : 5;
if ($noRecordsRow !== null) {
    $sheet->mergeCells('A5:M5');
}
$sheet->getStyle('A1:' . $lastCell)->getProtection()->setLocked(
    \PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_PROTECTED
);

$sheet->getStyle('A1:M1')->applyFromArray([
    'font' => [
        'name' => 'Arial',
        'size' => 18,
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => '174C3C'],
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
    ],
]);
$sheet->getStyle('A2:M2')->applyFromArray([
    'font' => [
        'name' => 'Arial',
        'size' => 11,
        'italic' => true,
        'color' => ['rgb' => '315B4C'],
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E8F2EC'],
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
    ],
]);
$sheet->getStyle('A4:M4')->applyFromArray([
    'font' => [
        'name' => 'Arial',
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => '2F6B57'],
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
        'wrapText' => true,
    ],
    'borders' => [
        'bottom' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
            'color' => ['rgb' => '174C3C'],
        ],
    ],
]);

if ($lastRow > 4) {
    $dataRange = 'A5:M' . $lastRow;
    $sheet->getStyle($dataRange)->applyFromArray([
        'font' => ['name' => 'Arial', 'size' => 10, 'color' => ['rgb' => '26332D']],
        'alignment' => [
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'borders' => [
            'bottom' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR,
                'color' => ['rgb' => 'D5E1DA'],
            ],
        ],
    ]);

    for ($row = 5; $row <= $lastRow; $row += 2) {
        $sheet->getStyle('A' . $row . ':M' . $row)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle('A' . $row . ':M' . $row)->getFill()
            ->getStartColor()->setRGB('F2F7F4');
    }
    if ($noRecordsRow !== null) {
        $sheet->getStyle('A5:M5')->applyFromArray([
            'font' => ['name' => 'Arial', 'size' => 11, 'italic' => true, 'color' => ['rgb' => '5B6472']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);
    }
}

foreach ([
    'A' => 24, 'B' => 20, 'C' => 20, 'D' => 20, 'E' => 14,
    'F' => 16, 'G' => 20, 'H' => 24, 'I' => 24, 'J' => 18,
    'K' => 38, 'L' => 24, 'M' => 38,
] as $column => $width) {
    $sheet->getColumnDimension($column)->setWidth($width);
}

$sheet->getRowDimension(1)->setRowHeight(34);
$sheet->getRowDimension(2)->setRowHeight(24);
$sheet->getRowDimension(3)->setRowHeight(10);
$sheet->getRowDimension(4)->setRowHeight(34);
$sheet->freezePane('A5');
if ($hasReportData) {
    $sheet->setAutoFilter('A4:M' . $lastRow);
}
$sheet->getSheetView()->setZoomScale(85);

$sheet->getPageSetup()
    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
    ->setFitToWidth(1)
    ->setFitToHeight(0);
$sheet->getPageSetup()->setFitToPage(true);
$sheet->getPageMargins()->setTop(0.5)->setRight(0.3)->setBottom(0.5)->setLeft(0.3);

$sheet->getProtection()->setPassword($sheetPassword);
unset($sheetPassword);
$sheet->getProtection()->setSheet(true);

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;
