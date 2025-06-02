<?php
require_once 'includes/db.php';

try {
    // Check if users table exists
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result->num_rows == 0) {
        echo "Users table does not exist!\n";
        exit;
    }

    // Get table structure
    $result = $conn->query("DESCRIBE users");
    echo "Users table structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }

    // Check if Kevin Sang exists
    $stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE name = ?");
    $stmt->bind_param("s", "Kevin Sang");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo "\nKevin Sang's account details:\n";
        echo "ID: " . $user['id'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Role: " . $user['role'] . "\n";
    } else {
        echo "\nKevin Sang's account not found in database.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
