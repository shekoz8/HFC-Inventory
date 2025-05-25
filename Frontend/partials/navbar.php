<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}
$userName = $_SESSION['user']['name'];
$userRole = $_SESSION['user']['role'];
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #052460;">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <img src="images/HFC-logo.png" alt="HFC" height="40" class="d-inline-block align-top me-2">
            <span class="d-none d-md-inline">Inventory System</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/hfc_inventory/Frontend/dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/hfc_inventory/Frontend/inventory.php">
                        <i class="bi bi-box-seam"></i> Inventory
                    </a>
                </li>
                <?php if ($userRole !== 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/hfc_inventory/Frontend/my_requests.php">
                        <i class="bi bi-card-checklist"></i> My Requests
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($userRole === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/hfc_inventory/Frontend/request_management.php">
                        <i class="bi bi-clipboard-check"></i> Request Management
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($userName) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="bi bi-person"></i> Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
