<?php
session_start();
require 'firebase_api.php';

echo "<h2>Cart Debug Test</h2>";

// Check session
echo "<h3>Session Info:</h3>";
echo "user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "user_email: " . ($_SESSION['user_email'] ?? 'NOT SET') . "<br>";
echo "firebase_token: " . (isset($_SESSION['firebase_token']) ? 'SET (length: ' . strlen($_SESSION['firebase_token']) . ')' : 'NOT SET') . "<br>";

if (isset($_SESSION['firebase_token'])) {
    echo "<br><strong>Token preview:</strong> " . substr($_SESSION['firebase_token'], 0, 50) . "...<br>";
    
    // Test token with Node.js verification script
    echo "<h3>Testing Token with Firebase Admin SDK:</h3>";
    $token = escapeshellarg($_SESSION['firebase_token']);
    $output = shell_exec("cd '/home/plantsed11/VSCode Projects/AFChoco-Web' && node test_token.js $token 2>&1");
    echo "<pre>$output</pre>";
}

// Test Firebase API
echo "<h3>Firebase API Test:</h3>";
$firebaseAPI = new FirebaseAPI();
if (isset($_SESSION['firebase_token'])) {
    $firebaseAPI->setAuthToken($_SESSION['firebase_token']);
    echo "Auth token set<br>";
}

// Test profile endpoint first
echo "<h3>Profile API Response (to test token):</h3>";
$profileResponse = $firebaseAPI->getProfile();
echo "<pre>";
print_r($profileResponse);
echo "</pre>";

echo "<h3>Cart API Response:</h3>";
$cartResponse = $firebaseAPI->getCart();
echo "<pre>";
print_r($cartResponse);
echo "</pre>";

if ($cartResponse && isset($cartResponse['success']) && $cartResponse['success']) {
    echo "<h3>Cart Items Found:</h3>";
    $cartData = $cartResponse['cart'] ?? [];
    $items = $cartData['items'] ?? [];
    echo "Number of items: " . count($items) . "<br>";
    echo "<pre>";
    print_r($items);
    echo "</pre>";
} else {
    echo "<h3>No Cart Items or Error</h3>";
    echo "Error: " . ($cartResponse['error'] ?? 'Unknown error') . "<br>";
}

echo "<hr>";
echo "<h3>Possible Solutions:</h3>";
echo "<ol>";
echo "<li><strong>Token Expired:</strong> Firebase ID tokens expire after 1 hour. You may need to log out and log back in.</li>";
echo "<li><strong>Refresh Token:</strong> <a href='refresh_token.php' style='background:green;color:white;padding:10px;text-decoration:none;'>🔄 Click Here to Refresh Your Token</a></li>";
echo "<li><strong>Check Firebase Console:</strong> Verify the user exists in Firebase Authentication.</li>";
echo "<li><strong>Check Server:</strong> Make sure Node.js server is running with proper Firebase Admin SDK credentials.</li>";
echo "</ol>";

echo "<hr>";
echo "<h3>Server Status:</h3>";
$serverCheck = @file_get_contents('http://localhost:3000/api/health');
if ($serverCheck) {
    echo "<span style='color:green;'>✓ Node.js server is running</span>";
} else {
    echo "<span style='color:red;'>✗ Node.js server is not responding. Run: node server.js</span>";
}
?>
