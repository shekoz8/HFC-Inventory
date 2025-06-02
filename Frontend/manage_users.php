<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user']) || empty($_SESSION['user']['id']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../Frontend/index.php?error=access_denied");
    exit;
}

// Get all users
$users = [];
$error = null;

try {
    $stmt = $conn->prepare("
        SELECT u.*, u.role as role_name
        FROM users u
        ORDER BY u.name ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = intval($_POST['delete_user']);
    
    if ($userId !== $_SESSION['user']['id']) { // Prevent self-deletion
        try {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "User deleted successfully";
                header("Location: manage_users.php");
                exit;
            } else {
                $_SESSION['error'] = "Failed to delete user";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "You cannot delete your own account";
    }
    header("Location: manage_users.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | HFC Inventory</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include "partials/navbar.php"; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Users</h2>
            <a href="partials/registerFE.php" class="btn btn-hfc">Add New User</a>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']); 
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']); 
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Last Login</th>
                                <th>Created At</th>
                                <th>Updated At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['role_name'] ?: 'N/A'); ?></td>
                                <td><?php echo $user['last_login'] ? htmlspecialchars($user['last_login']) : 'NULL'; ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($user['created_at'])); ?></td>
                                <td><?php echo $user['updated_at'] ? date('Y-m-d H:i:s', strtotime($user['updated_at'])) : 'NULL'; ?></td>
                                <td>
                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square"></i> Edit</a>
                                    <a href="copy_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-secondary"><i class="bi bi-files"></i> Copy</a>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                        <input type="hidden" name="delete_user" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" <?php echo $user['id'] == $_SESSION['user']['id'] ? 'disabled' : ''; ?>>
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
