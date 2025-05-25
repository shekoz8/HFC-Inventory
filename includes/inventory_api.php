<?php
header('Content-Type: application/json');
session_start();

// Debug: Log all requests
error_log("API Request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);

// Temporarily bypass authentication for testing
// Comment this back in when authentication is working
/*
if (!isset($_SESSION['user']) || empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}
*/

// For testing, set a dummy user ID
$_SESSION['user']['id'] = 1;

// Include database connection
require_once 'db.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Log to server console
function console_log($output, $with_timestamp_and_script_name = false) {
    $js_console_format = $with_timestamp_and_script_name ? '[%s] %s: %s' : '%s';
    $timestamp = date('Y-m-d H:i:s');
    $script_name = $_SERVER['SCRIPT_NAME'];
    $output = print_r($output, true);
    $output = sprintf($js_console_format, $timestamp, $script_name, $output);
    error_log($output);
    return $output;
}


// Get the action from the request
$action = isset($_GET['action']) ? $_GET['action'] : '';

console_log("Method: " . $method);
console_log("Action: " . $action);

// Process based on method and action
switch ($method) {
    case 'GET':
        // Read operations
        if ($action === 'get_all') {
            getAllItems($conn);
        } elseif ($action === 'get_item' && isset($_GET['id'])) {
            getItemById($conn, $_GET['id']);
        } elseif ($action === 'get_categories') {
            getCategories($conn);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
        }
        break;
        
    case 'POST':
        // Create operation
        if ($action === 'add') {
            $data = json_decode(file_get_contents('php://input'), true);
            addItem($conn, $data);
        } elseif ($action === 'request') {
            $data = json_decode(file_get_contents('php://input'), true);
            requestItem($conn, $data);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
        }
        break;
        
    case 'PUT':
        // Update operation
        if ($action === 'update' && isset($_GET['id'])) {
            $data = json_decode(file_get_contents('php://input'), true);
            updateItem($conn, $_GET['id'], $data);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
        }
        break;
        
    case 'DELETE':
        // Delete operation
        if ($action === 'delete' && isset($_GET['id'])) {
            deleteItem($conn, $_GET['id']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

// Function to get all inventory items
function getAllItems($conn) {
    try {
        // Join with categories table to get category name
        $stmt = $conn->prepare("
            SELECT i.*, c.name as category_name 
            FROM inventory_items i
            JOIN categories c ON i.category_id = c.id
            ORDER BY i.id ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $items = [];
        while ($row = $result->fetch_assoc()) {
            // Format the item for the frontend
            $items[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'category' => $row['category_name'],
                'category_id' => $row['category_id'],
                'quantity' => $row['quantity'],
                'min_quantity' => $row['min_quantity'],
                'status' => $row['status'],
                'location' => $row['location'],
                'description' => $row['description'],
                'created_at' => $row['created_at']
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $items]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to get a specific inventory item by ID
function getItemById($conn, $id) {
    try {
        $stmt = $conn->prepare("
            SELECT i.*, c.name as category_name 
            FROM inventory_items i
            JOIN categories c ON i.category_id = c.id
            WHERE i.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Item not found']);
            return;
        }
        
        $row = $result->fetch_assoc();
        $item = [
            'id' => $row['id'],
            'name' => $row['name'],
            'category' => $row['category_name'],
            'category_id' => $row['category_id'],
            'quantity' => $row['quantity'],
            'min_quantity' => $row['min_quantity'],
            'status' => $row['status'],
            'location' => $row['location'],
            'description' => $row['description'],
            'created_at' => $row['created_at']
        ];
        
        echo json_encode(['success' => true, 'data' => $item]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to get all categories
function getCategories($conn) {
    try {
        $stmt = $conn->prepare("SELECT * FROM categories ORDER BY name ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        
        echo json_encode(['success' => true, 'data' => $categories]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to add a new inventory item
function addItem($conn, $data) {
    // Validate required fields
    if (!isset($data['name']) || !isset($data['category_id']) || !isset($data['quantity'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Determine status based on quantity and min_quantity
        $min_quantity = isset($data['min_quantity']) ? $data['min_quantity'] : 5;
        $status = determineStatus($data['quantity'], $min_quantity);
        
        // Insert the item
        $stmt = $conn->prepare("
            INSERT INTO inventory_items 
            (name, category_id, quantity, min_quantity, status, location, description, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $location = isset($data['location']) ? $data['location'] : null;
        $description = isset($data['description']) ? $data['description'] : null;
        $userId = $_SESSION['user']['id'];
        
        $stmt->bind_param(
            "siisssi", 
            $data['name'], 
            $data['category_id'], 
            $data['quantity'], 
            $min_quantity,
            $status,
            $location,
            $description,
            $userId
        );
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $newId = $conn->insert_id;
            
            // Log the transaction
            $transactionStmt = $conn->prepare("
                INSERT INTO inventory_transactions 
                (item_id, quantity, transaction_type, user_id, notes) 
                VALUES (?, ?, 'add', ?, 'Initial inventory addition')
            ");
            $transactionStmt->bind_param("iii", $newId, $data['quantity'], $userId);
            $transactionStmt->execute();
            
            // Log the activity
            logActivity($conn, 'item_add', "Added new item: {$data['name']}", $userId);
            
            // Commit transaction
            $conn->commit();
            
            // Get the category name for the response
            $catStmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
            $catStmt->bind_param("i", $data['category_id']);
            $catStmt->execute();
            $catResult = $catStmt->get_result();
            $category = $catResult->fetch_assoc()['name'];
            
            echo json_encode([
                'success' => true, 
                'message' => 'Item added successfully',
                'data' => [
                    'id' => $newId,
                    'name' => $data['name'],
                    'category' => $category,
                    'category_id' => $data['category_id'],
                    'quantity' => $data['quantity'],
                    'min_quantity' => $min_quantity,
                    'status' => $status,
                    'location' => $location,
                    'description' => $description
                ]
            ]);
        } else {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add item']);
        }
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to update an existing inventory item
function updateItem($conn, $id, $data) {
    // Validate that we have at least one field to update
    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        return;
    }
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // First check if the item exists and get current values
        $checkStmt = $conn->prepare("SELECT * FROM inventory_items WHERE id = ?");
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Item not found']);
            return;
        }
        
        $currentItem = $result->fetch_assoc();
        $oldQuantity = $currentItem['quantity'];
        
        // Build the update query dynamically based on provided fields
        $updateFields = [];
        $types = '';
        $params = [];
        
        // Map of field names to their types for bind_param
        $fieldTypes = [
            'name' => 's',
            'category_id' => 'i',
            'quantity' => 'i',
            'min_quantity' => 'i',
            'location' => 's',
            'description' => 's'
        ];
        
        // Add each field that was provided to the update query
        foreach ($fieldTypes as $field => $type) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = ?";
                $types .= $type;
                $params[] = $data[$field];
            }
        }
        
        // If quantity is being updated, update the status as well
        if (isset($data['quantity'])) {
            $min_quantity = isset($data['min_quantity']) ? $data['min_quantity'] : $currentItem['min_quantity'];
            $status = determineStatus($data['quantity'], $min_quantity);
            $updateFields[] = "status = ?";
            $types .= "s";
            $params[] = $status;
        }
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No valid fields to update']);
            return;
        }
        
        // Add the ID parameter for the WHERE clause
        $types .= "i";
        $params[] = $id;
        
        $updateQuery = "UPDATE inventory_items SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        // If quantity changed, log the transaction
        if (isset($data['quantity']) && $data['quantity'] != $oldQuantity) {
            $quantityDiff = $data['quantity'] - $oldQuantity;
            $transactionType = $quantityDiff > 0 ? 'add' : 'remove';
            $quantityChange = abs($quantityDiff);
            
            $transactionStmt = $conn->prepare("
                INSERT INTO inventory_transactions 
                (item_id, quantity, transaction_type, user_id, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $userId = $_SESSION['user']['id'];
            $notes = "Quantity updated from $oldQuantity to {$data['quantity']}";
            
            $transactionStmt->bind_param("iisis", $id, $quantityChange, $transactionType, $userId, $notes);
            $transactionStmt->execute();
        }
        
        // Log the activity
        $userId = $_SESSION['user']['id'];
        logActivity($conn, 'item_update', "Updated item ID: $id", $userId);
        
        // Commit transaction
        $conn->commit();
        
        // Get the updated item with category name
        $getStmt = $conn->prepare("
            SELECT i.*, c.name as category_name 
            FROM inventory_items i
            JOIN categories c ON i.category_id = c.id
            WHERE i.id = ?
        ");
        $getStmt->bind_param("i", $id);
        $getStmt->execute();
        $result = $getStmt->get_result();
        $row = $result->fetch_assoc();
        
        $updatedItem = [
            'id' => $row['id'],
            'name' => $row['name'],
            'category' => $row['category_name'],
            'category_id' => $row['category_id'],
            'quantity' => $row['quantity'],
            'min_quantity' => $row['min_quantity'],
            'status' => $row['status'],
            'location' => $row['location'],
            'description' => $row['description'],
            'created_at' => $row['created_at']
        ];
        
        echo json_encode([
            'success' => true, 
            'message' => 'Item updated successfully',
            'data' => $updatedItem
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to delete an inventory item
function deleteItem($conn, $id) {
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // First check if the item exists
        $checkStmt = $conn->prepare("SELECT name FROM inventory_items WHERE id = ?");
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Item not found']);
            return;
        }
        
        $itemName = $result->fetch_assoc()['name'];
        
        // Delete the item (will cascade to transactions)
        $stmt = $conn->prepare("DELETE FROM inventory_items WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Log the activity
            $userId = $_SESSION['user']['id'];
            logActivity($conn, 'item_delete', "Deleted item: $itemName (ID: $id)", $userId);
            
            // Commit transaction
            $conn->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Item deleted successfully',
                'id' => $id
            ]);
        } else {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete item']);
        }
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to handle item requests
function requestItem($conn, $data) {
    // Validate required fields
    if (!isset($data['item_id']) || !isset($data['quantity']) || !isset($data['reason'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    try {
        // First check if the item exists and has enough quantity
        $stmtCheck = $conn->prepare("SELECT name, quantity FROM inventory_items WHERE id = ?");
        $stmtCheck->bind_param("i", $data['item_id']);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        
        if ($resultCheck->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Item not found']);
            return;
        }
        
        $item = $resultCheck->fetch_assoc();
        
        if ($item['quantity'] < $data['quantity']) {
            http_response_code(400);
            echo json_encode(['error' => 'Not enough quantity available']);
            return;
        }
        
        // Begin transaction
        $conn->begin_transaction();
        
        // Insert the request
        $userId = $_SESSION['user']['id'];
        $status = 'Pending'; // Default status for new requests
        
        $stmtInsert = $conn->prepare("
            INSERT INTO item_requests 
            (item_id, user_id, quantity, reason, status, requested_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmtInsert->bind_param("iiiss", $data['item_id'], $userId, $data['quantity'], $data['reason'], $status);
        $stmtInsert->execute();
        
        if ($stmtInsert->affected_rows === 0) {
            // Rollback if insert failed
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create request']);
            return;
        }
        
        $requestId = $stmtInsert->insert_id;
        
        // Log the activity
        logActivity($conn, 'request', "Requested {$data['quantity']} of {$item['name']}", $userId);
        
        // Commit the transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Request submitted successfully',
            'data' => [
                'id' => $requestId,
                'item_id' => $data['item_id'],
                'quantity' => $data['quantity'],
                'status' => $status,
                'requested_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->error !== '') {
            $conn->rollback();
        }
        
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

// Helper function to determine item status based on quantity
function determineStatus($quantity, $minQuantity) {
    if ($quantity <= 0) {
        return 'Out of Stock';
    } elseif ($quantity < $minQuantity) {
        return 'Low Stock';
    } else {
        return 'In Stock';
    }
}

// Helper function to log activity
function logActivity($conn, $actionType, $actionDetails, $userId) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO activity_logs 
            (user_id, action_type, action_details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        
        $stmt->bind_param("issss", $userId, $actionType, $actionDetails, $ipAddress, $userAgent);
        $stmt->execute();
    } catch (Exception $e) {
        // Just log to error log but don't stop execution
        error_log("Failed to log activity: " . $e->getMessage());
    }
}
?>
