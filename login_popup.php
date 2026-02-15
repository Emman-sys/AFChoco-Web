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

    // Get Firebase ID token from request
    $idToken = $_POST['idToken'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $providedUid = $_POST['uid'] ?? ''; // Get uid if provided (from token refresh)

    if (empty($idToken) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Authentication token required']);
        exit;
    }
    
    if (empty($providedUid)) {
        echo json_encode(['success' => false, 'message' => 'User ID (uid) is required']);
        exit;
    }

    // Initialize Firebase API
    $firebaseAPI = new FirebaseAPI();
    
    // Set token temporarily to verify it
    $firebaseAPI->setAuthToken($idToken);
    
    // Verify token by trying to get profile
    $userProfile = $firebaseAPI->getProfile();
    
    // If profile doesn't exist but token is valid, the user just signed up
    // Extract uid from token (we'll get it from the error or create default profile)
    if (!$userProfile || !isset($userProfile['success'])) {
        // For new users or token refresh, create a basic session with minimal info
        // The profile will be created during first full signup flow
        $_SESSION['firebase_token'] = $idToken;
        $_SESSION['user_id'] = $providedUid; // Use provided uid if available
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = explode('@', $email)[0]; // Use email prefix as name
        $_SESSION['user_role'] = 'user';
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'redirect' => 'MainPage.php'
        ]);
        exit;
    }
    
    $user = $userProfile['user'] ?? null;
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Unable to retrieve user data']);
        exit;
    }

    // Store user info in PHP session
    $_SESSION['firebase_token'] = $idToken;
    $_SESSION['user_id'] = $user['uid'] ?? $providedUid; // Use profile uid, fallback to provided uid
    $_SESSION['user_email'] = $user['email'] ?? $email;
    $_SESSION['user_name'] = $user['username'] ?? ($user['firstName'] ?? 'User');
    $_SESSION['user_role'] = $user['role'] ?? 'user';

    echo json_encode([
        'success' => true,
        'message' => 'Login successful!',
        'redirect' => 'MainPage.php'
    ]);

} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Authentication failed']);
}
exit;
?>