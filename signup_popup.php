<?php
session_start();
ob_start();
ob_clean();
header('Content-Type: application/json');

require_once 'firebase_api.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    $idToken = $_POST['idToken'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $uid = $_POST['uid'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($idToken)) {
        echo json_encode(['success' => false, 'message' => 'Authentication token required']);
        exit;
    }

    if (empty($username) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Username and email are required']);
        exit;
    }
    
    if (empty($uid)) {
        echo json_encode(['success' => false, 'message' => 'User ID (uid) is required']);
        exit;
    }

    // Initialize Firebase API
    $firebaseAPI = new FirebaseAPI();
    
    // Set auth token
    $firebaseAPI->setAuthToken($idToken);
    
    // Create user profile in Firestore via API
    $profileData = [
        'username' => $username,
        'phoneNumber' => $phone,
        'address' => $address
    ];
    
    $result = $firebaseAPI->createProfile($profileData);
    
    if ($result && isset($result['success']) && $result['success']) {
        // Set session after successful signup
        $_SESSION['firebase_token'] = $idToken;
        $_SESSION['user_id'] = $uid;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $username;
        $_SESSION['user_role'] = 'user';
        
        echo json_encode([
            'success' => true,
            'message' => 'Account created successfully!',
            'redirect' => 'MainPage.php'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error creating user profile: ' . ($result['error'] ?? 'Unknown error')
        ]);
    }

} catch (Exception $e) {
    error_log('Signup error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Registration failed']);
}
exit;
?>