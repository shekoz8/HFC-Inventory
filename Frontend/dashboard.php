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
                                <h3 class="mb-0" id="totalItems">127</h3>
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
                                <h3 class="mb-0" id="lowStock">8</h3>
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
                                <h3 class="mb-0" id="categoriesCount">12</h3>
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
                            <!-- Dynamic content will be added by JS -->
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
            <a href="add_item.php" class="btn btn-outline-hfc-blue w-100 mb-3">
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
    <script>
        const recentActivityData = [
            { action: 'Added 10 Bibles to inventory', time: '2 hours ago', icon: 'bi-plus-circle-fill', iconColor: 'text-success' },
            { action: 'Checked out 2 Microphones', time: 'Yesterday', icon: 'bi-dash-circle-fill', iconColor: 'text-danger' },
            { action: 'Updated Communion supplies', time: '3 days ago', icon: 'bi-pencil-fill', iconColor: 'text-primary' }
        ];

        const activityList = document.getElementById('recentActivityList');
        recentActivityData.forEach(activity => {
            const activityItem = document.createElement('div');
            activityItem.classList.add('list-group-item', 'd-flex', 'justify-content-between', 'align-items-center');
            activityItem.innerHTML = `
                <div>
                    <i class="bi ${activity.icon} ${activity.iconColor} me-2"></i>
                    <span>${activity.action}</span>
                </div>
                <small class="text-muted">${activity.time}</small>
            `;
            activityList.appendChild(activityItem);
        });
    </script>
</body>
</html>
