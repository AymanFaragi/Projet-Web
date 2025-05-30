<?php
ob_start();

session_start();
include 'config.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'cart_count' => 0
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Please log in to add items to cart');
    }
    
    $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    if ($productId <= 0) {
        throw new Exception('Invalid product');
    }
    
    if ($quantity <= 0) {
        throw new Exception('Invalid quantity');
    }
    
    $conn = getDBConnection();
    
    $productStmt = $conn->prepare("SELECT quantity FROM products WHERE product_id = ?");
    $productStmt->execute([$productId]);
    $product = $productStmt->fetch();
    
    if (!$product) {
        throw new Exception('Product not found');
    }
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    $maxCartItems = 100;
    $maxQuantityPerItem = 20;
    
    $found = false;
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['product_id'] == $productId) {
            $newQuantity = $item['quantity'] + $quantity;
            
            if ($newQuantity > $product['quantity']) {
                throw new Exception('Not enough stock available');
            }
            
            if ($newQuantity > $maxQuantityPerItem) {
                throw new Exception("Maximum $maxQuantityPerItem per item allowed");
            }
            
            $item['quantity'] = $newQuantity;
            $found = true;
            break;
        }
    }

    if (!$found) {
        if (count($_SESSION['cart']) >= $maxCartItems) {
            throw new Exception("Maximum $maxCartItems items allowed in cart");
        }

        if ($quantity > $product['quantity']) {
            throw new Exception('Not enough stock available');
        }
        
        if ($quantity > $maxQuantityPerItem) {
            throw new Exception("Maximum $maxQuantityPerItem per item allowed");
        }
        
        $_SESSION['cart'][] = [
            'product_id' => $productId,
            'quantity' => $quantity
        ];
    }

    $cartCount = array_sum(array_column($_SESSION['cart'], 'quantity'));
    $response['success'] = true;
    $response['message'] = 'Product added to cart';
    $response['cart_count'] = $cartCount;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

ob_end_clean();

echo json_encode($response);
exit;
?>