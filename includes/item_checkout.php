<?php
/**
 * Item Checkout and Return Handler
 * Manages the process of checking out items after request approval and returning them
 */
require_once 'db.php';

/**
 * Create a checkout record when an item request is approved
 * 
 * @param int $requestId The ID of the approved request
 * @param int $userId The ID of the user checking out the item
 * @param int $itemId The ID of the item being checked out
 * @param int $quantity The quantity being checked out
 * @param string $notes Optional notes about the checkout
 * @return bool True if successful, false otherwise
 */
function createCheckout($requestId, $userId, $itemId, $quantity, $notes = '') {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO item_checkouts 
            (request_id, user_id, item_id, quantity, status, notes, created_at)
            VALUES (?, ?, ?, ?, 'Pending', ?, NOW())
        ");
        $stmt->bind_param("iiiis", $requestId, $userId, $itemId, $quantity, $notes);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Error creating checkout: " . $e->getMessage());
        return false;
    }
}

/**
 * Process item checkout (when user receives the item)
 * 
 * @param int $checkoutId The ID of the checkout record
 * @param int $userId The ID of the user checking out the item
 * @param string $expectedReturnDate Optional expected return date (format: Y-m-d)
 * @return bool True if successful, false otherwise
 */
function processCheckout($checkoutId, $userId, $expectedReturnDate = null) {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        // Get checkout details
        $stmt = $conn->prepare("
            SELECT c.*, r.item_id, r.quantity, i.name as item_name
            FROM item_checkouts c
            JOIN item_requests r ON c.request_id = r.id
            JOIN inventory_items i ON r.item_id = i.id
            WHERE c.id = ? AND c.user_id = ?
        ");
        $stmt->bind_param("ii", $checkoutId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Checkout record not found or unauthorized");
        }
        
        $checkout = $result->fetch_assoc();
        
        // Update checkout status
        $status = 'Checked Out';
        $stmt = $conn->prepare("
            UPDATE item_checkouts 
            SET status = ?, checkout_date = NOW(), expected_return_date = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssi", $status, $expectedReturnDate, $checkoutId);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Failed to update checkout status");
        }
        
        // Log the activity
        $logDetails = "Checked out {$checkout['quantity']} of {$checkout['item_name']}";
        $stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action_type, action_details, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $actionType = 'item_checkout';
        $stmt->bind_param("iss", $userId, $actionType, $logDetails);
        $stmt->execute();
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error processing checkout: " . $e->getMessage());
        return false;
    }
}

/**
 * Process item return
 * 
 * @param int $checkoutId The ID of the checkout record
 * @param int $userId The ID of the user returning the item
 * @param int $returnQuantity The quantity being returned
 * @param string $notes Optional notes about the return
 * @return bool True if successful, false otherwise
 */
