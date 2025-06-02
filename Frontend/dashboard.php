<?php

// Enforce strict types
declare(strict_types=1);

// Start output buffering with compression
if (extension_loaded('zlib') && !ob_start('ob_gzhandler')) {
    ob_start();
} else {
    ob_start();
}

// Ensure session is started only once
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

// Session fingerprinting for security
$userFingerprint = md5($_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
if (!isset($_SESSION['fingerprint'])) {
    $_SESSION['fingerprint'] = $userFingerprint;
} elseif ($_SESSION['fingerprint'] !== $userFingerprint) {
    session_unset();
    session_destroy();
    header("Location: ../Frontend/index.php?error=session_invalid");
    exit;
}

// Check if user is admin
require_once "../includes/role_check.php";
checkUserRole('admin', '../Frontend/unauthorized.php');

// Log user access
if (isset($_SESSION['user']['email'])) {
    error_log('Dashboard access by: ' . $_SESSION['user']['email']);
}

// Session timeout management (30 minutes)
const SESSION_TIMEOUT = 1800;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    header("Location: ../Frontend/index.php?error=session_timeout");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Role validation
require_once '../includes/role_check.php';

// Check if user is logged in
checkUserRole(['admin', 'clerk']);

// Get user menu items based on role
$menu_items = getUserMenuItems();

// Role validation
$roleHierarchy = [
    'admin' => ['admin', 'clerk'],
    'clerk' => ['clerk']
];

if (empty($_SESSION['user']['id']) || 
    !isset($_SESSION['user']['role']) || 
    !isset($roleHierarchy[$_SESSION['user']['role']])) {
    header("Location: ../Frontend/index.php?error=access_denied");
    exit;
}

// CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        error_log('CSRF validation failed for user: ' . ($_SESSION['user']['email'] ?? 'unknown'));
        session_unset();
        session_destroy();
        header("Location: ../Frontend/index.php?error=csrf_invalid");
        exit;
    }
}

// Ensure clean output
register_shutdown_function(function() {
    if (ob_get_level()) ob_end_flush();
});

// Fetch recent activity logs for dashboard
require_once '../includes/db.php';
$recentActivities = [];
try {
    $stmt = $conn->prepare("SELECT action_type, action_details, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentActivities[] = $row;
    }
} catch (Exception $e) {
    error_log('Failed to fetch activity logs: ' . $e->getMessage());
}

