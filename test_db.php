<?php
require_once "includes/db.php";

if ($conn) {
    echo "Database connection successful!";
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        echo "<h3>Tables in database:</h3>";
        while ($row = $result->fetch_row()) {
            echo "<p>" . $row[0] . "</p>";
        }
    }
} else {
    echo "Database connection failed.";
}
?>
