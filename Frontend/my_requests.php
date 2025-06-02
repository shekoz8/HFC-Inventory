<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    header("Location: /hfc_inventory/Frontend/index.php?error=session_invalid");
    exit;
}

$userId = $_SESSION['user']['id'];

// Filter by status if specified
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$validStatuses = ['all', 'Pending', 'Approved', 'Rejected'];
if (!in_array($statusFilter, $validStatuses)) {
    $statusFilter = 'all';
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    // Count total requests for pagination
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM item_requests r
        WHERE r.user_id = ?
    ";
    
    if ($statusFilter !== 'all') {
        $countQuery .= " AND r.status = ?";
    }
    
    $countStmt = $conn->prepare($countQuery);
    
    if ($statusFilter !== 'all') {
        $countStmt->bind_param("is", $userId, $statusFilter);
    } else {
        $countStmt->bind_param("i", $userId);
    }
    
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRequests = $countResult->fetch_assoc()['total'];
    $totalPages = ceil($totalRequests / $perPage);
    
    // Get requests with details
    $query = "
        SELECT r.*, i.name as item_name, i.quantity as current_quantity
        FROM item_requests r
        JOIN inventory_items i ON r.item_id = i.id
        WHERE r.user_id = ?
    ";
    
    if ($statusFilter !== 'all') {
        $query .= " AND r.status = ?";
    }
    
    $query .= " ORDER BY r.requested_at DESC LIMIT ?, ?";
    
    $stmt = $conn->prepare($query);
    
    if ($statusFilter !== 'all') {
        $stmt->bind_param("isii", $userId, $statusFilter, $offset, $perPage);
    } else {
        $stmt->bind_param("iii", $userId, $offset, $perPage);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $requests = [];
    
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $requests = [];
    $totalPages = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>My Requests | HFC Inventory</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css" />
</head>
<body>
    <!-- Include Navbar -->
    <?php include "partials/navbar.php"; ?>

    <div class="container py-4">
        <!-- Alert Messages -->
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
                $success = '';
                switch($_GET['success']) {
                    case 'request_submitted':
                        $success = 'Your request has been submitted successfully!';
                        break;
                    default:
                        $success = 'Operation completed successfully!';
                }
                echo $success;
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-card-checklist"></i> My Requests</h2>
            <div>
                <a href="/hfc_inventory/Frontend/my_checkouts.php" class="btn btn-info me-2">
                    <i class="bi bi-box-arrow-in-right"></i> My Checkouts
                </a>
                <a href="/hfc_inventory/Frontend/inventory.php" class="btn btn-primary">
                    <i class="bi bi-box-seam"></i> Back to Inventory
                </a>
            </div>
        </div>
        
        <!-- Workflow Info Alert -->
        <?php if (isset($_GET['status']) && $_GET['status'] === 'Approved'): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <h5><i class="bi bi-info-circle"></i> What's Next?</h5>
            <p>Your approved requests are ready for pickup. Please go to <a href="/hfc_inventory/Frontend/my_checkouts.php" class="alert-link">My Checkouts</a> to:</p>
            <ol>
                <li>Receive your approved items</li>
                <li>Return items when you're done with them</li>
            </ol>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Status Filter -->
        <div class="mb-4">
            <div class="btn-group" role="group" aria-label="Status filter">
                <a href="?status=all" class="btn btn-outline-secondary <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
                    All Requests
                </a>
                <a href="?status=Pending" class="btn btn-outline-warning <?php echo $statusFilter === 'Pending' ? 'active' : ''; ?>">
                    Pending
                </a>
                <a href="?status=Approved" class="btn btn-outline-success <?php echo $statusFilter === 'Approved' ? 'active' : ''; ?>">
                    Approved
                </a>
                <a href="?status=Rejected" class="btn btn-outline-danger <?php echo $statusFilter === 'Rejected' ? 'active' : ''; ?>">
                    Rejected
                </a>
            </div>
        </div>
        
        <!-- Requests Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Requested On</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="7" class="text-center">No requests found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php echo $request['id']; ?></td>
                            <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                            <td><?php echo $request['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($request['reason']); ?></td>
                            <td>
                                <?php if ($request['status'] === 'Pending'): ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php elseif ($request['status'] === 'Approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                    <a href="/hfc_inventory/Frontend/my_checkouts.php" class="badge bg-info text-decoration-none ms-1">Ready for Pickup</a>
                                <?php elseif ($request['status'] === 'Rejected'): ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($request['requested_at'])); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                        data-bs-target="#detailsModal<?php echo $request['id']; ?>">
                                    <i class="bi bi-info-circle"></i> Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Requests pagination" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    
    <!-- Details Modals - One for each request -->
    <?php foreach ($requests as $request): ?>
    <div class="modal fade" id="detailsModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="list-group mb-3">
                        <li class="list-group-item"><strong>Item:</strong> <?php echo htmlspecialchars($request['item_name']); ?></li>
                        <li class="list-group-item"><strong>Quantity Requested:</strong> <?php echo $request['quantity']; ?></li>
                        <li class="list-group-item"><strong>Current Item Quantity:</strong> <?php echo $request['current_quantity']; ?></li>
                        <li class="list-group-item"><strong>Reason:</strong> <?php echo htmlspecialchars($request['reason']); ?></li>
                        <li class="list-group-item"><strong>Status:</strong> 
                            <?php if ($request['status'] === 'Pending'): ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php elseif ($request['status'] === 'Approved'): ?>
                                <span class="badge bg-success">Approved</span>
                                <a href="/hfc_inventory/Frontend/my_checkouts.php" class="badge bg-info text-decoration-none ms-1">Ready for Pickup</a>
                            <?php elseif ($request['status'] === 'Rejected'): ?>
                                <span class="badge bg-danger">Rejected</span>
                            <?php endif; ?>
                        </li>
                        <li class="list-group-item"><strong>Requested On:</strong> <?php echo date('F d, Y h:i A', strtotime($request['requested_at'])); ?></li>
                    </ul>
                    <?php if ($request['status'] === 'Approved'): ?>
                        <a href="/hfc_inventory/Frontend/my_checkouts.php" class="btn btn-info">Go to Checkouts</a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Bootstrap Bundle (includes Popper for modals) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
