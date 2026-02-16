<?php
session_start();
require_once 'firebase_api.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['firebase_token']) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to add items to cart']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

// Extract all required fields
$productId = $input['product_id'];
$quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
$productName = $input['productName'] ?? '';
$productPrice = isset($input['productPrice']) ? floatval($input['productPrice']) : 0;
$productImageUrl = $input['productImageUrl'] ?? '';

// Validate required fields
if (empty($productName) || $productPrice <= 0) {
    echo json_encode(['success' => false, 'message' => 'Product name and price are required']);
    exit;
}

try {
    // Initialize Firebase API
    $firebaseAPI = new FirebaseAPI();
    
    if (isset($_SESSION['firebase_token'])) {
        $firebaseAPI->setAuthToken($_SESSION['firebase_token']);
    }
    
    // Add to cart with all required fields matching mobile app schema
    $cartData = [
        'productId' => $productId,
        'productName' => $productName,
        'productPrice' => $productPrice,
        'productImageUrl' => $productImageUrl,
        'quantity' => $quantity
    ];
    
    $result = $firebaseAPI->addToCart($cartData);
    
    if ($result && isset($result['success']) && $result['success']) {
        echo json_encode([
            'success' => true, 
            'message' => 'Added to cart successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => $result['error'] ?? 'Failed to add to cart'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Add to cart error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while adding to cart'
    ]);
}
?>