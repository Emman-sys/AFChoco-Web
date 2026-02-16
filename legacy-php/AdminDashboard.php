<?php
session_start();
require_once '../firebase_api.php';

// Initialize Firebase API
$firebaseAPI = new FirebaseAPI('http://localhost:3000');

// Check if user is trying to login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // Simple admin authentication (use Firebase Auth in production)
    if ($email === 'admin@af.com' || $email === 'admin@test.com' || $email === 'admin') {
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_name'] = 'Admin User';
        $_SESSION['admin_email'] = $email;
        $_SESSION['role'] = 'admin';
        
        error_log("🔥 ADMIN LOGIN - Firestore Backend");
        header('Location: AdminDashboard.php');
        exit();
    }
    
    $loginError = "Invalid admin credentials";
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: AdminDashboard.php');
    exit();
}

// Get messages from session and clear them
$productSuccess = '';
$productError = '';

if (isset($_SESSION['product_success'])) {
    $productSuccess = $_SESSION['product_success'];
    unset($_SESSION['product_success']);
}

if (isset($_SESSION['product_error'])) {
    $productError = $_SESSION['product_error'];
    unset($_SESSION['product_error']);
}

// Handle product addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product']) && isset($_SESSION['admin_id'])) {
    error_log("🔥 ADD PRODUCT ATTEMPT - Firestore Backend");
    
    $name = trim($_POST['product_name']);
    $description = trim($_POST['product_description']);
    $price = floatval($_POST['product_price']);
    $stock = intval($_POST['product_stock']);
    $category = $_POST['category_name'] ?? 'SPECIALTY'; // Use category name not ID
    
    // Validate inputs
    if (empty($name) || empty($description) || $price <= 0 || $stock < 0) {
        $_SESSION['product_error'] = "Please fill all fields with valid data.";
        error_log("❌ Validation failed");
    } else {
        try {
            // For now, use placeholder image URL if no image uploaded
            // Firebase Storage integration can be added later
            $imageUrl = 'https://via.placeholder.com/400x300?text=' . urlencode($name);
            
            // Create product data following Firestore schema
            $productData = [
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'stockLevel' => $stock,
                'category' => strtoupper($category),
                'sku' => 'CHO-' . strtoupper(substr($category, 0, 3)) . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
                'imageUrl' => $imageUrl,
                'salesCount' => 0
            ];
            
            error_log("🔄 Creating product in Firestore: " . json_encode($productData));
            
            $result = $firebaseAPI->createProduct($productData);
            
            if ($result['success'] ?? false) {
                $_SESSION['product_success'] = "Product added successfully to Firestore!";
                error_log("✅ Product added to Firestore");
            } else {
                $_SESSION['product_error'] = "Failed to add product: " . ($result['error'] ?? 'Unknown error');
                error_log("❌ Firestore error: " . ($result['error'] ?? 'Unknown'));
            }
        } catch (Exception $e) {
            $_SESSION['product_error'] = "Error adding product: " . $e->getMessage();
            error_log("❌ Exception: " . $e->getMessage());
        }
    }
    
    header('Location: AdminDashboard.php?product_added=1&show_modal=1');
    exit();
}

// Image compression function - handles large images and MySQL packet size limitations
function compressImage($sourcePath, $mimeType) {
    error_log("🔍 Starting image compression - File: " . $sourcePath . ", Type: " . $mimeType);
    
    // Check if GD extension is available for image processing
    if (!extension_loaded('gd')) {
        error_log("⚠️ GD extension not available, using original image");
        
        // Fallback: use original image data but check size to prevent MySQL errors
        $imageData = file_get_contents($sourcePath);
        
        // 4MB fallback limit - prevents "Got a packet bigger than 'max_allowed_packet'" MySQL error
        // Most default MySQL configurations have max_allowed_packet = 1MB-16MB
        if (strlen($imageData) > 4000000) { // 4MB limit
            error_log("❌ Image too large without compression: " . strlen($imageData) . " bytes");
            return false; // Reject image if too large and can't compress
        }
        
        error_log("✅ Using original image (no GD): " . strlen($imageData) . " bytes");
        return $imageData;
    }
    
    error_log("✅ GD extension available, proceeding with compression");
    
    try {
        // Create image resource based on MIME type for processing
        switch ($mimeType) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($sourcePath);
                break;
            default:
                error_log("❌ Unsupported image type: " . $mimeType);
                return false;
        }
        
        // If image creation failed, fallback to original with size check
        if (!$image) {
            error_log("❌ Failed to create image resource from: " . $sourcePath);
            $imageData = file_get_contents($sourcePath);
            
            // Same 4MB limit for fallback scenario
            if (strlen($imageData) > 4000000) {
                error_log("❌ Fallback image too large: " . strlen($imageData) . " bytes");
                return false;
            }
            error_log("⚠️ Using original image as fallback: " . strlen($imageData) . " bytes");
            return $imageData;
        }
        
        // Get original image dimensions for resizing calculations
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);
        error_log("📐 Original dimensions: " . $originalWidth . "x" . $originalHeight);
        
        // Set maximum dimensions - balances quality vs file size
        // Larger dimensions = better quality but bigger file size
        $maxWidth = 800;  // Good for product display
        $maxHeight = 600; // Maintains reasonable aspect ratios
        
        // Calculate new dimensions while preserving aspect ratio
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = (int)($originalWidth * $ratio);
        $newHeight = (int)($originalHeight * $ratio);
        error_log("📐 New dimensions: " . $newWidth . "x" . $newHeight . " (ratio: " . $ratio . ")");
        
        // Create new blank image canvas with calculated dimensions
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF images
        if ($mimeType === 'image/png') {
            // PNG transparency handling
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
            error_log("🔍 PNG transparency preserved");
        } elseif ($mimeType === 'image/gif') {
            // GIF transparency handling
            $transparentIndex = imagecolortransparent($image);
            if ($transparentIndex >= 0) {
                $transparentColor = imagecolorsforindex($image, $transparentIndex);
                $transparentNew = imagecolorallocate($newImage, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
                imagefill($newImage, 0, 0, $transparentNew);
                imagecolortransparent($newImage, $transparentNew);
            }
            error_log("🔍 GIF transparency handled");
        }
        
        // Resize original image to new dimensions using resampling for quality
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
        error_log("✅ Image resized successfully");
        
        // Convert processed image back to binary data with compression
        ob_start(); // Start output buffering to capture image data
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($newImage, null, 75); // 75% quality - balance of size vs quality
                break;
            case 'image/png':
                imagepng($newImage, null, 6); // Compression level 6 (0-9, 9=max compression)
                break;
            case 'image/gif':
                imagegif($newImage, null); // GIF doesn't have quality settings
                break;
        }
        $imageData = ob_get_contents(); // Get the buffered image data
        ob_end_clean(); // Clean the output buffer
        
        // Free up memory by destroying image resources
        imagedestroy($image);
        imagedestroy($newImage);
        
        // Final size check - 1.5MB limit prevents MySQL packet errors
        // Even with compression, some images might still be large
        if (strlen($imageData) > 1500000) { // 1.5MB limit
            error_log("❌ Compressed image still too large: " . strlen($imageData) . " bytes");
            return false; // Reject if still too large after compression
        }
        
        error_log("✅ Image compressed successfully: " . strlen($imageData) . " bytes");
        return $imageData;
        
    } catch (Exception $e) {
        error_log("❌ Image compression error: " . $e->getMessage());
        
        // Final fallback: try original image with size check
        try {
            $imageData = file_get_contents($sourcePath);
            
            // 4MB limit for this fallback too
            if (strlen($imageData) > 4000000) {
                error_log("❌ Fallback image too large: " . strlen($imageData) . " bytes");
                return false;
            }
            
            error_log("⚠️ Using original image as fallback after error: " . strlen($imageData) . " bytes");
            return $imageData;
        } catch (Exception $fallbackError) {
            error_log("❌ Fallback also failed: " . $fallbackError->getMessage());
            return false;
        }
    }
}

// Function to ensure database connection is alive
function ensureConnection($conn) {
    if (!$conn->ping()) {
        error_log("❌ Database connection lost, attempting to reconnect...");
        $conn->close();
        
        // Reconnect using the same credentials
        require 'db_connect.php';
        return $conn;
    }
    return $conn;
}

