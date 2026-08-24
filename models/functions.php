<?php
include 'dbconn.php';

/**
 * Insert a record into any table using prepared statements
 * 
 * Sample usage:
 * $result = insertRecord('users', [
 *     'name' => 'John Doe',
 *     'email' => 'john@example.com',
 *     'age' => 30
 * ]);
 * 
 * Sample output: true on success, false on failure
 */
function insertRecord($table, $data)
{
    global $conn;

    // Validate table name (basic safety check)
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        throw new InvalidArgumentException("Invalid table name");
    }

    $columns = implode(", ", array_keys($data));
    $placeholders = implode(", ", array_fill(0, count($data), "?"));

    $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    $stmt = mysqli_prepare($conn, $query);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    // Bind parameters
    $types = str_repeat("s", count($data)); // all strings by default
    $values = array_values($data);
    mysqli_stmt_bind_param($stmt, $types, ...$values);

    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $result;
}

/**
 * Insert an order with proper NULL handling
 * 
 * Sample usage:
 * $orderId = insertSomething('orders', [
 *     'customer_id' => 123,
 *     'product_name' => 'Widget',
 *     'quantity' => 2,
 *     'notes' => null
 * ]);
 * 
 * Sample output: 45 (the new order ID)
 */
function insertSomething($table, $data)
{
    global $conn;

    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        throw new InvalidArgumentException("Invalid table name");
    }

    $columns = implode(", ", array_keys($data));
    $placeholders = implode(", ", array_fill(0, count($data), "?"));

    $query = "INSERT INTO `$table` ($columns) VALUES ($placeholders)";
    $stmt = mysqli_prepare($conn, $query);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    // Build types and values arrays
    $types = '';
    $values = [];
    $boundValues = [];

    foreach ($data as $value) {
        if ($value === null) {
            $types .= 's'; // still bind as string but will be NULL
            $boundValues[] = null;
        } else {
            $types .= 's';
            $boundValues[] = $value;
        }
    }

    mysqli_stmt_bind_param($stmt, $types, ...$boundValues);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception("Failed to create data in database: " . mysqli_error($conn));
    }

    $insertId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    return $insertId;
}

/**
 * Update records with prepared statements
 * 
 * Sample usage:
 * $result = editRecord('users', 
 *     ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
 *     'id = ?',
 *     [5]
 * );
 * 
 * Sample output: true on success, false on failure
 */
function editRecord($table, $data, $condition, $conditionParams = [])
{
    global $conn;

    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        throw new InvalidArgumentException("Invalid table name");
    }

    $updateData = [];
    foreach ($data as $column => $value) {
        $updateData[] = "$column = ?";
    }
    $updateString = implode(", ", $updateData);

    $query = "UPDATE $table SET $updateString WHERE $condition";
    $stmt = mysqli_prepare($conn, $query);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    // Combine SET parameters and WHERE parameters
    $allParams = array_merge(array_values($data), $conditionParams);
    $types = str_repeat("s", count($allParams));

    mysqli_stmt_bind_param($stmt, $types, ...$allParams);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $result;
}

/**
 * Delete records safely - condition must use prepared statements
 * 
 * Sample usage:
 * $result = deleteRecord('users', 'id = ? AND status = ?', [15, 'inactive']);
 * 
 * Sample output: true on success, false on failure
 */
function deleteRecord($table, $condition, $conditionParams = [])
{
    global $conn;

    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        throw new InvalidArgumentException("Invalid table name");
    }

    $query = "DELETE FROM $table WHERE $condition";
    $stmt = mysqli_prepare($conn, $query);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    if (!empty($conditionParams)) {
        $types = str_repeat("s", count($conditionParams));
        mysqli_stmt_bind_param($stmt, $types, ...$conditionParams);
    }

    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $result;
}

/**
 * Get all records with optional prepared condition
 * 
 * Sample usage:
 * $users = getAllRecords('users', 'WHERE status = ? AND age > ?', ['active', 18]);
 * 
 * Sample output: Array of associative arrays containing user data
 */
