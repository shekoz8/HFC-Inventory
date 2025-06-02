<?php
session_start();
require_once '../includes/db.php';

// Debugging session data
echo '<pre>';
// print_r($_SESSION);  \
echo '</pre>';

// Check if user is logged in
if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    header("Location: /hfc_inventory/Frontend/index.php?error=session_invalid");
    exit;
}

// Role-based access check
$allowed_roles = ['admin', 'clerk'];
if (!in_array($_SESSION['user']['role'], $allowed_roles)) {
    header("Location: /hfc_inventory/Frontend/unauthorized.php");
    exit;
}

// Fetch categories from database
$categories = [];
try {
    $stmt = $conn->prepare("SELECT * FROM categories ORDER BY name ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Fetch inventory items from database
$inventoryItems = [];
$filterCategory = isset($_GET['category']) ? $_GET['category'] : 'all';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

try {
    $query = "
        SELECT i.*, c.name as category_name 
        FROM inventory_items i
        JOIN categories c ON i.category_id = c.id
    ";
    
    // Apply filters if set
    $whereConditions = [];
    $params = [];
    $types = "";
    
    if ($filterCategory !== 'all') {
        $whereConditions[] = "c.name = ?";
        $params[] = $filterCategory;
        $types .= "s";
    }
    
    if (!empty($searchQuery)) {
        $whereConditions[] = "(i.name LIKE ? OR c.name LIKE ?)";
        $params[] = "%$searchQuery%";
        $params[] = "%$searchQuery%";
        $types .= "ss";
    }
    
    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $query .= " ORDER BY i.id ASC";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $inventoryItems[] = $row;
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle Add Item Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_item') {
    $name = $_POST['itemName'];
    $category_id = $_POST['itemCategory'];
    $quantity = $_POST['itemQuantity'];
    $min_quantity = isset($_POST['itemMinQuantity']) ? $_POST['itemMinQuantity'] : 5;
    $description = isset($_POST['itemDescription']) ? $_POST['itemDescription'] : '';
    
    // Determine status based on quantity
    $status = 'In Stock';
    if ($quantity <= 0) {
        $status = 'Out of Stock';
    } elseif ($quantity <= $min_quantity) {
        $status = 'Low Stock';
    }
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO inventory_items 
            (name, category_id, quantity, min_quantity, status, description, created_by, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $userId = $_SESSION['user']['id'];
        $stmt->bind_param("siiissi", $name, $category_id, $quantity, $min_quantity, $status, $description, $userId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $success = "Item added successfully!";
            // Log the activity
            logActivity($conn, 'add', "Added new item: $name", $userId);
            
            // Redirect to prevent form resubmission
            header("Location: /hfc_inventory/Frontend/inventory.php?success=item_added");
            exit;
        } else {
            $error = "Failed to add item";
        }
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle Update Item Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_item') {
    $id = $_POST['itemId'];
    $name = $_POST['itemName'];
    $category_id = $_POST['itemCategory'];
    $quantity = $_POST['itemQuantity'];
    $min_quantity = isset($_POST['itemMinQuantity']) ? $_POST['itemMinQuantity'] : 5;
    $description = isset($_POST['itemDescription']) ? $_POST['itemDescription'] : '';
    
    // Determine status based on quantity
    $status = 'In Stock';
    if ($quantity <= 0) {
        $status = 'Out of Stock';
    } elseif ($quantity <= $min_quantity) {
        $status = 'Low Stock';
    }
    
    try {
        $stmt = $conn->prepare("
            UPDATE inventory_items 
            SET name = ?, 
                category_id = ?, 
                quantity = ?, 
                min_quantity = ?, 
                status = ?,
                description = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $userId = $_SESSION['user']['id'];
        $stmt->bind_param("siiissi", $name, $category_id, $quantity, $min_quantity, $status, $description, $id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $success = "Item updated successfully!";
            // Log the activity
            logActivity($conn, 'update', "Updated item: $name (ID: $id)", $userId);
            
            // Redirect to prevent form resubmission
            header("Location: /hfc_inventory/Frontend/inventory.php?success=item_updated");
            exit;
        } else {
            $error = "No changes made or item not found";
        }
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle Delete Item
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    try {
        // First get the item name for logging
        $stmtGet = $conn->prepare("SELECT name FROM inventory_items WHERE id = ?");
        $stmtGet->bind_param("i", $id);
        $stmtGet->execute();
        $resultGet = $stmtGet->get_result();
        $item = $resultGet->fetch_assoc();
        
        // Then delete the item
        $stmt = $conn->prepare("DELETE FROM inventory_items WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $success = "Item deleted successfully!";
            // Log the activity
            $userId = $_SESSION['user']['id'];
            logActivity($conn, 'delete', "Deleted item: {$item['name']} (ID: $id)", $userId);
            
            // Redirect
            header("Location: /hfc_inventory/Frontend/inventory.php?success=item_deleted");
            exit;
        } else {
            $error = "Item not found or could not be deleted";
        }
    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle Request Item Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_item') {
    $item_id = $_POST['itemId'];
    $quantity = $_POST['requestQuantity'];
    $reason = $_POST['requestReason'];
    $user_id = $_SESSION['user']['id'];
    
    try {
        // First check if the item exists and has enough quantity
        $stmtCheck = $conn->prepare("SELECT name, quantity FROM inventory_items WHERE id = ?");
        $stmtCheck->bind_param("i", $item_id);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        
        if ($resultCheck->num_rows === 0) {
            $error = "Item not found";
            // Log the error
            logActivity($conn, 'error', "Request error: Item not found (ID: $item_id)", $user_id);
            header("Location: /hfc_inventory/Frontend/inventory.php?error=item_not_found");
            exit;
        }
        
        $item = $resultCheck->fetch_assoc();
        
        if ($item['quantity'] < $quantity) {
            $error = "Not enough quantity available";
            // Log the error
            logActivity($conn, 'error', "Request error: Insufficient quantity for {$item['name']} (ID: $item_id) - Requested: $quantity, Available: {$item['quantity']}", $user_id);
            header("Location: /hfc_inventory/Frontend/inventory.php?error=insufficient_quantity");
            exit;
        }
        
        // Begin transaction
        $conn->begin_transaction();
        
        // Insert the request
        $status = 'Pending'; // Default status for new requests
        
        $stmtInsert = $conn->prepare("
            INSERT INTO item_requests 
            (item_id, user_id, quantity, reason, status, requested_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmtInsert->bind_param("iiiss", $item_id, $user_id, $quantity, $reason, $status);
        $stmtInsert->execute();
        
        if ($stmtInsert->affected_rows === 0) {
            // Rollback if insert failed
            $conn->rollback();
            $error = "Failed to create request";
            // Log the error
            logActivity($conn, 'error', "Request error: Failed to create request for {$item['name']} (ID: $item_id)", $user_id);
            header("Location: /hfc_inventory/Frontend/inventory.php?error=request_failed");
            exit;
        }
        
        // Log the activity
        logActivity($conn, 'request', "Requested {$quantity} of {$item['name']}", $user_id);
        
        // Commit the transaction
        $conn->commit();
        
        $success = "Request submitted successfully";
        header("Location: /hfc_inventory/Frontend/inventory.php?success=request_submitted");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->error !== '') {
            $conn->rollback();
        }
        
        $error = "Database error: " . $e->getMessage();
        // Log the database error
        logActivity($conn, 'error', "Request error: Database error when requesting item (ID: $item_id) - " . $e->getMessage(), $user_id);
        header("Location: /hfc_inventory/Frontend/inventory.php?error=database_error");
        exit;
    }
}

// Helper function to log activity
function logActivity($conn, $actionType, $actionDetails, $userId) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action_type, action_details, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iss", $userId, $actionType, $actionDetails);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'In Stock':
            return 'bg-success';
        case 'Low Stock':
            return 'bg-warning text-dark';
        case 'Out of Stock':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

// Function to get category color class
function getCategoryColorClass($category) {
    $categories = [
        'Books' => 'text-primary',
        'Electronics' => 'text-danger',
        'Furniture' => 'text-success',
        'Music' => 'text-info',
        'Cleaning' => 'text-warning',
        'Office' => 'text-dark',
        'Worship' => 'text-primary',
        'Kitchen' => 'text-success',
        'Events' => 'text-info',
        'Transport' => 'text-danger'
    ];
    
    return isset($categories[$category]) ? $categories[$category] : 'text-secondary';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Inventory | HFC Management</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css" />
    <link rel="stylesheet" href="css/auth.css" />
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
            <h2><i class="bi bi-box-seam"></i> Inventory Management</h2>
            <div class="btn-group">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="bi bi-plus-lg"></i> Add Item
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <form method="GET" action="" class="row">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Search items..." 
                           value="<?php echo htmlspecialchars($searchQuery); ?>" />
                </div>
                <div class="col-md-4">
                    <select name="category" class="form-select">
                        <option value="all" <?php echo $filterCategory === 'all' ? 'selected' : ''; ?>>All Categories</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['name']); ?>" 
                                <?php echo $filterCategory === $category['name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">Filter</button>
                </div>
            </form>
        </div>

        <!-- Inventory Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventoryItems)): ?>
                    <tr>
                        <td colspan="6" class="text-center">No inventory items found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($inventoryItems as $item): ?>
                        <tr>
                            <td><?php echo $item['id']; ?></td>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td class="<?php echo getCategoryColorClass($item['category_name']); ?>">
                                <?php echo htmlspecialchars($item['category_name']); ?>
                            </td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>
                                <span class="badge <?php echo getStatusBadgeClass($item['status']); ?>">
                                    <?php echo $item['status']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-primary" 
                                            data-bs-toggle="modal" data-bs-target="#editItemModal<?php echo $item['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="inventory.php?action=delete&id=<?php echo $item['id']; ?>" 
                                       class="btn btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this item?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                    <?php if ($_SESSION['user']['role'] !== 'admin'): ?>
                                    <button type="button" class="btn btn-success" 
                                            data-bs-toggle="modal" data-bs-target="#requestModal<?php echo $item['id']; ?>">
                                        <i class="bi bi-box-arrow-down"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_item">
                        <div class="mb-3">
                            <label for="itemName" class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="itemName" name="itemName" required>
                        </div>
                        <div class="mb-3">
                            <label for="itemCategory" class="form-label">Category</label>
                            <select id="itemCategory" name="itemCategory" class="form-select" required>
                                <option value="">Select category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="itemQuantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="itemQuantity" name="itemQuantity" required>
                        </div>
                        <div class="mb-3">
                            <label for="itemMinQuantity" class="form-label">Minimum Quantity</label>
                            <input type="number" class="form-control" id="itemMinQuantity" name="itemMinQuantity" value="5">
                            <small class="text-muted">When inventory falls below this level, status will change to "Low Stock"</small>
                        </div>

                        <div class="mb-3">
                            <label for="itemDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="itemDescription" name="itemDescription" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Item</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Item Modals - One for each item -->
    <?php foreach ($inventoryItems as $item): ?>
    <div class="modal fade" id="editItemModal<?php echo $item['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_item">
                        <input type="hidden" name="itemId" value="<?php echo $item['id']; ?>">
                        <div class="mb-3">
                            <label for="itemName<?php echo $item['id']; ?>" class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="itemName<?php echo $item['id']; ?>" 
                                   name="itemName" value="<?php echo htmlspecialchars($item['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="itemCategory<?php echo $item['id']; ?>" class="form-label">Category</label>
                            <select id="itemCategory<?php echo $item['id']; ?>" name="itemCategory" class="form-select" required>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo $item['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="itemQuantity<?php echo $item['id']; ?>" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="itemQuantity<?php echo $item['id']; ?>" 
                                   name="itemQuantity" value="<?php echo $item['quantity']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="itemMinQuantity<?php echo $item['id']; ?>" class="form-label">Minimum Quantity</label>
                            <input type="number" class="form-control" id="itemMinQuantity<?php echo $item['id']; ?>" 
                                   name="itemMinQuantity" value="<?php echo $item['min_quantity']; ?>">
                        </div>

                        <div class="mb-3">
                            <label for="itemDescription<?php echo $item['id']; ?>" class="form-label">Description</label>
                            <textarea class="form-control" id="itemDescription<?php echo $item['id']; ?>" 
                                      name="itemDescription" rows="3"><?php echo htmlspecialchars($item['description']); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Item</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Request Item Modals - One for each item -->
    <?php foreach ($inventoryItems as $item): ?>
    <div class="modal fade" id="requestModal<?php echo $item['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="inventory.php">
                        <input type="hidden" name="action" value="request_item">
                        <input type="hidden" name="itemId" value="<?php echo $item['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Item Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($item['name']); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Available Quantity</label>
                            <input type="text" class="form-control" value="<?php echo $item['quantity']; ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="requestQuantity<?php echo $item['id']; ?>" class="form-label">Request Quantity</label>
                            <input type="number" class="form-control" id="requestQuantity<?php echo $item['id']; ?>" 
                                   name="requestQuantity" max="<?php echo $item['quantity']; ?>" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="requestReason<?php echo $item['id']; ?>" class="form-label">Reason</label>
                            <textarea class="form-control" id="requestReason<?php echo $item['id']; ?>" 
                                      name="requestReason" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-success">Submit Request</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Bootstrap Bundle (includes Popper for modals) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
