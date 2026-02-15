<?php
session_start();
require_once 'firebase_api.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['firebase_token']) && !isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to update cart']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['cart_item_id']) || !isset($input['quantity'])) {
    echo json_encode(['success' => false, 'message' => 'Cart item ID and quantity are required']);
    exit;
}

$cartItemId = $input['cart_item_id'];
$quantity = (int)$input['quantity'];

if ($quantity < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
    exit;
}

try {
    // Initialize Firebase API
    $firebaseAPI = new FirebaseAPI();
    
    if (isset($_SESSION['firebase_token'])) {
        $firebaseAPI->setAuthToken($_SESSION['firebase_token']);
    }
    
    if ($quantity == 0) {
        // Remove item if quantity is 0
        $result = $firebaseAPI->removeFromCart($cartItemId);
    } else {
        // Update quantity
        $result = $firebaseAPI->updateCartItem($cartItemId, $quantity);
    }
    
    if ($result && isset($result['success']) && $result['success']) {
        echo json_encode([
            'success' => true, 
            'message' => $quantity == 0 ? 'Item removed from cart' : 'Cart updated successfully',
            'quantity' => $quantity
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => $result['error'] ?? 'Failed to update cart'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Update cart error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while updating cart'
    ]);
}
?>
