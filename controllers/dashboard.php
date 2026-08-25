<?php
header('Content-Type: application/json; charset=utf-8');

$modelsPath = __DIR__ . '/../models';
set_include_path($modelsPath . PATH_SEPARATOR . get_include_path());
require_once $modelsPath . '/functions.php';

function respond($payload, $status = 200)
{
	http_response_code($status);
	echo json_encode($payload);
	exit;
}

try {
	$drivers = getAllRecords('drivers', 'ORDER BY full_name');
	$franchises = getAllRecords('franchises');
	$tricycles = getAllRecords('tricycles');
	$renewals = getAllRecords('renewals');
	$notifications = getAllRecords('notifications', 'ORDER BY created_at DESC LIMIT 5');

	$driverRenewals = array_map(function ($driver) {
		return [
			'name' => $driver['full_name'],
			'expiry' => null,
			'status' => $driver['status']
		];
	}, array_slice($drivers, 0, 5));

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
			'inactiveDrivers' => count(array_filter($drivers, fn($driver) => $driver['status'] !== 'Approved')),
			'pendingLicenses' => count(array_filter($drivers, fn($driver) => $driver['status'] !== 'Approved')),
			'totalFranchises' => count($franchises),
			'activeFranchises' => $renewalOverview['active'],
			'expiredFranchises' => $renewalOverview['expired'],
			'totalTricycles' => count($tricycles),
			'totalRenewals' => count($renewals)
		],
		'driverRenewals' => $driverRenewals,
		'renewalOverview' => $renewalOverview,
		'notifications' => array_map(function ($notification) {
			return [
				'title' => $notification['title'] ?? '',
				'message' => $notification['message'] ?? '',
				'createdAt' => $notification['created_at'] ?? ''
			];
		}, $notifications)
	]);
} catch (Throwable $error) {
	respond(['success' => false, 'message' => 'Unable to load dashboard data.'], 500);
}