// Fetch inventory stats for dashboard cards
$totalItems = 0;
$lowStock = 0;
$categoriesCount = 0;
try {
    // Total items
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM inventory_items");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $totalItems = $row['total'] ?? 0;
    // Low stock items
    $stmt = $conn->prepare("SELECT COUNT(*) as low FROM inventory_items WHERE quantity <= min_quantity");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $lowStock = $row['low'] ?? 0;
    // Categories count
    $stmt = $conn->prepare("SELECT COUNT(*) as cat FROM categories");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $categoriesCount = $row['cat'] ?? 0;
} catch (Exception $e) {
    error_log('Failed to fetch inventory stats: ' . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | HFC Inventory</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@400;600;700&display=swap" rel="stylesheet">
    <noscript><meta http-equiv="refresh" content="0;url=nojs.html"></noscript>
</head>
<body>
    <?php include "partials/navbar.php"; ?>

    <div class="container py-4">
        <!-- Welcome Banner -->
        <div class="card bg-hfc-blue text-white mb-4 border-0 shadow">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-3">
                            Welcome, <span id="dashboardUserName">
                                <?= htmlspecialchars($_SESSION['user']['name'] ?? 'Admin') ?>
                            </span>
                        </h2>
                        <p class="mb-0">Last login: <span id="lastLoginDate">Today</span></p>
                    </div>
                    <div class="col-md-4 text-center">
                        <img src="images/HFC-logo.png" alt="HFC Logo" class="img-fluid" style="max-height: 80px;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card h-100 border-0 shadow-sm hover-effect">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">TOTAL ITEMS</h6>
                                <h3 class="mb-0" id="totalItems"><?php echo $totalItems; ?></h3>
                            </div>
                            <div class="bg-hfc-blue-light rounded-circle p-3">
                                <i class="bi bi-box-seam text-white fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100 border-0 shadow-sm hover-effect">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">LOW STOCK</h6>
                                <h3 class="mb-0" id="lowStock"><?php echo $lowStock; ?></h3>
                            </div>
                            <div class="bg-warning rounded-circle p-3">
                                <i class="bi bi-exclamation-triangle text-white fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100 border-0 shadow-sm hover-effect">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">CATEGORIES</h6>
                                <h3 class="mb-0" id="categoriesCount"><?php echo $categoriesCount; ?></h3>
                            </div>
                            <div class="bg-success rounded-circle p-3">
                                <i class="bi bi-tags text-white fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role-Based Quick Actions -->
        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card border-0 shadow">
                    <div class="card-header bg-hfc-blue text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush" id="recentActivityList">
                            <?php
                            function getActivityIcon($type) {
                                switch ($type) {
                                    case 'add': return ['bi-plus-circle-fill', 'text-success'];
                                    case 'update': return ['bi-pencil-fill', 'text-primary'];
                                    case 'delete': return ['bi-dash-circle-fill', 'text-danger'];
                                    case 'error': return ['bi-exclamation-triangle-fill', 'text-warning'];
                                    case 'request': return ['bi-arrow-right-circle-fill', 'text-info'];
                                    case 'approve_request': return ['bi-check-circle-fill', 'text-success'];
                                    case 'reject_request': return ['bi-x-circle-fill', 'text-danger'];
                                    default: return ['bi-info-circle-fill', 'text-secondary'];
                                }
                            }
                            function timeAgo($datetime) {
                                $timestamp = strtotime($datetime);
                                $diff = time() - $timestamp;
                                if ($diff < 60) return 'just now';
                                if ($diff < 3600) return floor($diff/60) . ' min ago';
                                if ($diff < 86400) return floor($diff/3600) . ' hours ago';
                                return date('M d, Y', $timestamp);
                            }
                            if (!empty($recentActivities)) {
                                foreach ($recentActivities as $activity) {
                                    list($icon, $color) = getActivityIcon($activity['action_type']);
                                    echo '<div class="list-group-item d-flex justify-content-between align-items-center">';
                                    echo '<div><i class="bi ' . $icon . ' ' . $color . ' me-2"></i>';
                                    echo '<span>' . htmlspecialchars($activity['action_details']) . '</span></div>';
                                    echo '<small class="text-muted">' . timeAgo($activity['created_at']) . '</small>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<div class="text-muted">No recent activity.</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
    <div class="card border-0 shadow">
        <div class="card-header bg-hfc-blue text-white">
            <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>
        </div>
        <div class="card-body">
            <!-- Manage Inventory -->
            <a href="inventory.php" class="btn btn-hfc-blue w-100 mb-3">
                <i class="bi bi-box-seam me-2"></i>Manage Inventory
            </a>

            <!-- Add New Item -->
            <a href="inventory.php" class="btn btn-outline-hfc-blue w-100 mb-3">
                <i class="bi bi-plus-circle me-2"></i>Add New Item
            </a>

            <!-- Manage Users -->
            <a href="manage_users.php" class="btn btn-outline-hfc-blue w-100 mb-3">
                <i class="bi bi-people me-2"></i>Manage Users
            </a>

            <!-- Generate Report -->
            <a href="generate_report.php" class="btn btn-outline-hfc-blue w-100">
                <i class="bi bi-printer me-2"></i>Generate Report
            </a>
        </div>
    </div>
</div>


    <script src="js/main.js"></script>
    <!-- Removed static JS for recent activity -->
</body>
</html>
