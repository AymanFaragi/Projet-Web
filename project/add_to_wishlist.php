<?php
ob_start();

session_start();
include 'config.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'action' => '',
    'wishlist_count' => 0
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Please log in to manage your wishlist');
    }
    
    $userId = $_SESSION['user_id'];
    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    
    if ($productId <= 0) {
        throw new Exception('Invalid product');
    }

    $conn = getDBConnection();
    
    $checkProductStmt = $conn->prepare("SELECT 1 FROM products WHERE product_id = ?");
    $checkProductStmt->execute([$productId]);
    if (!$checkProductStmt->fetch()) {
        throw new Exception('Product not found');
    }
    
    $checkStmt = $conn->prepare("SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?");
    $checkStmt->execute([$userId, $productId]);
    
    if ($checkStmt->fetch()) {
        $deleteStmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        if ($deleteStmt->execute([$userId, $productId])) {
            $response['success'] = true;
            $response['action'] = 'removed';
            $response['message'] = 'Item removed from your wishlist';
        } 
        else {
            throw new Exception('Failed to remove item from wishlist');
        }
    } 
    else {
        $insertStmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        if ($insertStmt->execute([$userId, $productId])) {
            $response['success'] = true;
            $response['action'] = 'added';
            $response['message'] = 'Item added to your wishlist';
        } else {
            throw new Exception('Failed to add item to wishlist');
        }
    }
    
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $response['wishlist_count'] = $countStmt->fetchColumn();

} 
catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

ob_end_clean();

echo json_encode($response);
exit;
?>