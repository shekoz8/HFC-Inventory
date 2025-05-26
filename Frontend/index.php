<?php
session_start();

// Redirect if user is already logged in
if (isset($_SESSION['user'])) {
    error_log('User already logged in: ' . $_SESSION['user']['email']);
    $role = $_SESSION['user']['role'] ?? 'clerk';
    $redirect = $role === 'admin' ? 'Frontend/dashboard.php' : 'Frontend/inventory.php';
    header("Location: ../$redirect");    
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login | HFC Inventory</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Titillium+Web&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>

<body class="auth-page">
    <div class="auth-container">
        <!-- Logo -->
        <img src="images/HFC-logo.png" alt="Harvest Family Church Logo" class="church-logo">

        <h3 class="text-center mb-4">Welcome Back</h3>

        <!-- Error message -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" role="alert">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="../process_login.php" class="auth-form">
            <input type="email" name="email" id="email" placeholder="Email address" required>
            <div class="password-input-group">
                <input type="password" name="password" id="password" placeholder="Password" required>
                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility()">
                    <i class="bi bi-eye-slash" id="passwordIcon"></i>
                </button>
            </div>
            <button type="submit" class="btn btn-hfc w-100">Login</button>
        </form>

        <div class="auth-footer">
            <p>Don't have an account? <a href="partials/registerFE.php">Register here</a></p>
        </div>
    </div>
    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('bi-eye-slash');
                passwordIcon.classList.add('bi-eye');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('bi-eye');
                passwordIcon.classList.add('bi-eye-slash');
            }
        }
    </script>
</body>

</html>
