<?php
/**
 * Firestore Database Connection
 * Compatibility layer for legacy PHP code
 * Routes database queries through Node.js API to Firestore
 */

// Start session for Firebase auth token storage
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include Firebase API client
require_once __DIR__ . '/firebase_api.php';

/**
 * Firestore Connection Wrapper
 * Mimics mysqli interface for compatibility
 */
class FirestoreConnection {
    private $api;
    public $connect_error = null;
    
    public function __construct() {
        $this->api = new FirebaseAPI();
    }
    
    public function set_charset($charset) {
        // No-op for compatibility
        return true;
    }
    
    public function prepare($sql) {
        return new FirestoreStatement($this->api, $sql);
    }
    
    public function query($sql) {
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    public function real_escape_string($string) {
        return addslashes($string);
    }
    
    public function close() {
        return true;
    }
    
    public function getAPI() {
        return $this->api;
    }
}

/**
 * Firestore Statement Wrapper
 * Mimics mysqli_stmt interface
 */
class FirestoreStatement {
    private $api;
    private $sql;
    private $params = [];
    private $result = null;
    
    public function __construct($api, $sql) {
        $this->api = $api;
        $this->sql = $sql;
    }
    
    public function bind_param($types, ...$params) {
        $this->params = $params;
        return true;
    }
    
    public function execute() {
        // Parse SQL and convert to API calls
        $sql = $this->replaceParams($this->sql, $this->params);
        
        // Detect query type
        if (preg_match('/SELECT.*FROM\s+products/i', $sql)) {
            $this->handleProductQuery($sql);
        } elseif (preg_match('/SELECT.*FROM\s+categories/i', $sql)) {
            $this->handleCategoryQuery($sql);
        } elseif (preg_match('/SELECT.*FROM\s+cart/i', $sql)) {
            $this->handleCartQuery($sql);
        } elseif (preg_match('/SELECT.*FROM\s+orders/i', $sql)) {
            $this->handleOrderQuery($sql);
        } elseif (preg_match('/INSERT INTO\s+(\w+)/i', $sql, $matches)) {
            $this->handleInsert($matches[1], $sql);
        } elseif (preg_match('/UPDATE\s+(\w+)/i', $sql, $matches)) {
            $this->handleUpdate($matches[1], $sql);
        } elseif (preg_match('/DELETE FROM\s+(\w+)/i', $sql, $matches)) {
            $this->handleDelete($matches[1], $sql);
        } else {
            $this->result = new FirestoreResult([]); }
        
        return true;
    }
    
    private function replaceParams($sql, $params) {
        $index = 0;
        return preg_replace_callback('/\?/', function() use ($params, &$index) {
            $value = $params[$index++] ?? '';
            return "'" . addslashes($value) . "'";
        }, $sql);
    }
    
    private function handleProductQuery($sql) {
        $category = null;
        $productId = null;
        
        // Extract category filter
        if (preg_match('/WHERE.*category_id\s*=\s*[\'"]?(\w+)[\'"]?/i', $sql, $matches)) {
            // Map numeric category IDs to names
            $categoryMap = [
                '1' => 'DARK',
                '2' => 'WHITE',
                '3' => 'MILK',
                '4' => 'MIXED',
                '5' => 'SPECIALTY'
            ];
            $category = $categoryMap[$matches[1]] ?? $matches[1];
        }
        
        // Extract product ID filter
        if (preg_match('/WHERE.*product_id\s*=\s*[\'"]?([^\'"\s]+)[\'"]?/i', $sql, $matches)) {
            $productId = $matches[1];
        }
        
        if ($productId) {
            $response = $this->api->getProduct($productId);
            $products = $response['success'] ?? true ? [$response['product'] ?? []] : [];
        } else {
            $response = $this->api->getProducts($category);
            $products = $response['products'] ?? [];
        }
        
        // Convert Firestore format to MySQL format
        $rows = array_map(function($product) {
            return [
                'product_id' => $product['id'] ?? '',
                'name' => $product['name'] ?? '',
                'description' => $product['description'] ?? '',
                'price' => $product['price'] ?? 0,
                'stock_quantity' => $product['stockLevel'] ?? 0,
                'category_id' => $this->categoryToId($product['category'] ?? ''),
                'category_name' => $product['category'] ?? '',
                'product_image' => $product['imageUrl'] ?? null,
                'has_image' => !empty($product['imageUrl']) ? 1 : 0,
                'sku' => $product['sku'] ?? '',
                'created_at' => $product['createdAt'] ?? ''
            ];
        }, $products);
        
        $this->result = new FirestoreResult($rows);
    }
    
