<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HFC Inventory - Database Setup</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .setup-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .logo {
            max-height: 60px;
            margin-bottom: 20px;
        }
        .setup-results {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 5px solid #052460;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container setup-container">
        <div class="text-center mb-4">
            <img src="../Frontend/images/HFC-logo.png" alt="HFC Logo" class="logo">
            <h2>HFC Inventory System - Database Setup</h2>
            <p class="text-muted">This utility will set up the necessary database tables for the item checkout system</p>
        </div>

        <div class="alert alert-info">
            <h5><i class="bi bi-info-circle"></i> What This Will Do:</h5>
            <ul>
                <li>Create the <code>inventory_transactions</code> table if it doesn't exist</li>
                <li>Create the <code>item_checkouts</code> table if it doesn't exist</li>
                <li>Create the <code>activity_logs</code> table if it doesn't exist</li>
                <li>Add missing columns to the <code>item_requests</code> table if needed</li>
                <li>Create checkout records for existing approved requests</li>
            </ul>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])): ?>
            <div class="setup-results">
                <h4><i class="bi bi-gear-fill"></i> Setup Results:</h4>
                <hr>
                <?php include 'setup_checkout_tables.php'; ?>
            </div>
        <?php else: ?>
            <form method="post" action="">
                <div class="d-grid gap-2">
                    <button type="submit" name="setup" class="btn btn-primary btn-lg">
                        <i class="bi bi-database-fill-gear"></i> Run Database Setup
                    </button>
                </div>
            </form>
            <div class="mt-3 text-center">
                <a href="/hfc_inventory/Frontend/dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