function getAllRecords($table, $condition = '', $conditionParams = [])
{
    global $conn;

    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        throw new InvalidArgumentException("Invalid table name");
    }

    $query = "SELECT * FROM $table $condition";
    $stmt = mysqli_prepare($conn, $query);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    if (!empty($conditionParams)) {
        $types = str_repeat("s", count($conditionParams));
        mysqli_stmt_bind_param($stmt, $types, ...$conditionParams);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $data;
}

/**
 * Get a single record with prepared condition
 * 
 * Sample usage:
 * $user = getRecord('users', 'id = ?', [10]);
 * 
 * Sample output: Associative array with user data or null if not found
 */
function getRecord($table, $condition, $conditionParams = [])
{
    global $conn;

    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        throw new InvalidArgumentException("Invalid table name");
    }

    $query = "SELECT * FROM $table WHERE $condition";
    $stmt = mysqli_prepare($conn, $query);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    if (!empty($conditionParams)) {
        $types = str_repeat("s", count($conditionParams));
        mysqli_stmt_bind_param($stmt, $types, ...$conditionParams);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $data;
}

/**
 * Get record from multiple tables with JOIN using prepared statements
 * 
 * Sample usage:
 * $order = getRecordMultiTable(
 *     'orders', 'customers',
 *     'orders.customer_id = customers.id',
 *     'orders.id = ? AND customers.status = ?',
 *     [45, 'active']
 * );
 * 
 * Sample output: Associative array with joined data or null if not found
 */
function getRecordMultiTable($table1, $table2, $onCondition, $whereCondition, $whereParams = [])
{
    global $conn;

    // Validate table names
    if (
        !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table1) ||
        !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table2)
    ) {
        throw new InvalidArgumentException("Invalid table name");
    }

    $query = "SELECT * FROM $table1 LEFT JOIN $table2 ON $onCondition WHERE $whereCondition";
    $stmt = mysqli_prepare($conn, $query);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    if (!empty($whereParams)) {
        $types = str_repeat("s", count($whereParams));
        mysqli_stmt_bind_param($stmt, $types, ...$whereParams);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $data;
}

/**
 * Count records with optional prepared condition
 * 
 * Sample usage:
 * $count = countAllRecords('users', 'status = ? AND created_at > ?', ['active', '2023-01-01']);
 * 
 * Sample output: 42 (number of matching records)
 */
function countAllRecords($table, $whereCondition = '1', $whereParams = [])
{
    global $conn;

    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        throw new InvalidArgumentException("Invalid table name");
    }

    $query = "SELECT COUNT(*) as total FROM $table WHERE $whereCondition";
    $stmt = mysqli_prepare($conn, $query);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    if (!empty($whereParams)) {
        $types = str_repeat("s", count($whereParams));
        mysqli_stmt_bind_param($stmt, $types, ...$whereParams);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row['total'];
}

/**
 * Update record with proper NULL handling using prepared statements
 * 
 * Sample usage:
 * $result = updateRecord('products', 
 *     ['name' => 'New Product', 'price' => 29.99, 'description' => null],
 *     'id = ?',
 *     [7]
 * );
 * 
 * Sample output: true on success, false on failure
 */
function updateRecord($table, $data, $condition, $conditionParams = [])
{
    global $conn;

    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        throw new InvalidArgumentException("Invalid table name");
    }

    $updates = [];
    $updateValues = [];

    foreach ($data as $key => $value) {
        if ($value === null) {
            $updates[] = "$key = NULL";
        } else {
            $updates[] = "$key = ?";
            $updateValues[] = $value;
        }
    }

    $sql = "UPDATE $table SET " . implode(', ', $updates) . " WHERE $condition";
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    // Combine update values and condition parameters
    $allParams = array_merge($updateValues, $conditionParams);

    if (!empty($allParams)) {
        $types = str_repeat("s", count($allParams));
        mysqli_stmt_bind_param($stmt, $types, ...$allParams);
    }

    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $result;
}