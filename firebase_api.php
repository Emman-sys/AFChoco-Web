<?php
/**
 * Firebase API Client for PHP
 * Connects PHP frontend to Node.js/Firestore backend
 */

class FirebaseAPI {
    private $baseUrl;
    private $authToken;
    
    public function __construct($baseUrl = 'http://localhost:3000') {
        $this->baseUrl = rtrim($baseUrl, '/');
        
        // Get auth token from session if available
        if (isset($_SESSION['firebase_token'])) {
            $this->authToken = $_SESSION['firebase_token'];
        }
    }
    
    /**
     * Set authentication token
     */
    public function setAuthToken($token) {
        $this->authToken = $token;
        $_SESSION['firebase_token'] = $token;
    }
    
    /**
     * Make HTTP request to API
     */
    private function request($method, $endpoint, $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        // Build headers as a single string
        $headers = "Content-Type: application/json\r\n";
        $headers .= "Accept: application/json\r\n";
        
        // Add authorization header if token exists
        if ($this->authToken) {
            $headers .= "Authorization: Bearer " . $this->authToken . "\r\n";
        }
        
        $options = [
            'http' => [
                'method' => strtoupper($method),
                'header' => $headers,
                'ignore_errors' => true
            ]
        ];
        
        // Add request body for POST/PUT
        if ($data !== null && in_array(strtoupper($method), ['POST', 'PUT'])) {
            $options['http']['content'] = json_encode($data);
        }
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            error_log("API Request failed: $method $url");
            return ['success' => false, 'error' => 'Failed to connect to API'];
        }
        
        $decoded = json_decode($response, true);
        
        // Log if response is not valid JSON
        if ($decoded === null && $response !== 'null') {
            error_log("Invalid JSON response from $url: " . substr($response, 0, 200));
        }
        
        return $decoded ?: ['success' => false, 'error' => 'Invalid API response'];
    }
    
    /**
     * GET request
     */
    public function get($endpoint) {
        return $this->request('GET', $endpoint);
    }
    
    /**
     * POST request
     */
    public function post($endpoint, $data) {
        return $this->request('POST', $endpoint, $data);
    }
    
    /**
     * PUT request
     */
    public function put($endpoint, $data) {
        return $this->request('PUT', $endpoint, $data);
    }
    
    /**
     * DELETE request
     */
    public function delete($endpoint) {
        return $this->request('DELETE', $endpoint);
    }
    
    // ============ Product Methods ============
    
    public function getProducts($category = null, $search = null) {
        $query = [];
        if ($category) $query['category'] = $category;
        if ($search) $query['search'] = $search;
        
        $queryString = $query ? '?' . http_build_query($query) : '';
        return $this->get('/api/products' . $queryString);
    }
    
    public function getProduct($id) {
        return $this->get('/api/products/' . $id);
    }
    
    public function createProduct($data) {
        return $this->post('/api/products', $data);
    }
    
    public function updateProduct($id, $data) {
        return $this->put('/api/products/' . $id, $data);
    }
    
    public function deleteProduct($id) {
        return $this->delete('/api/products/' . $id);
    }
    
    // ============ Cart Methods ============
    
    public function getCart() {
        return $this->get('/api/cart');
    }
    
    public function addToCart($cartData) {
        // Accept either array with all fields or just productId/quantity for backwards compatibility
        if (is_string($cartData)) {
            // Old format: just productId
            $cartData = ['productId' => $cartData, 'quantity' => 1];
        } elseif (is_array($cartData) && !isset($cartData['productId'])) {
            // If productId key doesn't exist, assume first param is productId
            return ['success' => false, 'error' => 'productId is required'];
        }
        
        // Required fields: productId, productName, productPrice, productImageUrl, quantity
        // This matches the mobile app cart schema exactly
        return $this->post('/api/cart/add', $cartData);
    }
    
    public function updateCartItem($cartItemId, $quantity) {
        return $this->put('/api/cart/update/' . $cartItemId, [
            'quantity' => $quantity
        ]);
    }
    
    public function removeFromCart($cartItemId) {
        return $this->delete('/api/cart/remove/' . $cartItemId);
    }
    
    public function clearCart() {
        return $this->delete('/api/cart/clear');
    }
    
    // ============ Order Methods ============
    
    public function createOrder($data) {
        return $this->post('/api/orders/create', $data);
    }
    
    public function getMyOrders() {
        return $this->get('/api/orders/my-orders');
    }
    
    public function getOrder($orderId) {
        return $this->get('/api/orders/' . $orderId);
    }
    
    public function getAllOrders() {
        return $this->get('/api/orders/admin/all');
    }
    
    public function updateOrderStatus($orderId, $status) {
        return $this->put('/api/orders/' . $orderId . '/status', [
            'status' => $status
        ]);
    }
    
    // ============ Category Methods ============
    
    public function getCategories() {
        $response = $this->get('/api/categories');
        
        // Extract just the category values (WHITE, DARK, MILK, etc.)
        if (isset($response['categories']) && is_array($response['categories'])) {
            $categoryNames = [];
            foreach ($response['categories'] as $cat) {
                if (isset($cat['value'])) {
                    $categoryNames[] = $cat['value'];
                } elseif (is_string($cat)) {
                    $categoryNames[] = $cat;
                }
            }
            return ['success' => true, 'categories' => $categoryNames];
        }
        
        return $response;
    }
    
    public function getCategoryStats() {
        return $this->get('/api/categories/stats');
    }
    
    // ============ Comment Methods ============
    
    public function getProductComments($productId) {
        return $this->get('/api/comments/product/' . $productId);
    }
    
    public function createComment($data) {
        return $this->post('/api/comments', $data);
    }
    
    public function updateComment($commentId, $data) {
        return $this->put('/api/comments/' . $commentId, $data);
    }
    
    public function deleteComment($commentId) {
        return $this->delete('/api/comments/' . $commentId);
    }
    
    
    // ============ Auth Methods ============
    
    /**
     * Verify Firebase ID token by getting user profile
     * Returns user data if token is valid, false otherwise
     */
    public function verifyToken($idToken) {
        // Temporarily set token
        $oldToken = $this->authToken;
        $this->authToken = $idToken;
        
        // Try to get profile - if successful, token is valid
        $result = $this->get('/api/auth/profile');
        
        // Restore old token
        $this->authToken = $oldToken;
        
        if ($result && isset($result['success']) && $result['success']) {
            return $result['user'];
        }
        
        return false;
    }
    
    /**
     * Get user profile from Firestore
     */
    public function getUserProfile($userId = null) {
        $result = $this->getProfile();
        
        if ($result && isset($result['success']) && $result['success']) {
            return $result['user'];
        }
        
        return false;
    }
    
    /**
     * Create user profile in Firestore after Firebase Auth signup
     */
    public function createUserProfile($userId, $profileData, $idToken) {
        // Set token for this request
        $oldToken = $this->authToken;
        $this->authToken = $idToken;
        
        $result = $this->createProfile($profileData);
        
        // Restore old token
        $this->authToken = $oldToken;
        
        return $result;
    }
    
    public function createProfile($data) {
        return $this->post('/api/auth/create-profile', $data);
    }
    
    public function getProfile() {
        return $this->get('/api/auth/profile');
    }
    
    public function updateProfile($data) {
        return $this->put('/api/auth/profile', $data);
    }
}

// Global API instance
$firebaseAPI = new FirebaseAPI();
?>
