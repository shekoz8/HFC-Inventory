<?php
session_start();
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';
    $role = trim($_POST["role"] ?? '');
    if (empty($role)) {
        $role = 'clerk';
    } else if (!in_array($role, ['clerk', 'admin'])) {
        $role = 'clerk'; // Only allow valid roles
    }

    if (!$name || !$email || !$password) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: ../Frontend/partials/registerFE.php");
        exit;
    }

    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters.";
        header("Location: ../Frontend/partials/registerFE.php");
        exit;
    }

    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $_SESSION['error'] = "An account with this email already exists.";
        header("Location: ../Frontend/partials/registerFE.php");
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $hashedPassword, $role);

    if ($stmt->execute()) {
        $_SESSION['user'] = [
            'id' => $stmt->insert_id,
            'name' => $name,
            'email' => $email,
            'role' => $role
        ];

        error_log("User registered successfully: ID=" . $stmt->insert_id . ", Name=$name, Role=$role");
        
        $redirect = ($role === 'admin') ? '../Frontend/dashboard.php' : '../Frontend/inventory.php';
        header("Location: $redirect");
        exit;
    } else {
        error_log("Registration failed: " . $stmt->error);
        $_SESSION['error'] = "Registration failed. Please try again.";
        header("Location: ../Frontend/partials/registerFE.php");
        exit;
    }
} else {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: ../Frontend/partials/registerFE.php");
    exit;
}
