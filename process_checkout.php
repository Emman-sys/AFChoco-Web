<?php
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['firebase_token'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'User not authenticated. Please log in again.'
    ]);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get and parse JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request data'
    ]);
    exit;
}

// Validate required fields
if (empty($data['delivery_address']) || empty($data['phone']) || empty($data['cart_items'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required fields: delivery_address, phone, or cart_items'
    ]);
    exit;
}

try {
    // Transform cart items to match Node.js API format
    $items = [];
    foreach ($data['cart_items'] as $item) {
        $items[] = [
            'productId' => $item['product_id'],
            'productName' => $item['name'],
            'productPrice' => floatval($item['price']),
            'quantity' => intval($item['quantity'])
        ];
    }
    
    // Prepare request data for Node.js API
    $apiData = [
        'items' => $items,
        'deliveryAddress' => $data['delivery_address'],
        'phoneNumber' => $data['phone'],
        'notes' => $data['payment_method'] ?? '' // Store payment method in notes for now
    ];
    
    // Get Firebase auth token from session
    $firebaseToken = $_SESSION['firebase_token'];
    
    // Call Node.js API
    $apiUrl = 'http://localhost:3000/api/orders/create';
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $firebaseToken
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Handle cURL errors
    if ($curlError) {
        error_log("Checkout cURL error: " . $curlError);
        throw new Exception("Unable to connect to order service. Please try again.");
    }
    
    // Parse API response
    $apiResponse = json_decode($response, true);
    
    if ($httpCode === 201 && $apiResponse && isset($apiResponse['success']) && $apiResponse['success']) {
        // Success - return formatted response
        echo json_encode([
            'success' => true,
            'message' => 'Order placed successfully!',
            'order_id' => $apiResponse['orderId'] ?? $apiResponse['order']['id'] ?? 'N/A',
            'total_amount' => $data['total_amount'],
            'delivery_address' => $data['delivery_address'],
            'payment_method' => $data['payment_method'] ?? 'Cash on Delivery'
        ]);
    } else {
        // API returned an error
        $errorMessage = $apiResponse['error'] ?? $apiResponse['message'] ?? 'Failed to process order';
        error_log("Order API error (HTTP $httpCode): " . $errorMessage);
        
        http_response_code($httpCode >= 400 ? $httpCode : 500);
        echo json_encode([
            'success' => false,
            'message' => $errorMessage
        ]);
    }
    
} catch (Exception $e) {
    error_log("Checkout exception: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
