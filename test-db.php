<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Point this to your actual database connection file
require_once __DIR__ . '/db.php'; 

if (isset($conn) && mysqli_ping($conn)) {
    echo "<h2>✅ Database Connected Successfully!</h2>";
    echo "<p>Connected to: " . mysqli_get_host_info($conn) . "</p>";
    
    // Check if the admins table is readable
    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM admins");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        echo "<p>✅ Query test passed! Total admins found: <strong>" . $row['total'] . "</strong></p>";
    } else {
        echo "<p>⚠️ Connected, but query failed: " . mysqli_error($conn) . "</p>";
    }
} else {
    echo "<h2>❌ Database Connection Failed</h2>";
    echo "<p>Error: " . mysqli_connect_error() . "</p>";
}
