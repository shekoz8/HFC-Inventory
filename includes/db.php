<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "hfc_inventory";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log($e->getMessage());
    exit("Database connection failed. Please try again later.");
}
?>
