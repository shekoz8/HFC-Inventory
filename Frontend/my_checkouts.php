<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/item_checkout.php';

// Check if user is logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    header("Location: /hfc_inventory/Frontend/index.php?error=session_invalid");
    exit;
}

$userId = $_SESSION['user']['id'];

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Process checkout (receive item)
        if ($_POST['action'] === 'checkout' && isset($_POST['checkout_id'])) {
            $checkoutId = intval($_POST['checkout_id']);
            $expectedReturnDate = !empty($_POST['expected_return_date']) ? $_POST['expected_return_date'] : null;
            
            if (processCheckout($checkoutId, $userId, $expectedReturnDate)) {
                header("Location: /hfc_inventory/Frontend/my_checkouts.php?success=item_received");
                exit;
            } else {
                $error = "Failed to process checkout. Please try again.";
            }
        }
        
        // Process return
        if ($_POST['action'] === 'return' && isset($_POST['checkout_id']) && isset($_POST['return_quantity'])) {
            $checkoutId = intval($_POST['checkout_id']);
            $returnQuantity = intval($_POST['return_quantity']);
            $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
            
            if (processReturn($checkoutId, $userId, $returnQuantity, $notes)) {
                header("Location: /hfc_inventory/Frontend/my_checkouts.php?success=item_returned");
                exit;
            } else {
                $error = "Failed to process return. Please try again.";
            }
        }
    }
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;

// Status filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$validStatuses = ['all', 'Pending', 'Checked Out', 'Returned', 'Partially Returned'];
if (!in_array($statusFilter, $validStatuses)) {
    $statusFilter = 'all';
}

// Get checkouts with pagination
$checkouts = getUserCheckouts($userId, $statusFilter, $page, $perPage);
$totalCheckouts = countUserCheckouts($userId, $statusFilter);
$totalPages = ceil($totalCheckouts / $perPage);

// Get pending checkouts for the user (items that need to be received)
$pendingCheckouts = getUserCheckouts($userId, 'Pending', 1, 100); // Get up to 100 pending checkouts

