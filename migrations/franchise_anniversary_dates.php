<?php
// Run once with PHP CLI. Preserve original dates before changing existing terms.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../models/dbconn.php';
require_once __DIR__ . '/../models/franchise_dates.php';
$backup = __DIR__ . '/franchise_dates_backup.json';
if (file_exists($backup)) { echo "Backup already exists; migration will not run again.\n"; exit; }
$franchises = mysqli_fetch_all(mysqli_query($conn, 'SELECT franchise_id, issue_date, expiry_date, created_at FROM franchises'), MYSQLI_ASSOC);
$applications = mysqli_fetch_all(mysqli_query($conn, 'SELECT application_id, franchise_id, issue_date, expiry_date, application_date FROM franchise_applications ORDER BY application_id DESC'), MYSQLI_ASSOC);
if (file_put_contents($backup, json_encode(['franchises' => $franchises, 'applications' => $applications], JSON_PRETTY_PRINT), LOCK_EX) === false) throw new RuntimeException('Unable to back up dates.');
mysqli_begin_transaction($conn);
try {
    $updateApplication = mysqli_prepare($conn, 'UPDATE franchise_applications SET issue_date = ?, expiry_date = ? WHERE application_id = ?');
    $linkedDates = [];
    foreach ($applications as $application) {
        $issue = $application['issue_date'];
        $applied = substr($application['application_date'], 0, 10);
        if (!$issue || (substr($issue, 5) === '01-01' && $issue > $applied)) $issue = $applied;
        $expiry = franchiseAnniversary($issue);
        $id = (int) $application['application_id'];
        mysqli_stmt_bind_param($updateApplication, 'ssi', $issue, $expiry, $id);
        mysqli_stmt_execute($updateApplication);
        if (!empty($application['franchise_id']) && !isset($linkedDates[$application['franchise_id']])) $linkedDates[$application['franchise_id']] = $issue;
    }
    $updateFranchise = mysqli_prepare($conn, 'UPDATE franchises SET issue_date = ?, expiry_date = ? WHERE franchise_id = ?');
    foreach ($franchises as $franchise) {
        $issue = $franchise['issue_date'];
        $created = substr($franchise['created_at'], 0, 10);
        if (!$issue || (substr($issue, 5) === '01-01' && $issue > $created)) $issue = $linkedDates[$franchise['franchise_id']] ?? $created;
        $expiry = franchiseAnniversary($issue);
        $id = (int) $franchise['franchise_id'];
        mysqli_stmt_bind_param($updateFranchise, 'ssi', $issue, $expiry, $id);
        mysqli_stmt_execute($updateFranchise);
    }
    mysqli_commit($conn);
    echo 'Updated ' . count($franchises) . ' franchises and ' . count($applications) . " applications.\n";
} catch (Throwable $error) {
    mysqli_rollback($conn);
    throw $error;
}
