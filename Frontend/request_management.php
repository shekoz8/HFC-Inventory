<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/item_checkout.php';

// Check if user is logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    header("Location: /hfc_inventory/Frontend/index.php?error=session_invalid");
    exit;
}

// Role-based access check - ONLY ADMIN CAN ACCESS THIS PAGE
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: /hfc_inventory/Frontend/unauthorized.php");
    exit;
}

// Process request actions (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $requestId = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $userId = $_SESSION['user']['id'];
    
    if ($_POST['action'] === 'approve' && $requestId > 0) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Get request details
            $stmt = $conn->prepare("
                SELECT r.*, i.name as item_name, i.quantity as available_quantity, u.name as requester_name 
                FROM item_requests r
                JOIN inventory_items i ON r.item_id = i.id
                JOIN users u ON r.user_id = u.id
                WHERE r.id = ?
            ");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Request not found");
            }
            
            $request = $result->fetch_assoc();
            
            // Check if there's enough quantity
            if ($request['available_quantity'] < $request['quantity']) {
                throw new Exception("Not enough quantity available");
            }
            
            // Update request status
            $status = 'Approved';
            $stmt = $conn->prepare("
                UPDATE item_requests 
                SET status = ?, approved_by = ?, fulfilled_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("sii", $status, $userId, $requestId);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0) {
                throw new Exception("Failed to update request status");
            }
            
            // Update inventory quantity
            $newQuantity = $request['available_quantity'] - $request['quantity'];
            
            // Determine new status based on quantity and min_quantity
            $stmtItem = $conn->prepare("SELECT min_quantity FROM inventory_items WHERE id = ?");
            $stmtItem->bind_param("i", $request['item_id']);
            $stmtItem->execute();
            $resultItem = $stmtItem->get_result();
            $item = $resultItem->fetch_assoc();
            
            $status = 'In Stock';
            if ($newQuantity <= 0) {
                $status = 'Out of Stock';
            } elseif ($newQuantity <= $item['min_quantity']) {
                $status = 'Low Stock';
            }
            
            $stmt = $conn->prepare("
                UPDATE inventory_items 
                SET quantity = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("isi", $newQuantity, $status, $request['item_id']);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0) {
                throw new Exception("Failed to update inventory quantity");
            }
            
            // Log the activity
            $logDetails = "Approved request #{$requestId}: {$request['quantity']} of {$request['item_name']} for {$request['requester_name']}";
            $stmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action_type, action_details, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $actionType = 'approve_request';
            $stmt->bind_param("iss", $userId, $actionType, $logDetails);
            $stmt->execute();
            
            // Create inventory transaction record
            $stmt = $conn->prepare("
                INSERT INTO inventory_transactions 
                (item_id, user_id, quantity, transaction_type, notes, transaction_date)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $transactionType = 'request_approval';
            $notes = "Approved request #{$requestId}";
            $stmt->bind_param("iiiss", $request['item_id'], $userId, $request['quantity'], $transactionType, $notes);
            $stmt->execute();
            
            // Create checkout record for the user to receive the item
            createCheckout($requestId, $request['user_id'], $request['item_id'], $request['quantity'], "Created from approved request #{$requestId}");
            
            // Commit the transaction
            $conn->commit();
            
            $success = "Request #{$requestId} approved successfully";
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'reject' && $requestId > 0) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Get request details for logging
            $stmt = $conn->prepare("
                SELECT r.*, i.name as item_name, u.name as requester_name 
                FROM item_requests r
                JOIN inventory_items i ON r.item_id = i.id
                JOIN users u ON r.user_id = u.id
                WHERE r.id = ?
            ");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("Request not found");
            }
            
            $request = $result->fetch_assoc();
            
            // Update request status
            $status = 'Rejected';
            $rejectReason = isset($_POST['reject_reason']) ? $_POST['reject_reason'] : '';
            
            $stmt = $conn->prepare("
                UPDATE item_requests 
                SET status = ?, reject_reason = ?, rejected_by = ?, rejected_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("ssii", $status, $rejectReason, $userId, $requestId);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0) {
                throw new Exception("Failed to update request status");
            }
            
            // Log the activity
            $logDetails = "Rejected request #{$requestId}: {$request['quantity']} of {$request['item_name']} for {$request['requester_name']}";
            if (!empty($rejectReason)) {
                $logDetails .= " - Reason: {$rejectReason}";
            }
            
            $stmt = $conn->prepare("
                INSERT INTO activity_logs (user_id, action_type, action_details, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $actionType = 'reject_request';
            $stmt->bind_param("iss", $userId, $actionType, $logDetails);
            $stmt->execute();
            
            // Commit the transaction
            $conn->commit();
            
            $success = "Request #{$requestId} rejected successfully";
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch requests with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Filter by status if specified
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$validStatuses = ['all', 'Pending', 'Approved', 'Rejected'];
if (!in_array($statusFilter, $validStatuses)) {
    $statusFilter = 'all';
}

try {
    // Count total requests for pagination
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM item_requests r
    ";
    
    if ($statusFilter !== 'all') {
        $countQuery .= " WHERE r.status = ?";
    }
    
    $countStmt = $conn->prepare($countQuery);
    
    if ($statusFilter !== 'all') {
        $countStmt->bind_param("s", $statusFilter);
    }
    
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRequests = $countResult->fetch_assoc()['total'];
    $totalPages = ceil($totalRequests / $perPage);
    
    // Get requests with details
    $query = "
        SELECT r.*, 
               i.name as item_name, 
               i.quantity as available_quantity, 
               u.name as requester_name,
               ua.name as approver_name,
               ur.name as rejecter_name
        FROM item_requests r
        JOIN inventory_items i ON r.item_id = i.id
        JOIN users u ON r.user_id = u.id
        LEFT JOIN users ua ON r.approved_by = ua.id
        LEFT JOIN users ur ON r.rejected_by = ur.id
    ";
    
    if ($statusFilter !== 'all') {
        $query .= " WHERE r.status = ?";
    }
    
    $query .= " ORDER BY r.requested_at DESC LIMIT ?, ?";
    
    $stmt = $conn->prepare($query);
    
    if ($statusFilter !== 'all') {
        $stmt->bind_param("sii", $statusFilter, $offset, $perPage);
    } else {
        $stmt->bind_param("ii", $offset, $perPage);
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
    <title>Request Management | HFC Inventory</title>

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
        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
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
            <h2><i class="bi bi-clipboard-check"></i> Request Management</h2>
            <a href="/hfc_inventory/Frontend/inventory.php" class="btn btn-primary">
                <i class="bi bi-box-seam"></i> Back to Inventory
            </a>
        </div>
        
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
                        <th>Requester</th>
                        <th>Quantity</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Requested At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="8" class="text-center">No requests found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php echo $request['id']; ?></td>
                            <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['requester_name']); ?></td>
                            <td><?php echo $request['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($request['reason']); ?></td>
                            <td>
                                <?php if ($request['status'] === 'Pending'): ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                <?php elseif ($request['status'] === 'Approved'): ?>
                                    <span class="badge bg-success">Approved by <?php echo htmlspecialchars($request['approver_name']); ?></span>
                                <?php elseif ($request['status'] === 'Rejected'): ?>
                                    <span class="badge bg-danger">Rejected by <?php echo htmlspecialchars($request['rejecter_name']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($request['requested_at'])); ?></td>
                            <td>
                                <?php if ($request['status'] === 'Pending'): ?>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" 
                                            data-bs-target="#approveModal<?php echo $request['id']; ?>">
                                        <i class="bi bi-check-lg"></i> Approve
                                    </button>
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" 
                                            data-bs-target="#rejectModal<?php echo $request['id']; ?>">
                                        <i class="bi bi-x-lg"></i> Reject
                                    </button>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">No actions available</span>
                                <?php endif; ?>
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
    
    <!-- Approve Modals - One for each pending request -->
    <?php foreach ($requests as $request): ?>
    <?php if ($request['status'] === 'Pending'): ?>
    <div class="modal fade" id="approveModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to approve this request?</p>
                    <ul class="list-group mb-3">
                        <li class="list-group-item"><strong>Item:</strong> <?php echo htmlspecialchars($request['item_name']); ?></li>
                        <li class="list-group-item"><strong>Requester:</strong> <?php echo htmlspecialchars($request['requester_name']); ?></li>
                        <li class="list-group-item"><strong>Quantity:</strong> <?php echo $request['quantity']; ?></li>
                        <li class="list-group-item"><strong>Reason:</strong> <?php echo htmlspecialchars($request['reason']); ?></li>
                    </ul>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                        <button type="submit" class="btn btn-success">Confirm Approval</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
    
    <!-- Reject Modals - One for each pending request -->
    <?php foreach ($requests as $request): ?>
    <?php if ($request['status'] === 'Pending'): ?>
    <div class="modal fade" id="rejectModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to reject this request?</p>
                    <ul class="list-group mb-3">
                        <li class="list-group-item"><strong>Item:</strong> <?php echo htmlspecialchars($request['item_name']); ?></li>
                        <li class="list-group-item"><strong>Requester:</strong> <?php echo htmlspecialchars($request['requester_name']); ?></li>
                        <li class="list-group-item"><strong>Quantity:</strong> <?php echo $request['quantity']; ?></li>
                        <li class="list-group-item"><strong>Reason:</strong> <?php echo htmlspecialchars($request['reason']); ?></li>
                    </ul>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                        <div class="mb-3">
                            <label for="rejectReason<?php echo $request['id']; ?>" class="form-label">Rejection Reason</label>
                            <textarea class="form-control" id="rejectReason<?php echo $request['id']; ?>" 
                                      name="reject_reason" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
    
    <!-- Bootstrap Bundle (includes Popper for modals) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