// Handle product editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product']) && isset($_SESSION['admin_id'])) {
    error_log("🔥 EDIT PRODUCT ATTEMPT - Firestore Backend - ID: " . $_POST['product_id']);
    
    $product_id = trim($_POST['product_id']); // Keep as string for Firestore document ID
    $name = trim($_POST['product_name']);
    $description = trim($_POST['product_description']);
    $price = floatval($_POST['product_price']);
    $stock = intval($_POST['product_stock']);
    $category = $_POST['category_name'] ?? 'SPECIALTY'; // Use category name not ID
    
    // Validate inputs
    if (empty($product_id) || empty($name) || empty($description) || $price <= 0 || $stock < 0) {
        $_SESSION['product_error'] = "Please fill all fields with valid data.";
        error_log("❌ Validation failed");
    } else {
        try {
            // For now, use placeholder for image URL if new image uploaded
            // Firebase Storage integration can be added later
            $imageUrl = null;
            
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                error_log("📸 New image upload for edit: " . $_FILES['product_image']['name']);
                $imageUrl = 'https://via.placeholder.com/400x300?text=' . urlencode($name);
            }
            
            // Create update data following Firestore schema
            $updateData = [
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'stockLevel' => $stock,
                'category' => strtoupper($category)
            ];
            
            // Only include imageUrl if new image uploaded
            if ($imageUrl !== null) {
                $updateData['imageUrl'] = $imageUrl;
            }
            
            error_log("🔄 Updating product in Firestore: " . json_encode($updateData));
            
            $result = $firebaseAPI->updateProduct($product_id, $updateData);
            
            if ($result['success'] ?? false) {
                $_SESSION['product_success'] = "Product updated successfully in Firestore!";
                error_log("✅ Product updated in Firestore");
            } else {
                $_SESSION['product_error'] = "Failed to update product: " . ($result['error'] ?? 'Unknown error');
                error_log("❌ Firestore error: " . ($result['error'] ?? 'Unknown'));
            }
        } catch (Exception $e) {
            $_SESSION['product_error'] = "Error updating product: " . $e->getMessage();
            error_log("❌ Exception: " . $e->getMessage());
        }
    }
    
    header('Location: AdminDashboard.php?product_updated=1&show_modal=1');
    exit();
}

// Handle product deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product']) && isset($_SESSION['admin_id'])) {
    $product_id = $_POST['product_id'];
    
    try {
        $result = $firebaseAPI->deleteProduct($product_id);
        
        if ($result['success'] ?? false) {
            $_SESSION['product_success'] = "Product deleted successfully from Firestore!";
        } else {
            $_SESSION['product_error'] = "Failed to delete product: " . ($result['error'] ?? 'Unknown error');
        }
    } catch (Exception $e) {
        $_SESSION['product_error'] = "Error deleting product: " . $e->getMessage();
    }
    
    header('Location: AdminDashboard.php?product_deleted=1');
    exit();
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_order']) && isset($_SESSION['admin_id'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'] ?? 'DELIVERED';
    
    try {
        $result = $firebaseAPI->updateOrderStatus($order_id, $new_status);
        
        if ($result['success'] ?? false) {
            $_SESSION['product_success'] = "Order status updated successfully!";
        } else {
            $_SESSION['product_error'] = "Failed to update order: " . ($result['error'] ?? 'Unknown error');
        }
    } catch (Exception $e) {
        $_SESSION['product_error'] = "Error updating order: " . $e->getMessage();
    }
    
    header('Location: AdminDashboard.php?order_completed=1');
    exit();
}

// Get categories for dropdown (Firestore categories)
function getCategories($firebaseAPI) {
    // Firestore uses predefined categories from mobile app schema
    return [
        ['category_id' => 'DARK', 'category_name' => 'Dark Chocolate'],
        ['category_id' => 'MILK', 'category_name' => 'Milk Chocolate'],
        ['category_id' => 'WHITE', 'category_name' => 'White Chocolate'],
        ['category_id' => 'MIXED', 'category_name' => 'Mixed Chocolate'],
        ['category_id' => 'SPECIALTY', 'category_name' => 'Specialty']
    ];
}

