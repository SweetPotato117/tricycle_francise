<?php
header('Content-Type: application/json; charset=utf-8');

$modelsPath = __DIR__ . '/../models';
set_include_path($modelsPath . PATH_SEPARATOR . get_include_path());
require_once $modelsPath . '/functions.php';
require_once $modelsPath . '/notification_triggers.php';

function respond($payload, $status = 200)
{
	http_response_code($status);
	echo json_encode($payload);
	exit;
}

try {
	markExpiredFranchises();
	$drivers = getAllRecords('drivers', 'ORDER BY full_name');
	$franchises = getAllRecords('franchises');
	$tricycles = getAllRecords('tricycles');
	$applications = getAllRecords('franchise_applications', 'WHERE status = ? ORDER BY application_date DESC', ['Pending']);
	$renewals = getAllRecords('renewals', 'WHERE receipt_status = ? ORDER BY receipt_submitted_at DESC', ['Submitted']);
	$pendingApplications = [];

	foreach ($applications as $application) $pendingApplications[] = [
		'type' => 'Franchise Application',
		'title' => $application['franchise_name'] ?: 'Unnamed franchise',
		'subtitle' => $application['rider_name'],
		'submitted' => $application['application_date']
	];
	foreach ($drivers as $driver) {
		if (!in_array($driver['status'], ['Pending', 'For Review'], true)) continue;
		$pendingApplications[] = [
			'type' => 'Driver Addition',
			'title' => $driver['full_name'],
			'subtitle' => 'Driver registration',
			'submitted' => $driver['created_at'] ?? ''
		];
	}
	foreach ($tricycles as $tricycle) {
		if (($tricycle['status'] ?? '') !== 'Pending') continue;
		$pendingApplications[] = [
			'type' => 'Tricycle Addition',
			'title' => $tricycle['plate_number'] ?: 'No plate number',
			'subtitle' => 'Tricycle registration',
			'submitted' => $tricycle['created_at'] ?? ''
		];
	}
	foreach ($renewals as $renewal) $pendingApplications[] = [
		'type' => 'Renewal Submission',
		'title' => ($renewal['renewal_year'] ?? date('Y')) . ' renewal payment',
		'subtitle' => 'Renewal receipt submission',
		'submitted' => $renewal['receipt_submitted_at'] ?: ($renewal['renewal_date'] ?? '')
	];
	usort($pendingApplications, fn($first, $second) => strcmp((string) $second['submitted'], (string) $first['submitted']));
	$pendingBreakdown = [];
	foreach ($pendingApplications as $application) $pendingBreakdown[$application['type']] = ($pendingBreakdown[$application['type']] ?? 0) + 1;

	$renewalOverview = [
		'active' => count(array_filter($franchises, fn($franchise) => $franchise['renewal_status'] === 'Active')),
		'pending' => count(array_filter($franchises, fn($franchise) => $franchise['renewal_status'] === 'Pending Renewal')),
		'expired' => count(array_filter($franchises, fn($franchise) => $franchise['renewal_status'] === 'Expired'))
	];

	respond([
		'success' => true,
		'stats' => [
			'totalDrivers' => count($drivers),
			'activeDrivers' => count(array_filter($drivers, fn($driver) => $driver['status'] === 'Approved')),
			'totalFranchises' => count($franchises),
			'activeFranchises' => $renewalOverview['active'],
			'activeTricycles' => count(array_filter($tricycles, fn($tricycle) => ($tricycle['status'] ?? '') === 'Active')),
			'pendingApplications' => count($pendingApplications)
		],
		'activeFranchises' => array_map(function ($franchise) {
			return [
				'name' => $franchise['franchise_name'],
				'owner' => $franchise['owner_name'],
				'expiry' => $franchise['expiry_date']
			];
		}, array_values(array_filter($franchises, fn($franchise) => $franchise['renewal_status'] === 'Active'))),
		'renewalOverview' => $renewalOverview,
		'pendingApplications' => $pendingApplications,
		'pendingBreakdown' => $pendingBreakdown
	]);
} catch (Throwable $error) {
	respond(['success' => false, 'message' => 'Unable to load dashboard data.'], 500);
}
