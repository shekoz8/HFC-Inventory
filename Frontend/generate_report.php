<?php
session_start();
require_once '../includes/db.php';
require_once __DIR__ . '/../libraries/tcpdf/inventory_report.php';

// Redirect if user not logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    header("Location: ../Frontend/index.php?error=session_invalid");
    exit;
}

$items = [];
$report_type = $_POST['report_type'] ?? 'all';
$category_filter = $_POST['category_filter'] ?? 'all';

// Fetch categories for the dropdown
$category_sql = "SELECT name FROM categories ORDER BY name ASC";
$category_result = $conn->query($category_sql);
$categories = $category_result ? $category_result->fetch_all(MYSQLI_ASSOC) : [];

function buildQuery(&$params, $report_type, $category_filter) {
    $query = "SELECT i.*, c.name AS category_name,
                CASE 
                    WHEN i.quantity <= 0 THEN 'Out of Stock'
                    WHEN i.quantity <= i.min_quantity THEN 'Low Stock'
                    ELSE 'In Stock'
                END AS status
            FROM inventory_items i
            LEFT JOIN categories c ON i.category_id = c.id";

    $where_clauses = [];

    if ($report_type === 'low_stock') {
        $where_clauses[] = "i.quantity <= i.min_quantity";
    } elseif ($report_type === 'out_of_stock') {
        $where_clauses[] = "i.quantity <= 0";
    }

    if ($category_filter !== 'all') {
        $where_clauses[] = "c.name = ?";
        $params[] = $category_filter;
    }

    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $query .= " ORDER BY i.name ASC";
    return $query;
}

try {
    $params = [];
    $query = buildQuery($params, $report_type, $category_filter);
    $stmt = $conn->prepare($query);
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    if (!empty($params)) {
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading data: " . $e->getMessage();
}

// Generate PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    try {
        $params = [];
        $query = buildQuery($params, $report_type, $category_filter);
        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        if (!empty($params)) {
            $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $pdf_items = $result->fetch_all(MYSQLI_ASSOC);

        $pdf = new InventoryReport('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('HFC Inventory System');
        $pdf->SetAuthor('HFC Management');
        $pdf->SetTitle('HFC Inventory Report');
        $pdf->SetHeaderData('', 0, '', '');
        $pdf->SetMargins(15, 30, 15);
        $pdf->SetAutoPageBreak(TRUE, 25);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->AddPage();
        $pdf->AddTable($pdf_items);

        $filename = "inventory_report_" . date('Y-m-d_H-i-s') . ".pdf";
        $pdf->Output($filename, 'D');
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Report generation failed: " . $e->getMessage();
        header("Location: generate_report.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Report | HFC Inventory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
       .table-scroll {
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
}

.table-scroll table {
    width: 100%;
    border-collapse: collapse;
}

.table-scroll thead th {
    position: sticky;
    top: 0;
    background-color: var(--hfc-blue); /* or #343a40 */
    color: #fff;
    z-index: 2;
}

    </style>
</head>
<body>
<?php include "partials/navbar.php"; ?>

<div class="container py-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <i class="bi bi-printer me-2 fs-4"></i>
                <h4 class="mb-0 fw-bold">Generate Inventory Report</h4>
            </div>
        </div>
        <div class="card-body">
            <!-- Alerts -->
            <?php foreach (['success', 'error'] as $type): ?>
                <?php if (isset($_SESSION[$type])): ?>
                    <div class="alert alert-<?= $type === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show" role="alert">
                        <?= $_SESSION[$type]; unset($_SESSION[$type]); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- Filter Form -->
            <form method="POST" class="mb-4">
                <div class="mb-3">
                    <label class="form-label">Report Type</label>
                    <select name="report_type" class="form-select">
                        <option value="all" <?= $report_type === 'all' ? 'selected' : '' ?>>All Items</option>
                        <option value="low_stock" <?= $report_type === 'low_stock' ? 'selected' : '' ?>>Low Stock Items</option>
                        <option value="out_of_stock" <?= $report_type === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock Items</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Category Filter</label>
                    <select name="category_filter" class="form-select">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category['name']) ?>" <?= $category_filter === $category['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="generate_report" class="btn btn-primary">
                    <i class="bi bi-download me-2"></i>Download PDF Report
                </button>
            </form>

            <!-- Report Preview -->
            <div class="card">
                <div class="card-header d-flex justify-content-between bg-light">
                    <h5 class="mb-0">Report Preview</h5>
                    <div>
                        <span class="badge bg-primary">Total: <?= count($items) ?></span>
                        <span class="badge bg-success">In Stock: <?= count(array_filter($items, fn($i) => $i['status'] === 'In Stock')) ?></span>
                        <span class="badge bg-warning">Low: <?= count(array_filter($items, fn($i) => $i['status'] === 'Low Stock')) ?></span>
                        <span class="badge bg-danger">Out: <?= count(array_filter($items, fn($i) => $i['status'] === 'Out of Stock')) ?></span>
                    </div>
                </div>
                <div class="table-responsive">
                <div class="table-scroll">
                    <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="0" class="text-center py-4">
                                            <div class="alert alert-info">
                                                <i class="bi bi-info-circle me-2"></i>No items found in your inventory
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo ($item['status'] === 'Out of Stock') ? 'danger' : 
                                                     (($item['status'] === 'Low Stock') ? 'warning' : 'success');
                                            ?>"><?php echo $item['status']; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-0"><strong>Report Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $report_type)); ?></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <p class="mb-0"><strong>Generated:</strong> <?php echo date('F j, Y H:i'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
