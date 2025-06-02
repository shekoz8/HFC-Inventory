<?php
/**
 * Database Setup Script for Item Checkout System
 * This script creates the necessary tables for the item checkout and return workflow
 */

// Include database connection
require_once '../includes/db.php';

// Function to execute SQL from a file
function executeSqlFile($conn, $filename) {
    echo "Executing SQL file: $filename<br>";
    $sql = file_get_contents($filename);
    
    if ($conn->multi_query($sql)) {
        do {
            // Store first result set
            if ($result = $conn->store_result()) {
                $result->free();
            }
            // Print divider
            if ($conn->more_results()) {
                echo "--------------------<br>";
            }
        } while ($conn->next_result());
    }
    
    if ($conn->error) {
        echo "Error executing SQL: " . $conn->error . "<br>";
        return false;
    }
    
    echo "SQL executed successfully<br><br>";
    return true;
}

// Check if inventory_transactions table exists
$result = $conn->query("SHOW TABLES LIKE 'inventory_transactions'");
$transactionsTableExists = $result->num_rows > 0;

// Check if item_checkouts table exists
$result = $conn->query("SHOW TABLES LIKE 'item_checkouts'");
$checkoutsTableExists = $result->num_rows > 0;

// Check if activity_logs table exists
$result = $conn->query("SHOW TABLES LIKE 'activity_logs'");
$activityLogsTableExists = $result->num_rows > 0;

// Create inventory_transactions table if it doesn't exist
if (!$transactionsTableExists) {
    echo "<h3>Creating inventory_transactions table</h3>";
    executeSqlFile($conn, 'inventory_transactions.sql');
} else {
    echo "<h3>inventory_transactions table already exists</h3>";
}

// Create item_checkouts table if it doesn't exist
if (!$checkoutsTableExists) {
    echo "<h3>Creating item_checkouts table</h3>";
    executeSqlFile($conn, 'item_checkouts.sql');
} else {
    echo "<h3>item_checkouts table already exists</h3>";
}

// Create activity_logs table if it doesn't exist
if (!$activityLogsTableExists) {
    echo "<h3>Creating activity_logs table</h3>";
    executeSqlFile($conn, 'activity_logs.sql');
} else {
    echo "<h3>activity_logs table already exists</h3>";
}

// Check if the item_requests table has the required columns
$result = $conn->query("SHOW COLUMNS FROM item_requests LIKE 'reject_reason'");
$rejectReasonExists = $result->num_rows > 0;

$result = $conn->query("SHOW COLUMNS FROM item_requests LIKE 'rejected_by'");
$rejectedByExists = $result->num_rows > 0;

$result = $conn->query("SHOW COLUMNS FROM item_requests LIKE 'rejected_at'");
$rejectedAtExists = $result->num_rows > 0;

// Add missing columns to item_requests table if needed
if (!$rejectReasonExists || !$rejectedByExists || !$rejectedAtExists) {
    echo "<h3>Adding missing columns to item_requests table</h3>";
    
    if (!$rejectReasonExists) {
        $conn->query("ALTER TABLE item_requests ADD COLUMN reject_reason TEXT DEFAULT NULL AFTER fulfilled_at");
        echo "Added reject_reason column<br>";
    }
    
    if (!$rejectedByExists) {
        $conn->query("ALTER TABLE item_requests ADD COLUMN rejected_by INT(11) DEFAULT NULL AFTER reject_reason");
        echo "Added rejected_by column<br>";
    }
    
    if (!$rejectedAtExists) {
        $conn->query("ALTER TABLE item_requests ADD COLUMN rejected_at TIMESTAMP NULL DEFAULT NULL AFTER rejected_by");
        echo "Added rejected_at column<br>";
    }
} else {
    echo "<h3>item_requests table already has all required columns</h3>";
}

// Create checkout records for existing approved requests that don't have checkouts yet
$sql = "
    SELECT r.id, r.user_id, r.item_id, r.quantity 
    FROM item_requests r
    LEFT JOIN item_checkouts c ON r.id = c.request_id
    WHERE r.status = 'Approved' AND c.id IS NULL
";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<h3>Creating checkout records for existing approved requests</h3>";
    
    while ($row = $result->fetch_assoc()) {
        $stmt = $conn->prepare("
            INSERT INTO item_checkouts 
            (request_id, user_id, item_id, quantity, status, notes, created_at)
            VALUES (?, ?, ?, ?, 'Pending', 'Created from existing approved request', NOW())
        ");
        $stmt->bind_param("iiii", $row['id'], $row['user_id'], $row['item_id'], $row['quantity']);
        $stmt->execute();
        
        echo "Created checkout record for request ID: " . $row['id'] . "<br>";
    }
} else {
    echo "<h3>No existing approved requests need checkout records</h3>";
}

echo "<h3>Setup completed successfully!</h3>";
echo "<p><a href='/hfc_inventory/Frontend/dashboard.php' class='btn btn-primary'>Return to Dashboard</a></p>";
?>
