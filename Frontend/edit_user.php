<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../Frontend/index.php?error=access_denied');
    exit;
}

// Get user ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_users.php?error=invalid_user');
    exit;
}
$userId = intval($_GET['id']);

// Fetch user
$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    header('Location: manage_users.php?error=user_not_found');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $password = $_POST['password'];
    $updatePassword = !empty($password);
    
    if ($updatePassword) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare('UPDATE users SET name=?, email=?, role=?, password=?, updated_at=NOW() WHERE id=?');
        $stmt->bind_param('ssssi', $name, $email, $role, $hashedPassword, $userId);
    } else {
        $stmt = $conn->prepare('UPDATE users SET name=?, email=?, role=?, updated_at=NOW() WHERE id=?');
        $stmt->bind_param('sssi', $name, $email, $role, $userId);
    }
    if ($stmt->execute()) {
        $_SESSION['success'] = 'User updated successfully.';
        header('Location: manage_users.php');
        exit;
    } else {
        $error = 'Failed to update user.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container py-4">
    <h2>Edit User</h2>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select" required>
                <option value="admin" <?php if ($user['role'] === 'admin') echo 'selected'; ?>>Admin</option>
                <option value="clerk" <?php if ($user['role'] === 'clerk') echo 'selected'; ?>>Clerk</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Password (leave blank to keep current)</label>
            <input type="password" name="password" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary">Update User</button>
        <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
</body>
</html> 