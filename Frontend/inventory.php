<?php
session_start();

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
    <?php
    include "partials/navbar.php"; 
    ?>

    <div class="container py-4">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-box-seam"></i> Inventory Management</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                <i class="bi bi-plus-lg"></i> Add Item
            </button>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-md-6">
                <input type="text" id="searchInput" class="form-control" placeholder="Search items..." />
            </div>
            <div class="col-md-6">
                <select id="categoryFilter" class="form-select">
                    <option value="all">All Categories</option>
                    <option value="Books">Books & Literature</option>
                    <option value="Electronics">Electronics</option>
                    <option value="Furniture">Furniture</option>
                    <option value="Music">Musical Instruments</option>
                    <option value="Cleaning">Cleaning & Maintenance</option>
                    <option value="Office">Office Supplies</option>
                    <option value="Worship">Communion & Worship</option>
                    <option value="Kitchen">Kitchen & Hospitality</option>
                    <option value="Events">Event & Decoration</option>
                    <option value="Transport">Vehicles & Transport</option>
                </select>
            </div>
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
                <tbody id="inventoryTable">
                    <!-- Inventory items will be dynamically inserted here -->
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
                    <form id="addItemForm">
                        <div class="mb-3">
                            <label for="itemName" class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="itemName" required>
                        </div>
                        <div class="mb-3">
                            <label for="itemCategory" class="form-label">Category</label>
                            <select id="itemCategory" class="form-select" required>
                                <option value="">Select category</option>
                                <option value="Books">Books & Literature</option>
                                <option value="Electronics">Electronics</option>
                                <option value="Furniture">Furniture</option>
                                <option value="Music">Musical Instruments</option>
                                <option value="Cleaning">Cleaning & Maintenance</option>
                                <option value="Office">Office Supplies</option>
                                <option value="Worship">Communion & Worship</option>
                                <option value="Kitchen">Kitchen & Hospitality</option>
                                <option value="Events">Event & Decoration</option>
                                <option value="Transport">Vehicles & Transport</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="itemQuantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="itemQuantity" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Item</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Item Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Item Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="requestForm">
                        <div class="mb-3">
                            <label for="requestItemName" class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="requestItemName" required>
                        </div>
                        <div class="mb-3">
                            <label for="requestQuantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="requestQuantity" required>
                        </div>
                        <button type="submit" class="btn btn-success">Submit Request</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle (includes Popper for modals) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Your Scripts -->
    <script src="js/main.js"></script>
    <script src="js/inventory.js"></script>
</body>
</html>
