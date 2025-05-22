<?php
// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: /hfc_inventory/Frontend/index.php");
    exit;
}

$email = trim($_POST["email"] ?? '');
$password = trim($_POST["password"] ?? '');

if (empty($email) || empty($password)) {
    $_SESSION['error'] = "Email and password are required.";
    header("Location: /hfc_inventory/Frontend/index.php");
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $_SESSION['error'] = "Account not found.";
        header("Location: /hfc_inventory/Frontend/index.php");
        exit;
    }

    $user = $result->fetch_assoc();

    if (!password_verify($password, $user['password'])) {
        $_SESSION['error'] = "Incorrect password.";
        header("Location: /hfc_inventory/Frontend/index.php");
        exit;
    }

    if (empty($user['role'])) {
        $_SESSION['error'] = "Your account has no assigned role. Contact admin.";
        header("Location: /hfc_inventory/Frontend/index.php");
        exit;
    }

    // ✅ Store session data
    $_SESSION['user'] = [
        'id' => $user['id'],
        'role' => $user['role'],
        'email' => $user['email'],
        'name' => $user['name']
    ];

    // Force session to write before redirect
session_write_close();

    // ✅ Redirect based on role
    $redirect = ($user['role'] === 'admin') 
        ? '/hfc_inventory/Frontend/dashboard.php' 
        : '/hfc_inventory/Frontend/inventory.php';

    header("Location: $redirect");
    exit;

} catch (Exception $e) {
    $_SESSION['error'] = "System error. Please try again later.";
    header("Location: /hfc_inventory/Frontend/index.php");
    exit;
}
?>
