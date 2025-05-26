<?php
/**
 * Check if user has required role for a page
 * @param string|array $required_roles Single role or array of roles
 * @param string $redirect_to Page to redirect unauthorized users to
 */
function checkUserRole($required_roles, $redirect_to = 'index.php') {
    if (!isset($_SESSION['user'])) {
        header("Location: $redirect_to");
        exit();
    }

    $user_role = $_SESSION['user']['role'];
    
    // Convert to array if single role provided
    $required_roles = is_array($required_roles) ? $required_roles : [$required_roles];
    
    if (!in_array($user_role, $required_roles)) {
        header("Location: $redirect_to");
        exit();
    }
}

/**
 * Get user-specific menu items
 * @return array Menu items based on user role
 */
function getUserMenuItems() {
    if (!isset($_SESSION['user'])) {
        return [];
    }
    
    $user_role = $_SESSION['user']['role'];
    
    $menu_items = [
        'dashboard' => [
            'title' => 'Dashboard',
            'icon' => 'bi-speedometer2',
            'url' => '/hfc_inventory/Frontend/dashboard.php'
        ],
        'inventory' => [
            'title' => 'Inventory',
            'icon' => 'bi-box-seam',
            'url' => '/hfc_inventory/Frontend/inventory.php'
        ]
    ];
    
    if ($user_role === 'admin') {
        $menu_items['request_management'] = [
            'title' => 'Request Management',
            'icon' => 'bi-clipboard-check',
            'url' => '/hfc_inventory/Frontend/request_management.php'
        ];
        $menu_items['manage_users'] = [
            'title' => 'Manage Users',
            'icon' => 'bi-people',
            'url' => '/hfc_inventory/Frontend/manage_users.php'
        ];
        $menu_items['generate_report'] = [
            'title' => 'Generate Report',
            'icon' => 'bi-printer',
            'url' => '/hfc_inventory/Frontend/generate_report.php'
        ];
    } else {
        $menu_items['my_requests'] = [
            'title' => 'My Requests',
            'icon' => 'bi-card-checklist',
            'url' => '/hfc_inventory/Frontend/my_requests.php'
        ];
    }
    
    return $menu_items;
}
