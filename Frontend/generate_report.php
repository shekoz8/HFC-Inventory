<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    header("Location: ../Frontend/index.php?error=session_invalid");
    exit;
}

// Get all inventory items with their current status
try {
    $stmt = $conn->prepare("
        SELECT i.*, c.name as category_name, 
               CASE 
                   WHEN i.quantity <= 0 THEN 'Out of Stock'
                   WHEN i.quantity <= i.min_quantity THEN 'Low Stock'
                   ELSE 'In Stock'
               END as status
        FROM inventory_items i
        LEFT JOIN categories c ON i.category_id = c.id
        ORDER BY i.name ASC
    ");
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    try {
        $filename = "inventory_report_" . date('Y-m-d_H-i-s') . ".pdf";
        
        // Create PDF content
        $html = "<h1>HFC Inventory Report - " . date('F d, Y') . "</h1>
        <table border='1' cellpadding='5' cellspacing='0' width='100%'>
            <tr>
                <th>Item Name</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>Min Quantity</th>
                <th>Status</th>
            </tr>";
        
        foreach ($items as $item) {
            $html .= "<tr>
                <td>{$item['name']}</td>
                <td>{$item['category_name']}</td>
                <td>{$item['quantity']}</td>
                <td>{$item['min_quantity']}</td>
                <td><strong>{$item['status']}</strong></td>
            </tr>";
        }
        $html .= "</table>";
        
        // Save to file
        file_put_contents("../reports/{$filename}", $html);
        
        // Redirect to download
        header("Location: ../reports/{$filename}");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Error generating report: " . $e->getMessage();
        header("Location: generate_report.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Report | HFC Inventory</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include "partials/navbar.php"; ?>

    <div class="container py-4">
        <div class="card">
            <div class="card-header bg-hfc-blue text-blue d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-printer me-2"></i>Generate Inventory Report</h5>
                <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                    <a href="inventory.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Add Item</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
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

                <form method="POST" class="mb-4">
                    <button type="submit" name="generate_report" class="btn btn-hfc">
                        <i class="bi bi-printer me-2"></i>Generate Report
                    </button>
                </form>

                <h4>Preview Report</h4>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Min Quantity</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo $item['min_quantity']; ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo ($item['status'] === 'Out of Stock') ? 'danger' : 
                                             (($item['status'] === 'Low Stock') ? 'warning' : 'success');
                                    ?>"><?php echo $item['status']; ?></span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="edit_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                                            <a href="delete_item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this item?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
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