function processReturn($checkoutId, $userId, $returnQuantity, $notes = '') {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        // Get checkout details
        $stmt = $conn->prepare("
            SELECT c.*, r.item_id, r.quantity as requested_quantity, i.name as item_name, i.quantity as current_quantity
            FROM item_checkouts c
            JOIN item_requests r ON c.request_id = r.id
            JOIN inventory_items i ON r.item_id = i.id
            WHERE c.id = ? AND c.user_id = ?
        ");
        $stmt->bind_param("ii", $checkoutId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Checkout record not found or unauthorized");
        }
        
        $checkout = $result->fetch_assoc();
        
        // Validate return quantity
        if ($returnQuantity <= 0 || $returnQuantity > $checkout['requested_quantity']) {
            throw new Exception("Invalid return quantity");
        }
        
        // Update checkout status
        $status = ($returnQuantity == $checkout['requested_quantity']) ? 'Returned' : 'Partially Returned';
        $stmt = $conn->prepare("
            UPDATE item_checkouts 
            SET status = ?, actual_return_date = NOW(), notes = CONCAT(IFNULL(notes, ''), '\nReturned: ', ?, ' on ', NOW(), ' - ', ?)
            WHERE id = ?
        ");
        $stmt->bind_param("sisi", $status, $returnQuantity, $notes, $checkoutId);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Failed to update checkout status");
        }
        
        // Update inventory quantity
        $newQuantity = $checkout['current_quantity'] + $returnQuantity;
        
        // Determine new status based on quantity and min_quantity
        $stmtItem = $conn->prepare("SELECT min_quantity FROM inventory_items WHERE id = ?");
        $stmtItem->bind_param("i", $checkout['item_id']);
        $stmtItem->execute();
        $resultItem = $stmtItem->get_result();
        $item = $resultItem->fetch_assoc();
        
        $itemStatus = 'In Stock';
        if ($newQuantity <= 0) {
            $itemStatus = 'Out of Stock';
        } elseif ($newQuantity <= $item['min_quantity']) {
            $itemStatus = 'Low Stock';
        }
        
        $stmt = $conn->prepare("
            UPDATE inventory_items 
            SET quantity = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("isi", $newQuantity, $itemStatus, $checkout['item_id']);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Failed to update inventory quantity");
        }
        
        // Log the activity
        $logDetails = "Returned {$returnQuantity} of {$checkout['item_name']}";
        if (!empty($notes)) {
            $logDetails .= " - Notes: {$notes}";
        }
        
        $stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action_type, action_details, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $actionType = 'item_return';
        $stmt->bind_param("iss", $userId, $actionType, $logDetails);
        $stmt->execute();
        
        // Create inventory transaction record
        $stmt = $conn->prepare("
            INSERT INTO inventory_transactions 
            (item_id, user_id, quantity, transaction_type, notes, transaction_date)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $transactionType = 'item_return';
        $transactionNotes = "Returned {$returnQuantity} of {$checkout['item_name']}";
        $stmt->bind_param("iiiss", $checkout['item_id'], $userId, $returnQuantity, $transactionType, $transactionNotes);
        $stmt->execute();
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error processing return: " . $e->getMessage());
        return false;
    }
}

/**
 * Get checkout records for a user
 * 
 * @param int $userId The ID of the user
 * @param string $status Optional status filter
 * @param int $page Page number for pagination
 * @param int $perPage Items per page
 * @return array Array of checkout records
 */
function getUserCheckouts($userId, $status = 'all', $page = 1, $perPage = 10) {
    global $conn;
    
    $offset = ($page - 1) * $perPage;
    
    $query = "
        SELECT c.*, r.status as request_status, i.name as item_name, 
               DATE_FORMAT(c.checkout_date, '%M %d, %Y') as formatted_checkout_date,
               DATE_FORMAT(c.expected_return_date, '%M %d, %Y') as formatted_expected_return_date,
               DATE_FORMAT(c.actual_return_date, '%M %d, %Y') as formatted_actual_return_date
        FROM item_checkouts c
        JOIN item_requests r ON c.request_id = r.id
        JOIN inventory_items i ON c.item_id = i.id
        WHERE c.user_id = ?
    ";
    
    if ($status !== 'all') {
        $query .= " AND c.status = ?";
        $stmt = $conn->prepare($query . " ORDER BY c.created_at DESC LIMIT ?, ?");
        $stmt->bind_param("isii", $userId, $status, $offset, $perPage);
    } else {
        $stmt = $conn->prepare($query . " ORDER BY c.created_at DESC LIMIT ?, ?");
        $stmt->bind_param("iii", $userId, $offset, $perPage);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $checkouts = [];
    while ($row = $result->fetch_assoc()) {
        $checkouts[] = $row;
    }
    
    return $checkouts;
}

/**
 * Count total checkout records for a user
 * 
 * @param int $userId The ID of the user
 * @param string $status Optional status filter
 * @return int Total count of checkout records
 */
function countUserCheckouts($userId, $status = 'all') {
    global $conn;
    
    $query = "
        SELECT COUNT(*) as total
        FROM item_checkouts
        WHERE user_id = ?
    ";
    
    if ($status !== 'all') {
        $query .= " AND status = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $userId, $status);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

/**
 * Get a specific checkout record by ID
 * 
 * @param int $checkoutId The ID of the checkout record
 * @param int $userId The ID of the user (for authorization)
 * @return array|null Checkout record or null if not found
 */
function getCheckoutById($checkoutId, $userId) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT c.*, r.status as request_status, i.name as item_name, i.category
        FROM item_checkouts c
        JOIN item_requests r ON c.request_id = r.id
        JOIN inventory_items i ON c.item_id = i.id
        WHERE c.id = ? AND c.user_id = ?
    ");
    $stmt->bind_param("ii", $checkoutId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    return $result->fetch_assoc();
}
?>
