<?php
session_start();
require_once 'firebase_api.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['firebase_token']) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to remove items from cart']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['cart_item_id'])) {
    echo json_encode(['success' => false, 'message' => 'Cart item ID is required']);
    exit;
}

$cartItemId = $input['cart_item_id'];

try {
    // Initialize Firebase API
    $firebaseAPI = new FirebaseAPI();
    
    if (isset($_SESSION['firebase_token'])) {
        $firebaseAPI->setAuthToken($_SESSION['firebase_token']);
    }
    
    $result = $firebaseAPI->removeFromCart($cartItemId);
    
    if ($result && isset($result['success']) && $result['success']) {
        echo json_encode([
            'success' => true, 
            'message' => 'Item removed from cart successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => $result['error'] ?? 'Failed to remove item from cart'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Remove from cart error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while removing item from cart'
    ]);
}
?>