// Get checked out items (items that need to be returned)
$checkedOutItems = getUserCheckouts($userId, 'Checked Out', 1, 100); // Get up to 100 checked out items
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Checkouts - HFC Inventory</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .badge {
            font-size: 0.9em;
        }
        .table-responsive {
            max-height: 600px;
        }
        .action-buttons .btn {
            margin-right: 5px;
        }
    </style>
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
                    case 'item_received':
                        $success = 'You have successfully received the requested item!';
                        break;
                    case 'item_returned':
                        $success = 'You have successfully returned the item!';
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
            <h2><i class="bi bi-box-arrow-in-right"></i> My Checkouts</h2>
            <div>
                <a href="/hfc_inventory/Frontend/my_requests.php" class="btn btn-outline-primary me-2">
                    <i class="bi bi-card-checklist"></i> My Requests
                </a>
                <a href="/hfc_inventory/Frontend/inventory.php" class="btn btn-primary">
                    <i class="bi bi-box-seam"></i> Back to Inventory
                </a>
            </div>
        </div>

        <!-- Pending Items Section -->
        <?php if (!empty($pendingCheckouts)): ?>
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-exclamation-circle"></i> Items Ready for Pickup</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Request Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingCheckouts as $checkout): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($checkout['item_name']); ?></td>
                                <td><?php echo $checkout['quantity']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($checkout['created_at'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" 
                                            data-bs-target="#checkoutModal<?php echo $checkout['id']; ?>">
                                        <i class="bi bi-box-arrow-in-down"></i> Receive Item
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Checked Out Items Section -->
        <?php if (!empty($checkedOutItems)): ?>
        <div class="card mb-4 border-info">
            <div class="card-header bg-info text-dark">
                <h5 class="mb-0"><i class="bi bi-arrow-return-left"></i> Items to Return</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Checkout Date</th>
                                <th>Expected Return</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checkedOutItems as $checkout): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($checkout['item_name']); ?></td>
                                <td><?php echo $checkout['quantity']; ?></td>
                                <td><?php echo $checkout['formatted_checkout_date']; ?></td>
                                <td>
                                    <?php if (!empty($checkout['formatted_expected_return_date'])): ?>
                                        <?php echo $checkout['formatted_expected_return_date']; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not specified</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                            data-bs-target="#returnModal<?php echo $checkout['id']; ?>">
                                        <i class="bi bi-arrow-return-left"></i> Return Item
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Status Filter -->
        <div class="mb-4">
            <div class="btn-group" role="group" aria-label="Status filter">
                <a href="?status=all" class="btn btn-outline-secondary <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
                    All Items
                </a>
                <a href="?status=Pending" class="btn btn-outline-warning <?php echo $statusFilter === 'Pending' ? 'active' : ''; ?>">
                    Pending
                </a>
                <a href="?status=Checked Out" class="btn btn-outline-info <?php echo $statusFilter === 'Checked Out' ? 'active' : ''; ?>">
                    Checked Out
                </a>
                <a href="?status=Partially Returned" class="btn btn-outline-primary <?php echo $statusFilter === 'Partially Returned' ? 'active' : ''; ?>">
                    Partially Returned
                </a>
                <a href="?status=Returned" class="btn btn-outline-success <?php echo $statusFilter === 'Returned' ? 'active' : ''; ?>">
                    Returned
                </a>
            </div>
        </div>
        
        <!-- All Checkouts Table -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="bi bi-list-check"></i> All Checkouts History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Checkout Date</th>
                                <th>Return Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($checkouts)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No checkout records found</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($checkouts as $checkout): ?>
                                <tr>
                                    <td><?php echo $checkout['id']; ?></td>
                                    <td><?php echo htmlspecialchars($checkout['item_name']); ?></td>
                                    <td><?php echo $checkout['quantity']; ?></td>
                                    <td>
                                        <?php if ($checkout['status'] === 'Pending'): ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php elseif ($checkout['status'] === 'Checked Out'): ?>
                                            <span class="badge bg-info text-dark">Checked Out</span>
                                        <?php elseif ($checkout['status'] === 'Partially Returned'): ?>
                                            <span class="badge bg-primary">Partially Returned</span>
                                        <?php elseif ($checkout['status'] === 'Returned'): ?>
                                            <span class="badge bg-success">Returned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($checkout['checkout_date'])): ?>
                                            <?php echo date('M d, Y', strtotime($checkout['checkout_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($checkout['actual_return_date'])): ?>
                                            <?php echo date('M d, Y', strtotime($checkout['actual_return_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                                data-bs-target="#detailsModal<?php echo $checkout['id']; ?>">
                                            <i class="bi bi-info-circle"></i> Details
                                        </button>
                                        
                                        <?php if ($checkout['status'] === 'Pending'): ?>
                                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" 
                                                data-bs-target="#checkoutModal<?php echo $checkout['id']; ?>">
                                            <i class="bi bi-box-arrow-in-down"></i> Receive
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($checkout['status'] === 'Checked Out' || $checkout['status'] === 'Partially Returned'): ?>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                                data-bs-target="#returnModal<?php echo $checkout['id']; ?>">
                                            <i class="bi bi-arrow-return-left"></i> Return
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Checkouts pagination" class="mt-4">
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
    
    <!-- Checkout (Receive) Modals -->
    <?php foreach ($checkouts as $checkout): ?>
        <?php if ($checkout['status'] === 'Pending'): ?>
        <div class="modal fade" id="checkoutModal<?php echo $checkout['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Receive Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="" method="POST">
                        <div class="modal-body">
                            <p>You are about to receive the following item:</p>
                            <ul class="list-group mb-3">
                                <li class="list-group-item"><strong>Item:</strong> <?php echo htmlspecialchars($checkout['item_name']); ?></li>
                                <li class="list-group-item"><strong>Quantity:</strong> <?php echo $checkout['quantity']; ?></li>
                                <li class="list-group-item"><strong>Category:</strong> <?php echo htmlspecialchars($checkout['category']); ?></li>
                            </ul>
                            
                            <div class="mb-3">
                                <label for="expected_return_date<?php echo $checkout['id']; ?>" class="form-label">Expected Return Date (Optional)</label>
                                <input type="date" class="form-control" id="expected_return_date<?php echo $checkout['id']; ?>" name="expected_return_date">
                                <div class="form-text">If you know when you'll return this item, please specify a date.</div>
                            </div>
                            
                            <input type="hidden" name="checkout_id" value="<?php echo $checkout['id']; ?>">
                            <input type="hidden" name="action" value="checkout">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Confirm Receipt</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
    
    <!-- Return Modals -->
    <?php foreach ($checkouts as $checkout): ?>
        <?php if ($checkout['status'] === 'Checked Out' || $checkout['status'] === 'Partially Returned'): ?>
        <div class="modal fade" id="returnModal<?php echo $checkout['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Return Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="" method="POST">
                        <div class="modal-body">
                            <p>You are about to return the following item:</p>
                            <ul class="list-group mb-3">
                                <li class="list-group-item"><strong>Item:</strong> <?php echo htmlspecialchars($checkout['item_name']); ?></li>
                                <li class="list-group-item"><strong>Quantity Checked Out:</strong> <?php echo $checkout['quantity']; ?></li>
                                <li class="list-group-item"><strong>Category:</strong> <?php echo htmlspecialchars($checkout['category']); ?></li>
                                <?php if (!empty($checkout['checkout_date'])): ?>
                                <li class="list-group-item"><strong>Checked Out On:</strong> <?php echo date('M d, Y', strtotime($checkout['checkout_date'])); ?></li>
                                <?php endif; ?>
                            </ul>
                            
                            <div class="mb-3">
                                <label for="return_quantity<?php echo $checkout['id']; ?>" class="form-label">Quantity to Return</label>
                                <input type="number" class="form-control" id="return_quantity<?php echo $checkout['id']; ?>" name="return_quantity" min="1" max="<?php echo $checkout['quantity']; ?>" value="<?php echo $checkout['quantity']; ?>" required>
                                <div class="form-text">Specify how many items you are returning (max: <?php echo $checkout['quantity']; ?>)</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes<?php echo $checkout['id']; ?>" class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" id="notes<?php echo $checkout['id']; ?>" name="notes" rows="3" placeholder="Any notes about the condition of the returned items"></textarea>
                            </div>
                            
                            <input type="hidden" name="checkout_id" value="<?php echo $checkout['id']; ?>">
                            <input type="hidden" name="action" value="return">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Confirm Return</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
    
    <!-- Details Modals -->
    <?php foreach ($checkouts as $checkout): ?>
    <div class="modal fade" id="detailsModal<?php echo $checkout['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Checkout Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="list-group mb-3">
                        <li class="list-group-item"><strong>Item:</strong> <?php echo htmlspecialchars($checkout['item_name']); ?></li>
                        <li class="list-group-item"><strong>Quantity:</strong> <?php echo $checkout['quantity']; ?></li>
                        <li class="list-group-item"><strong>Category:</strong> <?php echo htmlspecialchars($checkout['category']); ?></li>
                        <li class="list-group-item"><strong>Status:</strong> 
                            <?php if ($checkout['status'] === 'Pending'): ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php elseif ($checkout['status'] === 'Checked Out'): ?>
                                <span class="badge bg-info text-dark">Checked Out</span>
                            <?php elseif ($checkout['status'] === 'Partially Returned'): ?>
                                <span class="badge bg-primary">Partially Returned</span>
                            <?php elseif ($checkout['status'] === 'Returned'): ?>
                                <span class="badge bg-success">Returned</span>
                            <?php endif; ?>
                        </li>
                        <li class="list-group-item"><strong>Request Status:</strong> 
                            <?php if ($checkout['request_status'] === 'Pending'): ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php elseif ($checkout['request_status'] === 'Approved'): ?>
                                <span class="badge bg-success">Approved</span>
                            <?php elseif ($checkout['request_status'] === 'Rejected'): ?>
                                <span class="badge bg-danger">Rejected</span>
                            <?php endif; ?>
                        </li>
                        <li class="list-group-item"><strong>Created On:</strong> <?php echo date('F d, Y h:i A', strtotime($checkout['created_at'])); ?></li>
                        
                        <?php if (!empty($checkout['checkout_date'])): ?>
                        <li class="list-group-item"><strong>Checked Out On:</strong> <?php echo date('F d, Y h:i A', strtotime($checkout['checkout_date'])); ?></li>
                        <?php endif; ?>
                        
                        <?php if (!empty($checkout['expected_return_date'])): ?>
                        <li class="list-group-item"><strong>Expected Return:</strong> <?php echo date('F d, Y', strtotime($checkout['expected_return_date'])); ?></li>
                        <?php endif; ?>
                        
                        <?php if (!empty($checkout['actual_return_date'])): ?>
                        <li class="list-group-item"><strong>Returned On:</strong> <?php echo date('F d, Y h:i A', strtotime($checkout['actual_return_date'])); ?></li>
                        <?php endif; ?>
                        
                        <?php if (!empty($checkout['notes'])): ?>
                        <li class="list-group-item"><strong>Notes:</strong> <pre class="mt-2"><?php echo htmlspecialchars($checkout['notes']); ?></pre></li>
                        <?php endif; ?>
                    </ul>
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