// Function to get all products from Firestore
function getAllProducts($firebaseAPI) {
    $products = [];
    try {
        $response = $firebaseAPI->getProducts();
        
        if ($response['success'] ?? false) {
            $firestoreProducts = $response['products'] ?? [];
            
            foreach ($firestoreProducts as $product) {
                $products[] = [
                    'product_id' => $product['id'] ?? '',
                    'name' => $product['name'] ?? '',
                    'description' => $product['description'] ?? '',
                    'price' => (float)($product['price'] ?? 0),
                    'stock_quantity' => (int)($product['stockLevel'] ?? 0),
                    'category_id' => $product['category'] ?? 'SPECIALTY',
                    'product_image' => null, // Not using BLOB anymore
                    'image_type' => null,
                    'image_url' => $product['imageUrl'] ?? '',
                    'sales_count' => (int)($product['salesCount'] ?? 0),
                    'category_name' => ucfirst(strtolower($product['category'] ?? 'Specialty'))
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting products from Firestore: " . $e->getMessage());
    }
    
    return $products;
}

// Function to get pending orders from Firestore
function getPendingOrders($firebaseAPI) {
    $orders = [];
    try {
        $response = $firebaseAPI->getAllOrders();
        
        if ($response['success'] ?? false) {
            $allOrders = $response['orders'] ?? [];
            
            // Filter for pending orders
            foreach ($allOrders as $order) {
                $status = $order['orderStatus'] ?? 'PENDING';
                if (in_array($status, ['PENDING', 'PAID'])) {
                    $timestamp = $order['createdAt']['_seconds'] ?? time();
                    $orders[] = [
                        'payment_id' => $order['id'] ?? '',
                        'order_id' => $order['id'] ?? '',
                        'payment_method' => 'Online',
                        'payment_status' => strtolower($status),
                        'amount_paid' => (float)($order['totalAmount'] ?? 0),
                        'payment_date' => date('Y-m-d H:i:s', $timestamp),
                        'delivery_address' => $order['deliveryAddress'] ?? '',
                        'customer_name' => $order['userName'] ?? 'Unknown',
                        'customer_email' => $order['userEmail'] ?? ''
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error getting orders from Firestore: " . $e->getMessage());
    }
    
    return $orders;
}

// Get dashboard stats from Firestore
function getDashboardStats($firebaseAPI) {
    $stats = [
        'total_sales' => 0,
        'daily_sales' => 0,
        'yesterday_sales' => 0,
        'sales_change_percent' => 0,
        'total_customers' => 0,
        'last_month_customers' => 0,
        'customer_change_percent' => 0,
        'total_products' => 0,
        'last_month_products' => 0,
        'product_change_percent' => 0,
        'total_orders' => 0,
        'total_deliveries' => 0
    ];
    
    try {
        // Get products from Firestore
        $productsResponse = $firebaseAPI->getProducts();
        if ($productsResponse['success'] ?? false) {
            $products = $productsResponse['products'] ?? [];
            $stats['total_products'] = count($products);
        }
        
        // Get orders from Firestore
        $ordersResponse = $firebaseAPI->getAllOrders();
        if ($ordersResponse['success'] ?? false) {
            $orders = $ordersResponse['orders'] ?? [];
            $stats['total_orders'] = count($orders);
            
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            
            foreach ($orders as $order) {
                $amount = (float)($order['totalAmount'] ?? 0);
                $stats['total_sales'] += $amount;
                
                // Check order status for deliveries
                if (($order['orderStatus'] ?? '') === 'DELIVERED') {
                    $stats['total_deliveries']++;
                }
                
                // Calculate daily sales
                $timestamp = $order['createdAt']['_seconds'] ?? 0;
                $orderDate = date('Y-m-d', $timestamp);
                
                if ($orderDate === $today) {
                    $stats['daily_sales'] += $amount;
                } elseif ($orderDate === $yesterday) {
                    $stats['yesterday_sales'] += $amount;
                }
            }
        }
        
        // Calculate daily sales percentage change
        if ($stats['yesterday_sales'] > 0) {
            $stats['sales_change_percent'] = (($stats['daily_sales'] - $stats['yesterday_sales']) / $stats['yesterday_sales']) * 100;
        } else if ($stats['daily_sales'] > 0) {
            $stats['sales_change_percent'] = 100;
        }
        
        // Note: Customer count would require users collection access
        // For now, estimate from unique order users
        $uniqueUsers = [];
        foreach (($ordersResponse['orders'] ?? []) as $order) {
            $userId = $order['userId'] ?? '';
            if ($userId) {
                $uniqueUsers[$userId] = true;
            }
        }
        $stats['total_customers'] = count($uniqueUsers);
        
    } catch (Exception $e) {
        error_log("Error getting Firestore stats: " . $e->getMessage());
    }
    
    return $stats;
}

// Get monthly sales data for chart from Firestore
function getMonthlySalesData($firebaseAPI) {
    $monthlyData = array_fill(0, 12, 0);
    
    try {
        $response = $firebaseAPI->getAllOrders();
        
        if ($response['success'] ?? false) {
            $orders = $response['orders'] ?? [];
            
            foreach ($orders as $order) {
                $timestamp = $order['createdAt']['_seconds'] ?? 0;
                $year = date('Y', $timestamp);
                $month = (int)date('n', $timestamp); // 1-12
                
                // Only count orders from 2025 and 2026
                if ($year == 2025 || $year == 2026) {
                    $amount = (float)($order['totalAmount'] ?? 0);
                    $monthIndex = $month - 1; // Convert to 0-based index
                    
                    if ($monthIndex >= 0 && $monthIndex < 12) {
                        $monthlyData[$monthIndex] += $amount;
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Error getting monthly sales from Firestore: " . $e->getMessage());
    }
    
    return $monthlyData;
}

// 📈 PREDICTIVE ANALYTICS - Revenue Forecast
function getRevenueForecast($firebaseAPI) {
    $forecast = [];
    
    try {
        $response = $firebaseAPI->getAllOrders();
        
        if ($response['success'] ?? false) {
            $orders = $response['orders'] ?? [];
            
            // Get last 30 days of sales data
            $dailySales = [];
            $today = time();
            $thirtyDaysAgo = strtotime('-30 days');
            
            foreach ($orders as $order) {
                $timestamp = $order['createdAt']['_seconds'] ?? 0;
                if ($timestamp >= $thirtyDaysAgo) {
                    $date = date('Y-m-d', $timestamp);
                    if (!isset($dailySales[$date])) {
                        $dailySales[$date] = 0;
                    }
                    $dailySales[$date] += (float)($order['totalAmount'] ?? 0);
                }
            }
            
            // Calculate moving average for prediction
            $salesValues = array_values($dailySales);
            $avgDailySales = count($salesValues) > 0 ? array_sum($salesValues) / count($salesValues) : 0;
            
            // Predict next 7 days
            $forecast = [];
            for ($i = 1; $i <= 7; $i++) {
                $futureDate = date('M d', strtotime("+$i days"));
                // Add ±10% variation for realistic prediction
                $variation = ($avgDailySales * 0.1) * (rand(-100, 100) / 100);
                $forecast[$futureDate] = max(0, $avgDailySales + $variation);
            }
        }
    } catch (Exception $e) {
        error_log("Error calculating revenue forecast: " . $e->getMessage());
    }
    
    return $forecast;
}

// 🎯 PREDICTIVE ANALYTICS - Automated Recommendations
function getRecommendations($firebaseAPI) {
    $recommendations = [];
    
    try {
        $productsResponse = $firebaseAPI->getProducts();
        
        if ($productsResponse['success'] ?? false) {
            $products = $productsResponse['products'] ?? [];
            
            // Calculate average sales
            $totalSales = 0;
            $productCount = 0;
            foreach ($products as $product) {
                $totalSales += ($product['salesCount'] ?? 0);
                $productCount++;
            }
            $avgSales = $productCount > 0 ? $totalSales / $productCount : 0;
            
            // Generate recommendations (matching mobile app logic)
            foreach ($products as $product) {
                $stockLevel = $product['stockLevel'] ?? 0;
                $salesCount = $product['salesCount'] ?? 0;
                $name = $product['name'] ?? 'Unknown Product';
                
                // 1. LOW STOCK (High Priority)
                if ($stockLevel < 10) {
                    $recommendations[] = [
                        'type' => 'RESTOCK',
                        'priority' => 'HIGH',
                        'product' => $name,
                        'message' => "Restock $name - only $stockLevel units left",
                        'color' => '#dc3545',
                        'icon' => '⚠️'
                    ];
                }
                
                // 2. NO SALES (Medium Priority)
                elseif ($salesCount == 0) {
                    $recommendations[] = [
                        'type' => 'PROMOTE',
                        'priority' => 'MEDIUM',
                        'product' => $name,
                        'message' => "Promote $name - no sales recorded yet",
                        'color' => '#fd7e14',
                        'icon' => '📢'
                    ];
                }
                
                // 3. LOW SALES (Medium Priority)
                elseif ($salesCount < ($avgSales * 0.5) && $avgSales > 0) {
                    $recommendations[] = [
                        'type' => 'DISCOUNT',
                        'priority' => 'MEDIUM',
                        'product' => $name,
                        'message' => "Consider discount for $name - sales below average",
                        'color' => '#ffc107',
                        'icon' => '💰'
                    ];
                }
                
                // 4. HIGH STOCK + LOW SALES (Low Priority)
                elseif ($stockLevel > 50 && $salesCount < $avgSales) {
                    $recommendations[] = [
                        'type' => 'CLEARANCE',
                        'priority' => 'LOW',
                        'product' => $name,
                        'message' => "High inventory on $name - consider promotion",
                        'color' => '#6c757d',
                        'icon' => '📦'
                    ];
                }
            }
            
            // Sort by priority (HIGH -> MEDIUM -> LOW)
            usort($recommendations, function($a, $b) {
                $priorities = ['HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3];
                return $priorities[$a['priority']] - $priorities[$b['priority']];
            });
        }
    } catch (Exception $e) {
        error_log("Error generating recommendations: " . $e->getMessage());
    }
    
    return $recommendations;
}

// 📊 PREDICTIVE ANALYTICS - Trending Products
function getTrendingProducts($firebaseAPI) {
    $trending = ['up' => [], 'down' => []];
    
    try {
        $productsResponse = $firebaseAPI->getProducts();
        
        if ($productsResponse['success'] ?? false) {
            $products = $productsResponse['products'] ?? [];
            
            // Sort by sales count
            usort($products, function($a, $b) {
                return ($b['salesCount'] ?? 0) - ($a['salesCount'] ?? 0);
            });
            
            // Top 3 trending up
            $trending['up'] = array_slice($products, 0, 3);
            
            // Bottom 3 trending down (lowest sales, excluding zero sales)
            $lowSalesProducts = array_filter($products, function($p) {
                return ($p['salesCount'] ?? 0) > 0;
            });
            usort($lowSalesProducts, function($a, $b) {
                return ($a['salesCount'] ?? 0) - ($b['salesCount'] ?? 0);
            });
            $trending['down'] = array_slice($lowSalesProducts, 0, 3);
        }
    } catch (Exception $e) {
        error_log("Error calculating trending products: " . $e->getMessage());
    }
    
    return $trending;
}

// 🔮 PREDICTIVE ANALYTICS - Stock Depletion Forecast
function getStockDepletionForecast($firebaseAPI) {
    $depletionWarnings = [];
    
    try {
        $productsResponse = $firebaseAPI->getProducts();
        $ordersResponse = $firebaseAPI->getAllOrders();
        
        if (($productsResponse['success'] ?? false) && ($ordersResponse['success'] ?? false)) {
            $products = $productsResponse['products'] ?? [];
            $orders = $ordersResponse['orders'] ?? [];
            
            // Calculate daily sales rate for each product (last 30 days)
            $thirtyDaysAgo = strtotime('-30 days');
            $productSalesRate = [];
            
            foreach ($orders as $order) {
                $timestamp = $order['createdAt']['_seconds'] ?? 0;
                if ($timestamp >= $thirtyDaysAgo) {
                    foreach (($order['items'] ?? []) as $item) {
                        $productId = $item['productId'] ?? '';
                        $quantity = $item['quantity'] ?? 0;
                        
                        if (!isset($productSalesRate[$productId])) {
                            $productSalesRate[$productId] = 0;
                        }
                        $productSalesRate[$productId] += $quantity;
                    }
                }
            }
            
            // Calculate depletion forecast
            foreach ($products as $product) {
                $productId = $product['id'] ?? '';
                $stockLevel = $product['stockLevel'] ?? 0;
                $totalSold = $productSalesRate[$productId] ?? 0;
                
                if ($totalSold > 0 && $stockLevel > 0) {
                    $dailySalesRate = $totalSold / 30; // Average per day
                    $daysUntilEmpty = $stockLevel / $dailySalesRate;
                    
                    // Warn if less than 14 days of stock
                    if ($daysUntilEmpty < 14) {
                        $depletionWarnings[] = [
                            'product' => $product['name'] ?? 'Unknown',
                            'stock' => $stockLevel,
                            'daysLeft' => round($daysUntilEmpty),
                            'dailyRate' => round($dailySalesRate, 1)
                        ];
                    }
                }
            }
            
            // Sort by days left (ascending)
            usort($depletionWarnings, function($a, $b) {
                return $a['daysLeft'] - $b['daysLeft'];
            });
        }
    } catch (Exception $e) {
        error_log("Error calculating stock depletion: " . $e->getMessage());
    }
    
    return $depletionWarnings;
}

$categories = getCategories($firebaseAPI);
$allProducts = getAllProducts($firebaseAPI);
$pendingOrders = getPendingOrders($firebaseAPI);
$stats = getDashboardStats($firebaseAPI);
$monthlySalesData = getMonthlySalesData($firebaseAPI);

// 🔮 Get Predictive Analytics
$revenueForecast = getRevenueForecast($firebaseAPI);
$recommendations = getRecommendations($firebaseAPI);
$trendingProducts = getTrendingProducts($firebaseAPI);
$stockDepletion = getStockDepletionForecast($firebaseAPI);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
     body{
        background:url("https://cdn.glitch.global/585aee42-d89c-4ece-870c-5b01fc1bab61/image%203.png?v=1747320934399");
        background-position:center;
        background-size:cover;
        background-attachment:fixed;
        font-family: 'Poppins', serif;
        min-height: 100vh;
        width:100%;
        overflow-x: hidden;
    }

      .brand-name {
      left:50px;
      font-size: clamp(2rem, 5vw, 3rem);
      font-weight: bold;
      color: white;
     }

    .Admin{
      position:absolute;
      left:145px;
      top:40px;
      font-size: 25px;
      font-weight: bold;
      color: white;
    }

    /* existing dashboard styles are here */
    .box1{
        position:absolute;
        left: 135px;
        top: 168px;
        width:213px;
        height: 269px;
        background: linear-gradient(to bottom, #D997D5,#FFFFFF);
        border-radius:20px;
        cursor: pointer;
        transition: transform 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: space-between;
        padding: 20px;
        box-sizing: border-box;
    }
    
    .box1:hover, .box2:hover, .box3:hover, .box4:hover, .box5:hover {
        transform: translateY(-5px);
    }
    
    .box2{
        position:absolute;
        left: 375px;
        top: 168px;
        width:213px;
        height: 269px;
        background: linear-gradient(to bottom, #7B87C6,#FFFFFF);
        border-radius:20px;
        cursor: pointer;
        transition: transform 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: space-between;
        padding: 20px;
        box-sizing: border-box;
    }

    .box3{
        position:absolute;
        left: 615px;
        top: 168px;
        width:213px;
        height: 269px;
        background: linear-gradient(to bottom, #7BC68F,#FFFFFF);
        border-radius:20px;
        cursor: pointer;
        transition: transform 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: space-between;
        padding: 20px;
        box-sizing: border-box;
    }

    .box4{
        position:absolute;
        left: 855px;
        top: 168px;
        width:213px;
        height: 269px;
        background: linear-gradient(to bottom, #C6B27B,#FFFFFF);
        border-radius:20px;
        cursor: pointer;
        transition: transform 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: space-between;
        padding: 20px;
        box-sizing: border-box;
    }

    .box5{
        position:absolute;
        left: 1112px;
        top: 168px;
        width:213px;
        height: 269px;
        background: linear-gradient(to bottom, #6A34D6,#FFFFFF);
        border-radius:20px;
        cursor: pointer;
        transition: transform 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: space-between;
        padding: 20px;
        box-sizing: border-box;
    }

    /* Update circle styles to be relative positioning */
    .dashboard-icon {
        width: 80px;
        height: 80px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        margin-top: 10px;
    }

    .dashboard-icon::after {
        content: '';
        position: absolute;
        width: 65px;
        height: 65px;
        border-radius: 50%;
        z-index: 1;
    }

    .box1 .dashboard-icon::after { background: #D997D5; }
    .box2 .dashboard-icon::after { background: #7B87C6; }
    .box3 .dashboard-icon::after { background: #7BC68F; }
    .box4 .dashboard-icon::after { background: #C6B27B; }
    .box5 .dashboard-icon::after { background: #6A34D6; }

    .dashboard-icon img {
        width: 35px;
        height: 35px;
        z-index: 2;
        position: relative;
    }

    /* Update text styles to work inside containers */
    .box-title {
        color: black;
        font-size: 18px;
        font-weight: 500;
        text-align: center;
        margin: 10px 0 5px 0;
    }

    .box-percentage {
        color: #8D7D7D;
        font-size: 12px;
        font-weight: 300;
        text-align: center;
        margin: 0;
    }

    .box-value {
        color: black;
        font-size: 24px;
        font-weight: 600;
        text-align: center;
        margin: 5px 0 10px 0;
    }

    .rectangle2{
        position:absolute;
        left: 150px;
        top: 500px;
        width: 1170px;
        height: 400px;
        background: linear-gradient(to bottom, #B85CD7, #DDCFCF);
        border-radius:14px;
        z-index: 1;
        display: flex;
        flex-direction: column;
        padding: 20px;
        box-sizing: border-box;
    }

    /* ADMIN LOGIN MODAL STYLES - Matching Welcome.php */
    .popup-overlay {
      display: <?php echo !isset($_SESSION['admin_id']) ? 'block' : 'none'; ?>;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1000;
    }

    .popup-container {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 90%;
      max-width: 500px;
      padding: 40px;
      border-radius: 15px;
      background-color: rgba(217, 217, 217, 0.1);
      backdrop-filter: blur(10px);
    }

    .popup-close {
      position: absolute;
      top: 15px;
      right: 20px;
      background: none;
      border: none;
      color: #fff;
      font-size: 24px;
      cursor: pointer;
    }

    .form-title {
      color: #fff;
      font-family: Poppins, sans-serif;
      font-size: 24px;
      font-weight: 700;
      text-align: center;
      margin-bottom: 30px;
    }

    .login-form {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .form-group {
      position: relative;
    }

    .input-label {
      color: #fff;
      font-family: Poppins;
      font-size: 16px;
      font-weight: 700;
      margin-bottom: 8px;
      display: block;
    }

    .form-input {
      width: 100%;
      height: 50px;
      border-radius: 10px;
      border: 1px solid rgba(216, 204, 204, 0.61);
      background-color: rgba(216, 204, 204, 0.61);
      padding: 0 15px;
      font-size: 14px;
      color: #000;
      box-sizing: border-box;
    }

    .login-button {
      width: 100%;
      height: 50px;
      border-radius: 10px;
      background-color: #fff;
      color: #000;
      font-size: 16px;
      font-weight: 700;
      border: none;
      cursor: pointer;
      margin-top: 20px;
    }

    .signup-text {
      text-align: center;
      font-family: Poppins, sans-serif;
      font-size: 14px;
      color: #fff;
      margin-top: 15px;
    }

    .signup-text a {
      color: #fff;
      text-decoration: underline;
      cursor: pointer;
    }

    .error-message {
      background-color: rgba(192, 57, 43, 0.7);
      color: white;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 15px;
      text-align: center;
    }

    .admin-welcome {
        position: absolute;
        right: 100px;
        top: 20px;
        color: white;
        font-size: 14px;
    }

    .admin-logout {
        position: absolute;
        right: 50px;
        top: 60px;
        background: #dc3545;
        color: white;
        padding: 8px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        font-size: 12px;
    }

    .admin-logout:hover {
        background: #c82333;
    }

    /* Hide dashboard content when not logged in */
    .dashboard-content {
        opacity: <?php echo isset($_SESSION['admin_id']) ? '1' : '0.3'; ?>;
        pointer-events: <?php echo isset($_SESSION['admin_id']) ? 'auto' : 'none'; ?>;
        transition: opacity 0.3s ease;
    }

    .password-toggle {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #333;
        cursor: pointer;
        font-size: 12px;
    }

    /* Product Management Modal Styles */
    .product-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        z-index: 2000;
    }

    .product-modal-container {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 30px;
        border-radius: 15px;
        background: linear-gradient(to bottom, #D997D5, #FFFFFF);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .product-modal-close {
        position: absolute;
        top: 15px;
        right: 20px;
        background: none;
        border: none;
        color: #333;
        font-size: 24px;
        cursor: pointer;
        font-weight: bold;
    }

    .product-form-title {
        color: #333;
        font-family: Poppins, sans-serif;
        font-size: 24px;
        font-weight: 700;
        text-align: center;
        margin-bottom: 25px;
    }

    .product-form {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .product-form-group {
        display: flex;
        flex-direction: column;
    }

    .product-input-label {
        color: #333;
        font-family: Poppins;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .product-form-input, .product-form-select, .product-form-textarea {
        width: 100%;
        padding: 10px;
        border: 2px solid rgba(0, 0, 0, 0.1);
        border-radius: 8px;
        font-size: 14px;
        font-family: Poppins;
        box-sizing: border-box;
        background: rgba(255, 255, 255, 0.9);
    }

    .product-form-textarea {
        min-height: 80px;
        resize: vertical;
    }

    .product-form-input:focus, .product-form-select:focus, .product-form-textarea:focus {
        outline: none;
        border-color: #D997D5;
        box-shadow: 0 0 5px rgba(217, 151, 213, 0.3);
    }

    .product-submit-btn {
        background: linear-gradient(45deg, #D997D5, #B85CD7);
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        margin-top: 10px;
        transition: transform 0.2s ease;
    }

    .product-submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(217, 151, 213, 0.4);
    }

    .product-success {
        background-color: rgba(76, 175, 80, 0.8);
        color: white;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
        text-align: center;
    }

    .product-error {
        background-color: rgba(244, 67, 54, 0.8);
        color: white;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 15px;
        text-align: center;
    }

    .add-product-btn {
        position: absolute;
        right: 50px;
        top: 100px;
        background: linear-gradient(45deg, #D997D5, #B85CD7);
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: transform 0.2s ease;
    }

    .add-product-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(217, 151, 213, 0.4);
    }

    /* Products Table Styles */
    .products-table-section {
        position: absolute;
        left: 150px;
        top: 950px;
        width: 1170px;
        background: rgba(255, 255, 255, 0.95);
        border-radius: 14px;
        padding: 30px;
        box-sizing: border-box;
        margin-bottom: 50px;
        z-index: 10;
    }

    .products-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .products-table th {
        background: linear-gradient(45deg, #D997D5, #B85CD7);
        color: white;
        padding: 15px 12px;
        text-align: left;
        font-weight: 600;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        cursor: pointer;
        user-select: none;
        position: relative;
        transition: background 0.3s ease;
    }

    .products-table th:hover {
        background: linear-gradient(45deg, #B85CD7, #9A4AC7);
    }

    .products-table th.sortable::after {
        content: '↕';
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 12px;
        opacity: 0.7;
    }

    .products-table th.sort-asc::after {
        content: '↑';
        opacity: 1;
    }

    .products-table th.sort-desc::after {
        content: '↓';
        opacity: 1;
    }

    .products-table th.non-sortable {
        cursor: default;
    }

    .products-table th.non-sortable:hover {
        background: linear-gradient(45deg, #D997D5, #B85CD7);
    }

    .products-table td {
        padding: 12px;
        border-bottom: 1px solid #e9ecef;
        font-size: 14px;
        color: #333;
    }

    .products-table tr:hover {
        background-color: #f8f9fa;
    }

    .table-actions {
        display: flex;
        gap: 8px;
    }

    .btn-edit, .btn-delete {
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-edit {
        background: #007bff;
        color: white;
    }

    .btn-edit:hover {
        background: #0056b3;
    }

    .btn-delete {
        background: #dc3545;
        color: white;
    }

    .btn-delete:hover {
        background: #c82333;
    }

    /* Orders Management Section */
    .orders-table-section {
        position: absolute;
        left: 150px;
        top: 1650px;
        width: 1170px;
        background: rgba(255, 255, 255, 0.95);
        border-radius: 14px;
        padding: 30px;
        box-sizing: border-box;
        margin-bottom: 100px;
        z-index: 10;
    }

    /* Predictive Analytics Section */
    .analytics-section {
        position: absolute;
        left: 150px;
        top: 2100px;
        width: 1170px;
        background: rgba(255, 255, 255, 0.95);
        border-radius: 14px;
        padding: 30px;
        box-sizing: border-box;
        margin-bottom: 100px;
        z-index: 10;
    }

    .analytics-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-top: 20px;
    }

    .analytics-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .analytics-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }

    .analytics-card h3 {
        color: #333;
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .recommendation-item {
        background: #f8f9fa;
        border-left: 4px solid #6c757d;
        padding: 12px 15px;
        margin-bottom: 10px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .recommendation-item.high { border-left-color: #dc3545; background: #fff5f5; }
    .recommendation-item.medium { border-left-color: #ffc107; background: #fffbf0; }
    .recommendation-item.low { border-left-color: #6c757d; background: #f8f9fa; }

    .priority-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .priority-high { background: #dc3545; color: white; }
    .priority-medium { background: #ffc107; color: #333; }
    .priority-low { background: #6c757d; color: white; }

    .trending-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 6px;
        margin-bottom: 8px;
    }

    .trend-up { color: #28a745; font-weight: 700; }
    .trend-down { color: #dc3545; font-weight: 700; }

    .forecast-chart {
        margin-top: 15px;
    }

    .forecast-bar {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
        gap: 10px;
    }

    .forecast-label {
        min-width: 80px;
        font-size: 12px;
        color: #666;
        font-weight: 600;
    }

    .forecast-bar-fill {
        height: 30px;
        background: linear-gradient(90deg, #D997D5, #B85CD7);
        border-radius: 4px;
        display: flex;
        align-items: center;
        padding: 0 10px;
        color: white;
        font-weight: 600;
        font-size: 12px;
    }

    .orders-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .orders-table th {
        background: linear-gradient(45deg, #7B87C6, #5B6FA8);
        color: white;
        padding: 15px 12px;
        text-align: left;
        font-weight: 600;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .orders-table td {
        padding: 12px;
        border-bottom: 1px solid #e9ecef;
        font-size: 14px;
        color: #333;
        vertical-align: top;
    }

    .orders-table tr:hover {
        background-color: #f8f9fa;
    }

    .btn-complete {
        background: #28a745;
        color: white;
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-complete:hover {
        background: #218838;
        transform: translateY(-1px);
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-pending {
        background: #ffc107;
        color: #212529;
    }

    .address-cell {
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Ensure body has enough height for all content */
    body {
        min-height: 2200px;
    }
    </style>
</head>

<body>
    <?php if (!isset($_SESSION['admin_id'])): ?>
    <!-- Admin Login Modal - Matching Welcome.php Style -->
    <div id="adminLoginPopup" class="popup-overlay">
        <div class="popup-container">
            <button class="popup-close" onclick="window.location.href='/MainPage.php'">&times;</button>
            <h2 class="form-title">Admin Login</h2>
            
            <?php if (isset($loginError)): ?>
                <div class="error-message"><?php echo htmlspecialchars($loginError); ?></div>
            <?php endif; ?>
            
            <form class="login-form" method="POST">
                <div class="form-group">
                    <label class="input-label">Admin Email</label>
                    <input type="email" class="form-input" name="email" value="admin@af.com" required />
                </div>
                <div class="form-group">
                    <label class="input-label">Password</label>
                    <div style="position: relative;">
                        <input type="password" class="form-input" name="password" id="adminPassword" placeholder="Enter password" required />
                        <button type="button" class="password-toggle" onclick="var input=document.getElementById('adminPassword'); if(input.type==='password'){ input.type='text'; this.textContent='Hide'; } else { input.type='password'; this.textContent='Show'; }">Show</button>
                    </div>
                </div>
                <button type="submit" name="admin_login" class="login-button">Login to Dashboard</button>
                <p class="signup-text">
                    <a href="../MainPage.php">← Back to Store</a>
                </p>
                <div style="margin-top: 15px; font-size: 12px; color: #ccc; text-align: center;">
                    Available accounts:<br>
                    admin@test.com (emmanuelle pranada)<br>
                    admin@af.com (test test)
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <!-- Welcome message and logout for logged in admin -->
    <div class="admin-welcome">
        Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?><?php if (isset($_SESSION['admin_email']) && !empty($_SESSION['admin_email'])): ?> (<?php echo htmlspecialchars($_SESSION['admin_email']); ?>)<?php endif; ?>
    </div>
    <a href="?logout=1" class="admin-logout" onclick="return confirm('Are you sure you want to logout?')">Logout</a>
    
    <!-- Add Product Button -->
    <button class="add-product-btn" onclick="document.getElementById('productModal').style.display='block'; document.getElementById('modalTitle').textContent='Add New Product'; document.getElementById('submitBtn').textContent='Add Product'; document.getElementById('submitBtn').name='add_product'; document.getElementById('product_id').value=''; document.getElementById('productForm').reset();">+ Add Product</button>
    <?php endif; ?>

    <!-- Product Management Modal -->
    <div id="productModal" class="product-modal">
        <div class="product-modal-container">
            <button class="product-modal-close" onclick="document.getElementById('productModal').style.display='none'; document.getElementById('productForm').reset();">&times;</button>
            <h2 class="product-form-title" id="modalTitle">Add New Product</h2>
            
            <?php if ($productSuccess): ?>
                <div class="product-success"><?php echo htmlspecialchars($productSuccess); ?></div>
            <?php endif; ?>
            
            <?php if ($productError): ?>
                <div class="product-error"><?php echo htmlspecialchars($productError); ?></div>
            <?php endif; ?>
            
            <form class="product-form" method="POST" enctype="multipart/form-data" id="productForm">
                <!-- Hidden field for edit mode -->
                <input type="hidden" id="product_id" name="product_id" value="">
                
                <div class="product-form-group">
                    <label class="product-input-label" for="product_name">Product Name</label>
                    <input type="text" class="product-form-input" id="product_name" name="product_name" required 
                           placeholder="Enter product name">
                </div>
                
                <div class="product-form-group">
                    <label class="product-input-label" for="product_description">Description</label>
                    <textarea class="product-form-textarea" id="product_description" name="product_description" required 
                              placeholder="Enter product description"></textarea>
                </div>
                
                <div class="product-form-group">
                    <label class="product-input-label" for="product_price">Price (₱)</label>
                    <input type="number" class="product-form-input" id="product_price" name="product_price" required 
                           min="0" step="0.01" placeholder="0.00">
                </div>
                
                <div class="product-form-group">
                    <label class="product-input-label" for="product_stock">Stock Quantity</label>
                    <input type="number" class="product-form-input" id="product_stock" name="product_stock" required 
                           min="0" placeholder="0">
                </div>
                
                <div class="product-form-group">
                    <label class="product-input-label" for="category_name">Category</label>
                    <select class="product-form-select" id="category_name" name="category_name" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="product-form-group">
                    <label class="product-input-label" for="product_image">Product Image <span id="imageLabel">(Optional)</span></label>
                    <input type="file" class="product-form-input" id="product_image" name="product_image" 
                           accept="image/jpeg,image/png,image/gif">
                    <small style="color: #666; font-size: 12px;">Max size: 5MB. Supported: JPG, PNG, GIF</small>
                    <div id="currentImage" style="margin-top: 10px; display: none;">
                        <p style="color: #666; font-size: 12px;">Current image:</p>
                        <img id="currentImagePreview" style="width: 100px; height: 100px; object-fit: cover; border-radius: 6px;">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" id="submitBtn" name="add_product" class="product-submit-btn" style="flex: 1;">Add Product</button>
                    <button type="button" onclick="document.getElementById('productModal').style.display='none'; document.getElementById('productForm').reset();" class="product-submit-btn" style="flex: 1; background: #6c757d;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-content">
        <div class="boxes">
            <div class="header-section"></div>
            <header class="header">
                <h2 class="brand-name">A&F</h2>
                <h2 class="Admin">Admin Dashboard</h2>

                <!-- Dashboard boxes with contained content -->
                <div class="box1" onclick="alert('Sales Details:\n\nTotal Sales: ₱<?php echo number_format($stats['total_sales'], 2); ?>\n\nDaily Sales: ₱<?php echo number_format($stats['daily_sales'], 2); ?>\n\nThis feature will show detailed sales analytics in a future update.');">
                    <div class="dashboard-icon">
                        <img src="images/topsales.png" alt="Total Sales Icon">
                    </div>
                    <div class="box-title">Total Sales</div>
                    <div class="box-value">₱<?php echo number_format($stats['total_sales'], 2); ?></div>
                    <div class="box-percentage">+₱<?php echo number_format($stats['daily_sales'], 2); ?> today</div>
                </div>
                
                <div class="box2" onclick="alert('Daily Sales Details:\n\nToday\'s Sales: ₱<?php echo number_format($stats['daily_sales'], 2); ?>\n\nThis feature will show daily sales breakdown in a future update.');">
                    <div class="dashboard-icon">
                        <img src="images/dailysales.png" alt="Daily Sales Icon">
                    </div>
                    <div class="box-title">Daily Sales</div>
                    <div class="box-value">₱<?php echo number_format($stats['daily_sales'], 2); ?></div>
                    <div class="box-percentage">
                        <?php if ($stats['sales_change_percent'] > 0): ?>
                            +<?php echo number_format($stats['sales_change_percent'], 1); ?>% from yesterday
                        <?php elseif ($stats['sales_change_percent'] < 0): ?>
                            <?php echo number_format($stats['sales_change_percent'], 1); ?>% from yesterday
                        <?php else: ?>
                            No change from yesterday
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="box3" onclick="document.querySelector('.products-table-section').scrollIntoView({ behavior: 'smooth' });">
                    <div class="dashboard-icon">
                        <img src="images/cart.png" alt="Products Icon">
                    </div>
                    <div class="box-title">Products</div>
                    <div class="box-value"><?php echo $stats['total_products']; ?></div>
                </div>
                
                <div class="box4" onclick="alert('Customer Analytics:\n\nTotal Registered Customers: <?php echo $stats['total_customers']; ?>\n\nCustomer Details:\n- Name, Email, Phone tracked\n- Registration dates monitored\n- Notification preferences managed\n\nThis feature will show detailed customer management in a future update.');">
                    <div class="dashboard-icon">
                        <img src="images/users.png" alt="Customers Icon">
                    </div>
                    <div class="box-title">Customers</div>
                    <div class="box-value"><?php echo $stats['total_customers']; ?></div>
                </div>
    
                <div class="box5" onclick="alert('Delivery Management:\n\nCompleted Deliveries: <?php echo $stats['total_deliveries'] ?? $stats['total_orders']; ?>\n\nBased on completed payments\n\nThis feature will show delivery tracking in a future update.');">
                    <div class="dashboard-icon">
                        <img src="images/deliv.png" alt="Delivery Icon">
                    </div>
                    <div class="box-title">Delivery</div>
                    <div class="box-value"><?php echo $stats['total_deliveries'] ?? $stats['total_orders']; ?></div>
                    <div class="box-percentage">Only completed orders</div>
                </div>
            </header>

            <!-- Summary sections -->
            <div class="sum-sales, top-sales">
                <!-- Rectangle2 now contains Chart.js chart -->
                <div class="rectangle2">
                    <div style="position: relative; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 25px; box-sizing: border-box;">
                        <h3 style="color: white; text-align: center; margin-bottom: 25px; font-size: 18px; margin-top: 0;">📊 Projected Sales 30 Days 📊</h3>
                        <div id="predictionStatus" style="color: white; text-align: center; margin-bottom: 10px; font-size: 12px;">Loading predictions...</div>
                        <div style="position: relative; width: 98%; max-width: 1100px; height: 300px; background: rgba(255,255,255,0.1); border-radius: 10px; padding: 15px; box-sizing: border-box; display: flex; align-items: center; justify-content: center;">
                            <canvas id="salesChart" style="width: 100% !important; height: 100% !important; max-width: 100%; max-height: 100%;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
    
        </div>
    </div>

    <!-- Products Table Section - OUTSIDE dashboard-content -->
    <div class="products-table-section">
        <h2 style="color: #333; font-family: Poppins, sans-serif; font-size: 28px; font-weight: 700; margin-bottom: 20px; text-align: center;">Product Management</h2>
        <div style="overflow-x: auto;">
            <table class="products-table" id="productsTable">
                <thead>
                    <tr>
                        <th class="sortable" data-column="product_id" data-type="number">ID</th>
                        <th class="non-sortable">Image</th>
                        <th class="sortable" data-column="name" data-type="text">Name</th>
                        <th class="non-sortable">Description</th>
                        <th class="sortable" data-column="price" data-type="number">Price</th>
                        <th class="sortable" data-column="stock_quantity" data-type="number">Stock</th>
                        <th class="non-sortable">Category</th>
                        <th class="sortable" data-column="sales" data-type="number">Sales</th>
                        <th class="non-sortable">Actions</th>
                    </tr>
                </thead>
                <tbody id="productsTableBody">
                    <?php foreach ($allProducts as $product): ?>
                    <tr data-product-id="<?php echo $product['product_id']; ?>">
                        <td data-sort="<?php echo $product['product_id']; ?>"><?php echo $product['product_id']; ?></td>
                        <td>
                            <?php if (!empty($product['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px;">
                            <?php else: ?>
                                <div style="width: 50px; height: 50px; background: #f8f9fa; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #6c757d;">No Image</div>
                            <?php endif; ?>
                        </td>
                        <td data-sort="<?php echo htmlspecialchars(strtolower($product['name'])); ?>" style="font-weight: 600;"><?php echo htmlspecialchars($product['name']); ?></td>
                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($product['description']); ?></td>
                        <td data-sort="<?php echo $product['price']; ?>" style="font-weight: 600; color: #D997D5;">₱<?php echo number_format($product['price'], 2); ?></td>
                        <td data-sort="<?php echo $product['stock_quantity']; ?>">
                            <span style="padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; <?php echo $product['stock_quantity'] < 10 ? 'background: #f8d7da; color: #721c24;' : 'background: #d1e7dd; color: #0f5132;'; ?>">
                                <?php echo $product['stock_quantity']; ?> units
                            </span>
                        </td>
                        <td><span style="background: #e9ecef; padding: 4px 8px; border-radius: 12px; font-size: 12px; color: #495057;"><?php echo htmlspecialchars($product['category_name'] ?? 'No Category'); ?></span></td>
                        <td><span style="color: #999; font-size: 12px;"><?php echo $product['sales_count'] ?? 0; ?> sold</span></td>
                        <td>
                            <div class="table-actions">
                                <button class="btn-edit" onclick="editProductById('<?php echo $product['product_id']; ?>')">Edit</button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <button type="submit" name="delete_product" class="btn-delete" 
                                            onclick="return confirm('Are you sure you want to delete this product?')">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Orders Management Section -->
    <div class="orders-table-section">
        <h2 style="color: #333; font-family: Poppins, sans-serif; font-size: 28px; font-weight: 700; margin-bottom: 20px; text-align: center;">Pending Orders Management</h2>
        <?php if (count($pendingOrders) > 0): ?>
        <div style="overflow-x: auto;">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Order ID</th>
                        <th>Payment Method</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Delivery Address</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingOrders as $order): ?>
                    <tr>
                        <td style="font-weight: 600;"><?php echo $order['payment_id']; ?></td>
                        <td><?php echo $order['order_id']; ?></td>
                        <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                        <td style="font-weight: 600; color: #7B87C6;">₱<?php echo number_format($order['amount_paid'], 2); ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($order['payment_date'])); ?></td>
                        <td class="address-cell" title="<?php echo htmlspecialchars($order['delivery_address']); ?>">
                            <?php echo htmlspecialchars($order['delivery_address']); ?>
                        </td>
                        <td>
                            <span class="status-badge status-pending">Pending</span>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?php echo $order['payment_id']; ?>">
                                <input type="hidden" name="new_status" value="DELIVERED">
                                <button type="submit" name="complete_order" class="btn-complete" 
                                        onclick="return confirm('Mark this order as completed?')">Complete Order</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #6c757d;">
            <h3>🎉 No pending orders!</h3>
            <p>All orders have been processed.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- 🔮 PREDICTIVE ANALYTICS SECTION -->
    <div class="analytics-section">
        <h2 style="color: #333; font-family: Poppins, sans-serif; font-size: 28px; font-weight: 700; margin-bottom: 20px; text-align: center;">
            🔮 Predictive Analytics & AI Insights
        </h2>
        
        <div class="analytics-grid">
            <!-- Revenue Forecast -->
            <div class="analytics-card">
                <h3>📈 Revenue Forecast (Next 7 Days)</h3>
                <div class="forecast-chart">
                    <?php 
                    if (count($revenueForecast) > 0):
                        $maxForecast = max($revenueForecast);
                        foreach ($revenueForecast as $date => $amount):
                            $width = $maxForecast > 0 ? ($amount / $maxForecast) * 100 : 0;
                    ?>
                    <div class="forecast-bar">
                        <div class="forecast-label"><?php echo $date; ?></div>
                        <div class="forecast-bar-fill" style="width: <?php echo $width; ?>%;">
                            ₱<?php echo number_format($amount, 2); ?>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <p style="color: #999; text-align: center; padding: 20px;">No historical data for prediction</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Automated Recommendations -->
            <div class="analytics-card">
                <h3>🎯 AI Recommendations</h3>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php 
                    if (count($recommendations) > 0):
                        foreach (array_slice($recommendations, 0, 6) as $rec):
                            $priorityClass = strtolower($rec['priority']);
                    ?>
                    <div class="recommendation-item <?php echo $priorityClass; ?>">
                        <span style="font-size: 20px;"><?php echo $rec['icon']; ?></span>
                        <div style="flex: 1;">
                            <span class="priority-badge priority-<?php echo $priorityClass; ?>">
                                <?php echo $rec['priority']; ?>
                            </span>
                            <p style="margin: 5px 0 0 0; color: #333; font-size: 13px;">
                                <?php echo htmlspecialchars($rec['message']); ?>
                            </p>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <p style="color: #999; text-align: center; padding: 20px;">✅ All good! No urgent actions needed.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Trending Products -->
            <div class="analytics-card">
                <h3>🔥 Trending Products</h3>
                <div>
                    <h4 style="color: #28a745; font-size: 14px; margin-bottom: 10px;">📈 Trending Up</h4>
                    <?php 
                    if (count($trendingProducts['up']) > 0):
                        foreach ($trendingProducts['up'] as $product):
                    ?>
                    <div class="trending-item">
                        <span><?php echo htmlspecialchars($product['name']); ?></span>
                        <span class="trend-up" style="font-weight: 700;">
                            ↗ <?php echo $product['salesCount'] ?? 0; ?> sales
                        </span>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <p style="color: #999; font-size: 12px;">No trending data</p>
                    <?php endif; ?>

                    <h4 style="color: #dc3545; font-size: 14px; margin: 15px 0 10px 0;">📉 Needs Attention</h4>
                    <?php 
                    if (count($trendingProducts['down']) > 0):
                        foreach ($trendingProducts['down'] as $product):
                    ?>
                    <div class="trending-item">
                        <span><?php echo htmlspecialchars($product['name']); ?></span>
                        <span class="trend-down" style="font-weight: 700;">
                            ↘ <?php echo $product['salesCount'] ?? 0; ?> sales
                        </span>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <p style="color: #999; font-size: 12px;">No low-selling products</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stock Depletion Forecast -->
            <div class="analytics-card">
                <h3>⏰ Stock Depletion Forecast</h3>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php 
                    if (count($stockDepletion) > 0):
                        foreach ($stockDepletion as $warning):
                            $urgency = $warning['daysLeft'] < 7 ? 'high' : 'medium';
                    ?>
                    <div class="recommendation-item <?php echo $urgency; ?>">
                        <span style="font-size: 20px;">⚠️</span>
                        <div style="flex: 1;">
                            <strong><?php echo htmlspecialchars($warning['product']); ?></strong>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 12px;">
                                <?php echo $warning['stock']; ?> units left • 
                                ~<?php echo $warning['daysLeft']; ?> days remaining • 
                                <?php echo $warning['dailyRate']; ?> sold/day
                            </p>
                        </div>
                    </div>
                    <?php 
                        endforeach;
                    else:
                    ?>
                    <p style="color: #999; text-align: center; padding: 20px;">✅ No stock depletion warnings</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Load Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        console.log('🚀 Starting simple script approach...');

        // Store product data globally
        const productDataMap = new Map();
        <?php foreach ($allProducts as $product): ?>
        productDataMap.set('<?php echo $product['product_id']; ?>', {
            product_id: '<?php echo $product['product_id']; ?>',
            name: <?php echo json_encode($product['name']); ?>,
            description: <?php echo json_encode($product['description']); ?>,
            price: <?php echo $product['price']; ?>,
            stock_quantity: <?php echo $product['stock_quantity']; ?>,
            category: '<?php echo $product['category_id']; ?>',
            has_image: <?php echo !empty($product['image_url']) ? 'true' : 'false'; ?>,
            image_url: <?php echo json_encode($product['image_url']); ?>
        });
        <?php endforeach; ?>

        // Edit product function
         function editProductById(productId) {
            console.log('✏️ Editing product ID:', productId);
            
            const product = productDataMap.get(productId);
            if (!product) {
                alert('Product not found!');
                return;
            }
            
            console.log('📦 Found product:', product.name);
            
            // Show modal
            document.getElementById('productModal').style.display = 'block';
            
            // Set modal to edit mode
            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('submitBtn').textContent = 'Save Changes';
            document.getElementById('submitBtn').name = 'edit_product';
            
            // Fill form with product data
            document.getElementById('product_id').value = productId;
            document.getElementById('product_name').value = product.name;
            document.getElementById('product_description').value = product.description;
            document.getElementById('product_price').value = product.price.toFixed(2);
            document.getElementById('product_stock').value = product.stock_quantity;
            document.getElementById('category_name').value = product.category;
            
            // Handle current image display
            const currentImg = document.getElementById('currentImage');
            const imgPreview = document.getElementById('currentImagePreview');
            const imageLabel = document.getElementById('imageLabel');
            
            if (product.has_image) {
                // Get the actual image from the table row
                const tableRow = document.querySelector(`tr[data-product-id="${productId}"]`);
                const existingImg = tableRow ? tableRow.querySelector('img') : null;
                
                if (existingImg && existingImg.src) {
                    console.log('🖼️ Found existing image in table');
                    currentImg.style.display = 'block';
                    imgPreview.src = existingImg.src;
                    imgPreview.alt = product.name;
                    imageLabel.textContent = '(Optional - leave empty to keep current image)';
                } else {
                    console.log('❌ No image found in table row');
                    currentImg.style.display = 'none';
                    imageLabel.textContent = '(Optional)';
                }
            } else {
                console.log('📷 Product has no image');
                currentImg.style.display = 'none';
                imageLabel.textContent = '(Optional)';
            }
            
            // Clear the file input
            document.getElementById('product_image').value = '';
            
            console.log('✅ Edit modal opened successfully');
        }

        // Chart creation function with 30-day predictions
        async function createChart() {
            const perfStart = performance.now();
            console.log('📊 createChart called at', new Date().toTimeString());
            const ctx = document.getElementById('salesChart');
            const statusDiv = document.getElementById('predictionStatus');
            
            if (!ctx) {
                console.error('❌ No canvas found');
                if (statusDiv) statusDiv.textContent = '⚠️ Chart canvas not found';
                return;
            }

            if (typeof Chart === 'undefined') {
                console.error('❌ Chart.js not loaded');
                if (statusDiv) statusDiv.innerHTML = '⚠️ Chart.js library not loaded. <a href="#" onclick="location.reload(); return false;">Reload page</a>';
                return;
            }

            try {
                // Fetch predictions from API
                const apiUrl = 'http://localhost:3000/api/predictions/sales/cached';
                const fetchStart = performance.now();
                console.log('🔄 Starting fetch at +' + (fetchStart - perfStart).toFixed(0) + 'ms');
                statusDiv.textContent = 'Fetching predictions...';
                
                const response = await fetch(apiUrl, {
                    method: 'GET',
                    mode: 'cors'
                });
                
                const fetchEnd = performance.now();
                console.log('📡 Fetch complete in ' + (fetchEnd - fetchStart).toFixed(0) + 'ms, status:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const parseStart = performance.now();
                const data = await response.json();
                const parseEnd = performance.now();
                console.log('📦 Parse complete in ' + (parseEnd - parseStart).toFixed(0) + 'ms, predictions:', data.predictions?.length);
                
                if (!data.success) {
                    throw new Error('API returned success: false');
                }

                const predictions = data.predictions;
                const dates = predictions.map(p => {
                    const date = new Date(p.date);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                });
                const revenues = predictions.map(p => p.predicted_revenue);
                
                // Update status with insights
                const totalRevenue = data.insights.total_predicted_revenue;
                statusDiv.innerHTML = `AI-powered forecast • Total: ₱${totalRevenue.toLocaleString()} • Generated: ${data.generated_at}`;
                
                console.log('📊 Creating chart...');
                const chartStart = performance.now();

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [{
                            label: 'Predicted Revenue (₱)',
                            data: revenues,
                            borderColor: '#FFD700',
                            backgroundColor: 'rgba(255, 215, 0, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#FFD700',
                            pointBorderColor: '#FFF',
                            pointBorderWidth: 2,
                            pointRadius: 3,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                labels: { color: 'white' }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: 'white',
                                bodyColor: 'white',
                                borderColor: '#FFD700',
                                borderWidth: 1,
                                callbacks: {
                                    label: function(context) {
                                        return 'Revenue: ₱' + context.parsed.y.toLocaleString();
                                    },
                                    afterLabel: function(context) {
                                        const pred = predictions[context.dataIndex];
                                        return [
                                            `Day: ${pred.day_name}`,
                                            `Orders: ${pred.predicted_orders}`,
                                            `Confidence: ${pred.confidence.toUpperCase()}`
                                        ];
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(255, 255, 255, 0.2)' },
                                ticks: { 
                                    color: 'white',
                                    callback: function(value) { return '₱' + value.toLocaleString(); }
                                }
                            },
                            x: {
                                grid: { color: 'rgba(255, 255, 255, 0.2)' },
                                ticks: { 
                                    color: 'white',
                                    maxRotation: 45,
                                    minRotation: 45
                                }
                            }
                        }
                    }
                });
                
                const chartEnd = performance.now();
                const totalTime = chartEnd - perfStart;
                console.log('✅ Chart created in ' + (chartEnd - chartStart).toFixed(0) + 'ms');
                console.log('✅ TOTAL TIME: ' + totalTime.toFixed(0) + 'ms');
                console.log('   - Fetch: ' + (fetchEnd - fetchStart).toFixed(0) + 'ms');
                console.log('   - Parse: ' + (parseEnd - parseStart).toFixed(0) + 'ms');
                console.log('   - Chart: ' + (chartEnd - chartStart).toFixed(0) + 'ms');
            } catch (error) {
                console.error('❌ Chart error:', error);
                console.error('Error details:', {
                    message: error.message,
                    stack: error.stack,
                    name: error.name,
                    type: error.constructor.name
                });
                
                if (statusDiv) {
                    let errorMsg = '';
                    
                    // Network/CORS errors
                    if (error.name === 'TypeError' && error.message.includes('fetch')) {
                        errorMsg = '⚠️ Network Error: Cannot connect to API server.<br>' +
                                   '<small>• Check if Node server is running: <code>lsof -i :3000</code><br>' +
                                   '• Check browser console for CORS errors<br>' +
                                   '• Try accessing API directly: <a href="http://localhost:3000/api/health" target="_blank">http://localhost:3000/api/health</a></small>';
                    } 
                    // HTTP errors
                    else if (error.message.includes('HTTP')) {
                        errorMsg = `⚠️ Server Error: ${error.message}<br>` +
                                   '<small>API endpoint returned an error response</small>';
                    }
                    // Chart.js errors
                    else if (error.message.includes('Chart')) {
                        errorMsg = '⚠️ Chart.js Error: Failed to render chart<br>' +
                                   '<small>Chart library may not be loaded correctly</small>';
                    }
                    // Generic errors
                    else {
                        errorMsg = `⚠️ Error: ${error.message}<br>` +
                                   '<small>Check browser console for details</small>';
                    }
                    
                    statusDiv.innerHTML = errorMsg + 
                        '<br><button onclick="createChart(); return false;" style="margin-top: 10px; padding: 5px 15px; background: #FFD700; color: #000; border: none; border-radius: 5px; cursor: pointer;">🔄 Retry</button>';
                    statusDiv.style.color = '#ffcccc';
                    statusDiv.style.fontSize = '11px';
                    statusDiv.style.lineHeight = '1.4';
                }
            }
        }

        // Table sorting functionality
        let currentSort = { column: null, direction: 'asc' };

        function sortTable(columnIndex, dataType, columnName) {
            const table = document.getElementById('productsTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Determine sort direction
            if (currentSort.column === columnName) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.direction = 'asc';
            }
            currentSort.column = columnName;
            
            // Update header classes
            const headers = table.querySelectorAll('th.sortable');
            headers.forEach(h => {
                h.classList.remove('sort-asc', 'sort-desc');
            });
            
            const currentHeader = table.querySelector(`th[data-column="${columnName}"]`);
            currentHeader.classList.add(currentSort.direction === 'asc' ? 'sort-asc' : 'sort-desc');
            
            // Sort rows
            rows.sort((a, b) => {
                let aValue, bValue;
                
                if (dataType === 'number') {
                    aValue = parseFloat(a.cells[columnIndex].getAttribute('data-sort')) || 0;
                    bValue = parseFloat(b.cells[columnIndex].getAttribute('data-sort')) || 0;
                } else {
                    aValue = a.cells[columnIndex].getAttribute('data-sort') || a.cells[columnIndex].textContent;
                    bValue = b.cells[columnIndex].getAttribute('data-sort') || b.cells[columnIndex].textContent;
                    aValue = aValue.toString().toLowerCase();
                    bValue = bValue.toString().toLowerCase();
                }
                
                if (currentSort.direction === 'asc') {
                    return aValue > bValue ? 1 : -1;
                } else {
                    return aValue < bValue ? 1 : -1;
                }
            });
            
            // Re-append sorted rows
            rows.forEach(row => tbody.appendChild(row));
        }

        // Add click listeners to sortable headers
        document.addEventListener('DOMContentLoaded', function() {
            console.log('📋 DOM ready - initializing...');
            
            // Check if we should show the modal (after form submission)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('show_modal') === '1') {
                console.log('🔔 Showing modal due to URL parameter');
                document.getElementById('productModal').style.display = 'block';
                
                // Remove the parameter from URL without refresh
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            
            // Create chart - wait for Chart.js to load (faster, no health check)
            function initChart() {
                if (typeof Chart !== 'undefined') {
                    console.log('✅ Chart.js loaded, creating chart immediately...');
                    createChart();
                } else {
                    console.log('⏳ Waiting for Chart.js...');
                    setTimeout(initChart, 50);
                }
            }
            // Start immediately on DOMContentLoaded (already fired)
            initChart();
            
            // Add form submission logging
            const productForm = document.getElementById('productForm');
            if (productForm) {
                productForm.addEventListener('submit', function(e) {
                    console.log('📝 Form submission:', {
                        action: document.getElementById('submitBtn').name,
                        hasFile: document.getElementById('product_image').files.length > 0,
                        fileName: document.getElementById('product_image').files[0]?.name || 'none'
                    });
                });
            }
            
            // Add sorting functionality
            const sortableHeaders = document.querySelectorAll('th.sortable');
            sortableHeaders.forEach((header, index) => {
                header.addEventListener('click', function() {
                    const columnName = this.getAttribute('data-column');
                    const dataType = this.getAttribute('data-type');
                   
                    const columnIndex = Array.from(this.parentNode.children).indexOf(this);
                    sortTable(columnIndex, dataType, columnName);
                });
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('productModal');
                if (event.target === modal) {
                    modal.style.display = 'none';
                    document.getElementById('productForm').reset();
                }
            });
            
            console.log('✅ Initialization complete with debugging!');
        });

        console.log('🎯 Script loaded successfully!');
    </script>
</body>
</html>
