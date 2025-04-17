<?php
session_start();
if (!isset($_SESSION["username"])) {
    header("Location: /hfc_inventory/Frontend/index.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | HFC Inventory</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <span id="currentUserName">User</span>
    <!-- Dynamic Navbar -->
    <div id="navbarContainer"></div>

    <div class="container py-4">
        <!-- Welcome Banner -->
        <div class="card bg-hfc-blue text-white mb-4 border-0 shadow">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-3">Welcome, <span id="dashboardUserName">Administrator</span></h2>
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
            <!-- Quick Stats Cards go here -->
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

        <div class="dropdown">
            <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i> <span id="userName">Shekinah</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#" onclick="logout()">Logout</a></li>
            </ul>
        </div>        

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card border-0 shadow">
                    <div class="card-header bg-hfc-blue text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush" id="recentActivityList">
                            <!-- Dynamic content for recent activity will go here -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
            <?php if ($_SESSION['role'] === 'admin'): ?>
    <!-- Admin: Quick Actions -->
    <div class="card border-0 shadow">
        <div class="card-header bg-hfc-blue text-white">
            <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Actions</h5>
        </div>
        <div class="card-body">
            <a href="inventory.html" class="btn btn-hfc-blue w-100 mb-3">
                <i class="bi bi-box-seam me-2"></i>Manage Inventory
            </a>
            <button class="btn btn-outline-hfc-blue w-100 mb-3">
                <i class="bi bi-plus-circle me-2"></i>Add New Item
            </button>
            <button class="btn btn-outline-hfc-blue w-100 mb-3">
                <i class="bi bi-people me-2"></i>Manage Users
            </button>
            <button class="btn btn-outline-hfc-blue w-100">
                <i class="bi bi-printer me-2"></i>Generate Report
            </button>
        </div>
    </div>
<?php else: ?>
    <!-- Normal user: Limited Actions -->
    <div class="card border-0 shadow">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="bi bi-eye me-2"></i>Your Dashboard</h5>
        </div>
        <div class="card-body">
            <p>Welcome, user! You can only view inventory and request items.</p>
            <a href="inventory.html" class="btn btn-outline-info w-100">
                <i class="bi bi-box-seam me-2"></i>View Inventory
            </a>
        </div>
    </div>
<?php endif; ?>
            </div>
        </div>

    </div>

    <script src="js/main.js"></script>
    <script>
        // Load navbar
        fetch('partials/navbar.html')
            .then(response => {
                if (!response.ok) {
                    throw new Error("Navbar file not found");
                }
                return response.text();
            })
            .then(data => {
                document.getElementById('navbarContainer').innerHTML = data;

                // Update user info
                const user = JSON.parse(localStorage.getItem('user')) || { name: 'Administrator', lastLogin: 'Today' };

                document.getElementById('dashboardUserName').textContent = user.name;
                document.getElementById('userName').textContent = user.name.split(' ')[0];

                if (user.lastLogin) {
                    document.getElementById('lastLoginDate').textContent = new Date(user.lastLogin).toLocaleString();
                }
            })
            .catch(error => console.error("Error loading navbar:", error));
    </script>
    
    <script>
        function logout() {
            localStorage.removeItem('user'); // Clear current user session
            window.location.href = 'index.html'; // Redirect to login page
        }

        // Optional: Block access to dashboard if no user is logged in
        window.addEventListener('DOMContentLoaded', () => {
            const currentUser = JSON.parse(localStorage.getItem('user'));
            if (!currentUser) {
                window.location.href = 'index.html'; // Send to login page
            }
        });

        const currentUser = JSON.parse(localStorage.getItem('user'));
        if (currentUser) {
            document.getElementById('currentUserName').textContent = currentUser.name;
        }

        // Dynamic recent activity
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