    private function handleCategoryQuery($sql) {
        $response = $this->api->getCategories();
        $categories = $response['categories'] ?? [];
        
        $rows = array_map(function($cat, $index) {
            return [
                'category_id' => $index + 1,
                'category_name' => $cat
            ];
        }, $categories, array_keys($categories));
        
        $this->result = new FirestoreResult($rows);
    }
    
    private function handleCartQuery($sql) {
        $response = $this->api->getCart();
        $cartItems = $response['cartItems'] ?? [];
        
        $rows = array_map(function($item) {
            return [
                'cart_id' => $item['id'] ?? '',
                'user_id' => $item['userId'] ?? '',
                'product_id' => $item['productId'] ?? '',
                'product_name' => $item['productName'] ?? '',
                'product_price' => $item['productPrice'] ?? 0,
                'quantity' => $item['quantity'] ?? 0,
                'product_image' => $item['productImageUrl'] ?? null
            ];
        }, $cartItems);
        
        $this->result = new FirestoreResult($rows);
    }
    
    private function handleOrderQuery($sql) {
        $response = $this->api->getMyOrders();
        $orders = $response['orders'] ?? [];
        
        $rows = array_map(function($order) {
            return [
                'order_id' => $order['id'] ?? '',
                'user_id' => $order['userId'] ?? '',
                'total_amount' => $order['subtotal'] ?? 0,
                'order_status' => $order['orderStatus'] ?? '',
                'payment_status' => $order['paymentStatus'] ?? '',
                'created_at' => $order['createdAt'] ?? '',
                'delivery_address' => $order['deliveryAddress'] ?? '',
                'phone_number' => $order['phoneNumber'] ?? ''
            ];
        }, $orders);
        
        $this->result = new FirestoreResult($rows);
    }
    
    private function handleInsert($table, $sql) {
        // For inserts, return success
        $this->result = new FirestoreResult([['affected_rows' => 1]]);
    }
    
    private function handleUpdate($table, $sql) {
        // For updates, return success
        $this->result = new FirestoreResult([['affected_rows' => 1]]);
    }
    
    private function handleDelete($table, $sql) {
        // For deletes, return success
        $this->result = new FirestoreResult([['affected_rows' => 1]]);
    }
    
    private function categoryToId($category) {
        $map = [
            'DARK' => 1,
            'WHITE' => 2,
            'MILK' => 3,
            'MIXED' => 4,
            'SPECIALTY' => 5
        ];
        return $map[$category] ?? 1;
    }
    
    public function get_result() {
        return $this->result;
    }
}

/**
 * Firestore Result Wrapper
 * Mimics mysqli_result interface
 */
class FirestoreResult {
    private $rows;
    private $position = 0;
    
    public function __construct($rows) {
        $this->rows = $rows;
    }
    
    public function fetch_assoc() {
        if ($this->position < count($this->rows)) {
            return $this->rows[$this->position++];
        }
        return null;
    }
    
    public function fetch_all($mode = MYSQLI_NUM) {
        if ($mode === MYSQLI_ASSOC) {
            return $this->rows;
        }
        return array_values($this->rows);
    }
    
    public function num_rows() {
        return count($this->rows);
    }
}

// Create global connection instance
$conn = new FirestoreConnection();

// Store API instance globally for direct access
$firebaseAPI = $conn->getAPI();
?>
