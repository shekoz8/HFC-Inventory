<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unauthorized Access</title>
    <link rel="stylesheet" href="css/style.css"> <!-- optional -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="text-center p-5">
    <h1 class="text-danger">Access Denied</h1>
    <p>You are not authorized to view this page.</p>
    <a href="index.php" class="btn btn-primary">Return to Login</a>
</body>
</html>