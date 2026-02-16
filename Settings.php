<?php
session_start();
require_once 'firebase_api.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_email'])) {
    header('Location: Welcome.php');
    exit;
}

// Initialize Firebase API
$firebaseAPI = new FirebaseAPI();

// Get user data from Firebase
$user_id = $_SESSION['user_id'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';
$user_name = $_SESSION['user_name'] ?? 'User';

// Fetch full user profile from Firebase
$user_data = [
    'user_id' => $user_id,
    'name' => $user_name,
    'email' => $user_email,
    'phone_number' => '',
    'address' => '',
    'order_notifications' => 1,
    'promo_notifications' => 0,
    'email_notifications' => 1,
    'language' => 'en'
];

if (isset($_SESSION['firebase_token'])) {
    $firebaseAPI->setAuthToken($_SESSION['firebase_token']);
    $profile = $firebaseAPI->getProfile();
    
    if ($profile && isset($profile['success']) && $profile['success']) {
        $userData = $profile['user'];
        $user_data = array_merge($user_data, [
            'name' => $userData['username'] ?? $userData['firstName'] ?? $user_name,
            'email' => $userData['email'] ?? $user_email,
            'phone_number' => $userData['phoneNumber'] ?? '',
            'address' => $userData['address'] ?? '',
        ]);
    }
}


// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'save_profile':
            $name = trim($_POST['fullName']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $address = trim($_POST['address']);
            
            if (empty($name) || empty($email)) {
                echo json_encode(['success' => false, 'message' => 'Name and email are required']);
                exit;
            }
            
            // Update user profile in Firebase
            $updateData = [
                'username' => $name,
                'firstName' => $name,
                'email' => $email,
                'phoneNumber' => $phone,
                'address' => $address
            ];
            
            $result = $firebaseAPI->updateProfile($updateData);
            
            if ($result && isset($result['success']) && $result['success']) {
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
            } else {
                $errorMsg = $result['error'] ?? 'Error updating profile';
                echo json_encode(['success' => false, 'message' => $errorMsg]);
            }
            break;
            
        case 'change_password':
            $current = $_POST['currentPassword'];
            $new_password = $_POST['newPassword'];
            $confirm = $_POST['confirmPassword'];
            
            if (empty($current) || empty($new_password) || empty($confirm)) {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
                exit;
            }
            
            if ($new_password !== $confirm) {
                echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
                exit;
            }
            
            if (strlen($new_password) < 6) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
                exit;
            }
            
            // Note: Password changes should be handled by Firebase Authentication
            // This would require Firebase Admin SDK on the backend
            echo json_encode(['success' => false, 'message' => 'Password changes must be done through Firebase Authentication']);
            break;
            
        case 'submit_support':
            $support_type = $_POST['supportType'];
            $message = trim($_POST['message']);
            
            if (empty($message)) {
                echo json_encode(['success' => false, 'message' => 'Message is required']);
                exit;
            }
            
            // Store support request in session or log it
            // In a full implementation, you would send this to Firebase or an email service
            error_log("Support Request - User: $user_id, Type: $support_type, Message: $message");
            
            echo json_encode(['success' => true, 'message' => 'Support request submitted successfully!']);
            break;
            
        case 'save_notification_settings':
            $order_notifications = isset($_POST['orderNotifications']) ? 1 : 0;
            $promo_notifications = isset($_POST['promoNotifications']) ? 1 : 0;
            $email_notifications = isset($_POST['emailNotifications']) ? 1 : 0;
            
            // Update notification settings in Firebase
            $updateData = [
                'orderNotifications' => $order_notifications,
                'promoNotifications' => $promo_notifications,
                'emailNotifications' => $email_notifications
            ];
            
            $result = $firebaseAPI->updateProfile($updateData);
            
            if ($result && isset($result['success']) && $result['success']) {
                echo json_encode(['success' => true, 'message' => 'Notification settings saved successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error saving notification settings']);
            }
            break;
            
        case 'get_user_settings':
            // Get user settings from Firebase
            $profile = $firebaseAPI->getProfile();
            
            if ($profile && isset($profile['success']) && $profile['success']) {
                $user = $profile['user'];
                $settings = [
                    'orderNotifications' => $user['orderNotifications'] ?? 1,
                    'promoNotifications' => $user['promoNotifications'] ?? 0,
                    'emailNotifications' => $user['emailNotifications'] ?? 1,
                    'language' => $user['language'] ?? 'en'
                ];
                echo json_encode(['success' => true, 'settings' => $settings]);
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

// Get user settings for initial load
$user_settings = [
    'orderNotifications' => $user_data['order_notifications'] ?? 1,
    'promoNotifications' => $user_data['promo_notifications'] ?? 0,
    'emailNotifications' => $user_data['email_notifications'] ?? 1,
    'language' => $user_data['language'] ?? 'en'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Merriweather+Sans:wght@700&display=swap" rel="stylesheet" />
  <title>Settings - A&F</title>
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
  />
  <!-- Leaflet CSS for maps -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <!-- Google Maps Places API for address autocomplete (optional enhancement) -->
  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCJPY70uT6qNqs2J2GW3zWAAKeQ_rQ1tUk&libraries=places&callback=initGoogleMaps" async defer></script>
  <!-- Leaflet JS for maps -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background-image: url("https://cdn.glitch.global/585aee42-d89c-4ece-870c-5b01fc1bab61/image%203.png?v=1747320934399");
      background-position: center;
      background-attachment: fixed;
      background-size: cover;
      font-family: Merriweather;
      min-height: 100vh;
      overflow-x: hidden; 
      zoom: 0.8;
    }

    .container {
      border-radius: 10px;
      max-width: 1200px;
      margin: 20px auto;
      padding-bottom: 50px;
      height: calc(100vh - 40px);
    }

    .header {
      background-color: #bca5a5;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 25px 40px;
      border-bottom: 3px solid #555;
    }

    .header-left {
      display: flex;
      align-items: center;
    }

    .header h1 {
      font-size: 32px;
      font-family: serif;
      font-weight: bold;
    }

    .header span {
      margin-left: 15px;
      font-size: 20px;
      font-weight: bold;
      color: #111;
    }

    .back-btn {
      background: #4b00b3;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 16px;
      transition: background 0.3s ease;
    }

    .back-btn:hover {
      background: #6b16ac;
    }

    .section-label {
      color: #fff;
      font-weight: bold;
      font-size: 18px;
      margin: 40px 60px 10px;
    }

    .settings-grid {
      display: flex;
      flex-direction: column;
      gap: 30px;
      padding: 0 60px;
    }

    .column {
      display: flex;
      flex-direction: column;
      gap: 20px;
      width: 100%;
    }

    .setting-box {
      background-color: white;
      border-radius: 10px;
      box-shadow: 2px 2px 4px #999;
      padding: 15px 20px;
      font-weight: bold;
      font-size: 18px;
      display: flex;
      align-items: center;
      gap: 15px;
      cursor: pointer;
      transition: background 0.2s ease;
      width: 100%;
    }

    .setting-box:hover {
      background-color: #f1f1f1;
    }

    .setting-box i {
      font-size: 22px;
    }

    /* Modal Styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
    }

    .modal-content {
      background-color: #fefefe;
      margin: 5% auto;
      padding: 30px;
      border-radius: 10px;
      width: 90%;
      max-width: 500px;
      max-height: 85vh;
      overflow-y: auto;
      position: relative;
      box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }

    /* Make profile modal wider to accommodate map */
    #profileModal .modal-content {
      max-width: 600px;
    }

    /* Custom scrollbar styling */
    .modal-content::-webkit-scrollbar {
      width: 8px;
    }

    .modal-content::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }

    .modal-content::-webkit-scrollbar-thumb {
      background: #4b00b3;
      border-radius: 10px;
    }

    .modal-content::-webkit-scrollbar-thumb:hover {
      background: #6b16ac;
    }

    .close {
      color: #aaa;
      float: right;
      font-size: 28px;
      font-weight: bold;
      position: absolute;
      right: 20px;
      top: 15px;
      cursor: pointer;
    }

    .close:hover {
      color: #000;
    }

    .modal h2 {
      color: #4b00b3;
      margin-bottom: 20px;
      font-size: 24px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
      color: #333;
    }

    .form-group input, .form-group select, .form-group textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 16px;
    }

    .btn {
      background: #4b00b3;
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 16px;
      margin-right: 10px;
      transition: background 0.3s ease;
    }

    .btn:hover {
      background: #6b16ac;
    }

    .btn-secondary {
      background: #666;
    }

    .btn-secondary:hover {
      background: #888;
    }

    .alert {
      padding: 10px;
      margin: 10px 0;
      border-radius: 5px;
      display: none;
    }

    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert-error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .toggle-switch {
      position: relative;
      display: inline-block;
      width: 60px;
      height: 34px;
      margin-left: 10px;
    }

    .toggle-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      transition: .4s;
      border-radius: 34px;
    }

    .slider:before {
      position: absolute;
      content: "";
      height: 26px;
      width: 26px;
      left: 4px;
      bottom: 4px;
      background-color: white;
      transition: .4s;
      border-radius: 50%;
    }

    input:checked + .slider {
      background-color: #4b00b3;
    }

    input:checked + .slider:before {
      transform: translateX(26px);
    }

    .setting-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      padding: 10px 0;
      border-bottom: 1px solid #eee;
    }

    .setting-item:last-child {
      border-bottom: none;
    }

    .loading {
      opacity: 0.6;
      pointer-events: none;
    }

    .success-message {
      color: #28a745;
      font-weight: bold;
      margin-top: 10px;
    }

    .error-message {
      color: #dc3545;
      font-weight: bold;
      margin-top: 10px;
    }

    /* Address autocomplete styles */
    .address-input-container {
      position: relative;
    }

    .address-suggestions {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #ddd;
      border-top: none;
      border-radius: 0 0 5px 5px;
      max-height: 200px;
      overflow-y: auto;
      z-index: 1000;
      display: none;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .address-suggestion {
      padding: 10px;
      cursor: pointer;
      border-bottom: 1px solid #eee;
      transition: background 0.2s;
    }

    .address-suggestion:last-child {
      border-bottom: none;
    }

    .address-suggestion:hover {
      background-color: #f5f5f5;
    }

    .address-suggestion strong {
      display: block;
      color: #333;
      margin-bottom: 3px;
    }

    .address-suggestion small {
      color: #666;
      font-size: 13px;
    }

    #address {
      border-radius: 5px 5px 0 0;
    }

    /* Map container styles */
    #map {
      width: 100%;
      height: 250px;
      border-radius: 5px;
      margin-top: 10px;
      border: 2px solid #ddd;
      display: none; /* Hidden by default, shown when location is available */
    }

    #map.active {
      display: block;
    }

    .map-info {
      font-size: 13px;
      color: #666;
      margin-top: 5px;
      display: none;
    }

    .map-info.active {
      display: block;
    }

    .map-info i {
      color: #4b00b3;
    }

    @media (max-width: 768px) {
      .settings-grid {
        flex-direction: column;
        padding: 0 30px;
      }

      .column {
        width: 100%;
      }

      .section-label {
        margin: 30px 30px 10px;
      }

      .header {
        flex-direction: column;
        align-items: flex-start;
      }

      .modal-content {
        margin: 10% auto;
        padding: 20px;
        max-height: 90vh;
      }

      #map {
        height: 200px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="header-left">
        <h1>A&F</h1>
        <span>Settings - <?php echo htmlspecialchars($user_data['name']); ?></span>
      </div>
      <button class="back-btn" onclick="goBack()">
        <i class="fas fa-arrow-left"></i> Back
      </button>
    </div>

    <div class="settings-grid">
      <div class="column">
        <div class="section-label">My Account</div>
        <div class="setting-box" onclick="openModal('profileModal')">
          <i class="fas fa-user-circle"></i> Profile
        </div>
        <div class="setting-box" onclick="openModal('passwordModal')">
          <i class="fas fa-lock"></i> Change Password
        </div>
      </div>

      <div class="column">
        <div class="section-label">Other</div>
        <div class="setting-box" onclick="openModal('notificationModal')">
          <i class="fas fa-bell"></i> Notification
        </div>
        <!-- Language setting removed -->
        <div class="setting-box" onclick="openModal('customerServiceModal')">
          <i class="fas fa-desktop"></i> Customer Service
        </div>
        <div class="setting-box" onclick="openModal('aboutModal')">
          <i class="fas fa-question-circle"></i> About Us
        </div>
      </div>
    </div>
  </div>

  <!-- Profile Modal -->
  <div id="profileModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal('profileModal')">&times;</span>
      <h2><i class="fas fa-user-circle"></i> Profile Settings</h2>
      <div id="profileAlert" class="alert"></div>
      <form id="profileForm">
        <div class="form-group">
          <label for="fullName">Full Name:</label>
          <input type="text" id="fullName" value="<?php echo htmlspecialchars($user_data['name']); ?>" required>
        </div>
        <div class="form-group">
          <label for="email">Email:</label>
          <input type="email" id="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
        </div>
        <div class="form-group">
          <label for="phone">Phone Number:</label>
          <input type="tel" id="phone" value="<?php echo htmlspecialchars($user_data['phone_number']); ?>">
        </div>
        <div class="form-group">
          <label for="address">Address:</label>
          <div class="address-input-container">
            <textarea id="address" rows="3" placeholder="Start typing your address..."><?php echo htmlspecialchars($user_data['address']); ?></textarea>
            <div id="address-suggestions" class="address-suggestions"></div>
          </div>
          <small style="color: #666; font-size: 13px; display: block; margin-top: 5px;">
            <i class="fas fa-map-marker-alt"></i> Type at least 3 characters to see suggestions
          </small>
          <button type="button" class="btn btn-secondary" onclick="getUserLocation()" style="margin-top: 10px; padding: 8px 16px; font-size: 14px;">
            <i class="fas fa-crosshairs"></i> Use My Current Location
          </button>
          <!-- Map container -->
          <div id="map"></div>
          <div id="map-info" class="map-info">
            <i class="fas fa-info-circle"></i> Click on the map to set your exact location or drag the marker
          </div>
        </div>
        <button type="submit" class="btn">Save Changes</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('profileModal')">Cancel</button>
      </form>
    </div>
  </div>

  <!-- Change Password Modal -->
  <div id="passwordModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal('passwordModal')">&times;</span>
      <h2><i class="fas fa-lock"></i> Change Password</h2>
      <div id="passwordAlert" class="alert"></div>
      <form id="passwordForm">
        <div class="form-group">
          <label for="currentPassword">Current Password:</label>
          <input type="password" id="currentPassword" required>
        </div>
        <div class="form-group">
          <label for="newPassword">New Password:</label>
          <input type="password" id="newPassword" required>
        </div>
        <div class="form-group">
          <label for="confirmPassword">Confirm Password:</label>
          <input type="password" id="confirmPassword" required>
        </div>
        <button type="submit" class="btn">Update Password</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('passwordModal')">Cancel</button>
      </form>
    </div>
  </div>

  <!-- Customer Service Modal -->
  <div id="customerServiceModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal('customerServiceModal')">&times;</span>
      <h2><i class="fas fa-desktop"></i> Customer Service</h2>
      <div id="supportAlert" class="alert"></div>
      <form id="supportForm">
        <div class="form-group">
          <label for="supportType">Support Type:</label>
          <select id="supportType" required>
            <option value="general">General Inquiry</option>
            <option value="order">Order Issue</option>
            <option value="payment">Payment Problem</option>
            <option value="technical">Technical Support</option>
          </select>
        </div>
        <div class="form-group">
          <label for="message">Message:</label>
          <textarea id="message" rows="4" placeholder="Describe your issue or question..." required></textarea>
        </div>
        <button type="submit" class="btn">Submit Request</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('customerServiceModal')">Cancel</button>
      </form>
    </div>
  </div>

  <!-- Enhanced Notification Modal -->
  <div id="notificationModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal('notificationModal')">&times;</span>
      <h2><i class="fas fa-bell"></i> Notification Settings</h2>
      <div id="notificationAlert" class="alert"></div>
      <form id="notificationForm">
        <div class="setting-item">
          <label>Order Updates:</label>
          <label class="toggle-switch">
            <input type="checkbox" id="orderNotifications" <?php echo $user_settings['orderNotifications'] ? 'checked' : ''; ?>>
            <span class="slider"></span>
          </label>
        </div>
        <small>Receive notifications about your order status</small>
        
        <div class="setting-item">
          <label>Promotional Offers:</label>
          <label class="toggle-switch">
            <input type="checkbox" id="promoNotifications" <?php echo $user_settings['promoNotifications'] ? 'checked' : ''; ?>>
            <span class="slider"></span>
          </label>
        </div>
        <small>Get notified about special offers and discounts</small>
        
        <div class="setting-item">
          <label>Email Notifications:</label>
          <label class="toggle-switch">
            <input type="checkbox" id="emailNotifications" <?php echo $user_settings['emailNotifications'] ? 'checked' : ''; ?>>
            <span class="slider"></span>
          </label>
        </div>
        <small>Receive notifications via email</small>
        
        <button type="submit" class="btn">Save Settings</button>
        <button type="button" class="btn btn-secondary" onclick="closeModal('notificationModal')">Cancel</button>
      </form>
    </div>
  </div>

  <!-- Language modal removed -->

  <!-- Static Modals (About) -->
  <div id="aboutModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal('aboutModal')">&times;</span>
      <h2><i class="fas fa-question-circle"></i> About A&F Chocolates</h2>
      <p><strong>Version:</strong> 1.0.0</p>
      <p><strong>Developer:</strong> A&F Development Team</p>
      <p><strong>Contact:</strong> A&FCHOCS@gmail.com</p>
      <p><strong>Address:</strong> W5R7+H8 Lipa, Batangas</p>
      <br>
      <p>A&F Chocolates is your premier destination for authentic chocolates, Korean snacks, and Filipino treats.</p>
      <button class="btn" onclick="closeModal('aboutModal')">Close</button>
    </div>
  </div>

  <script>
    // Modal Functions
    function openModal(modalId) {
      document.getElementById(modalId).style.display = 'block';
      
      // If opening profile modal, initialize map if not already done
      if (modalId === 'profileModal') {
        setTimeout(() => {
          if (!map) {
            // Initialize with default Philippines location
            initializeMap();
          }
          
          // If map exists and needs resizing (for Leaflet)
          if (map && map.invalidateSize) {
            map.invalidateSize();
          }
          
          // Try to geocode existing address
          if (addressInput && addressInput.value.trim()) {
            geocodeAddress(addressInput.value.trim());
          }
        }, 300);
      }
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
      // Clear alerts when closing modals
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        alert.style.display = 'none';
        alert.className = 'alert';
      });
    }

    function showAlert(alertId, message, type) {
      const alert = document.getElementById(alertId);
      alert.textContent = message;
      alert.className = `alert alert-${type}`;
      alert.style.display = 'block';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
      }
    }

    function goBack() {
      window.history.back();
    }

    // Profile Form Submission
    document.getElementById('profileForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData();
      formData.append('action', 'save_profile');
      formData.append('fullName', document.getElementById('fullName').value);
      formData.append('email', document.getElementById('email').value);
      formData.append('phone', document.getElementById('phone').value);
      formData.append('address', document.getElementById('address').value);

      fetch('Settings.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showAlert('profileAlert', data.message, 'success');
          setTimeout(() => {
            closeModal('profileModal');
          }, 1500);
        } else {
          showAlert('profileAlert', data.message, 'error');
        }
      })
      .catch(error => {
        showAlert('profileAlert', 'Network error occurred', 'error');
      });
    });

    // Password Form Submission
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData();
      formData.append('action', 'change_password');
      formData.append('currentPassword', document.getElementById('currentPassword').value);
      formData.append('newPassword', document.getElementById('newPassword').value);
      formData.append('confirmPassword', document.getElementById('confirmPassword').value);

      fetch('Settings.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showAlert('passwordAlert', data.message, 'success');
          document.getElementById('passwordForm').reset();
          setTimeout(() => {
            closeModal('passwordModal');
          }, 1500);
        } else {
          showAlert('passwordAlert', data.message, 'error');
        }
      })
      .catch(error => {
        showAlert('passwordAlert', 'Network error occurred', 'error');
      });
    });

    // Support Form Submission
    document.getElementById('supportForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData();
      formData.append('action', 'submit_support');
      formData.append('supportType', document.getElementById('supportType').value);
      formData.append('message', document.getElementById('message').value);

      fetch('Settings.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showAlert('supportAlert', data.message, 'success');
          document.getElementById('supportForm').reset();
          setTimeout(() => {
            closeModal('customerServiceModal');
          }, 1500);
        } else {
          showAlert('supportAlert', data.message, 'error');
        }
      })
      .catch(error => {
        showAlert('supportAlert', 'Network error occurred', 'error');
      });
    });

    // Notification Form Submission
    document.getElementById('notificationForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData();
      formData.append('action', 'save_notification_settings');
      formData.append('orderNotifications', document.getElementById('orderNotifications').checked ? '1' : '0');
      formData.append('promoNotifications', document.getElementById('promoNotifications').checked ? '1' : '0');
      formData.append('emailNotifications', document.getElementById('emailNotifications').checked ? '1' : '0');

      this.classList.add('loading');

      fetch('Settings.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showAlert('notificationAlert', data.message, 'success');
          setTimeout(() => {
            closeModal('notificationModal');
          }, 1500);
        } else {
          showAlert('notificationAlert', data.message, 'error');
        }
        this.classList.remove('loading');
      })
      .catch(error => {
        showAlert('notificationAlert', 'Network error occurred', 'error');
        this.classList.remove('loading');
      });
    });

    // Language option removed; no client handler

    // ============ Address Autocomplete Functionality ============
    let addressTimeout;
    let googleAutocompleteService = null;
    let googlePlacesService = null;
    let useGooglePlaces = false;
    let map = null;
    let marker = null;
    const addressInput = document.getElementById('address');
    const suggestionsContainer = document.getElementById('address-suggestions');
    const mapContainer = document.getElementById('map');
    const mapInfo = document.getElementById('map-info');

    // Initialize Google Places (only for autocomplete) - called when API loads
    window.initGoogleMaps = function() {
      // Check if Google Places API loaded successfully
      if (typeof google !== 'undefined' && google.maps && google.maps.places) {
        googleAutocompleteService = new google.maps.places.AutocompleteService();
        googlePlacesService = new google.maps.places.PlacesService(document.createElement('div'));
        useGooglePlaces = true;
        console.log('Google Places API loaded successfully for autocomplete');
      }
    };

    // Initialize Leaflet Map (free, no API key needed)
    function initializeMap(lat = 13.7565, lng = 121.0583) {
      if (!window.L) {
        console.error('Leaflet library not loaded');
        return;
      }
      
      // Create map centered on location
      map = L.map('map').setView([lat, lng], 15);
      
      // Add OpenStreetMap tile layer (free)
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
      }).addTo(map);
      
      // Add draggable marker
      marker = L.marker([lat, lng], {
        draggable: true,
        title: 'Your Location'
      }).addTo(map);
      
      // Customize marker icon to be more visible
      const customIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
      });
      marker.setIcon(customIcon);
      
      // Update address when marker is dragged
      marker.on('dragend', function() {
        const position = marker.getLatLng();
        reverseGeocode(position.lat, position.lng);
      });
      
      // Allow clicking on map to set location
      map.on('click', function(event) {
        const clickedLocation = event.latlng;
        marker.setLatLng(clickedLocation);
        map.panTo(clickedLocation);
        reverseGeocode(clickedLocation.lat, clickedLocation.lng);
      });
      
      // Try to geocode existing address if available
      if (addressInput && addressInput.value.trim()) {
        geocodeAddress(addressInput.value.trim());
      }
    }

    // Geocode an address to get coordinates using Nominatim
    async function geocodeAddress(address) {
      if (!address) return;
      
      try {
        const response = await fetch(
          `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&countrycodes=ph&limit=1`,
          {
            headers: {
              'User-Agent': 'AFChocolate-Web/1.0'
            }
          }
        );
        
        const results = await response.json();
        
        if (results && results.length > 0) {
          const location = results[0];
          updateMapLocation(parseFloat(location.lat), parseFloat(location.lon));
        }
      } catch (error) {
        console.error('Error geocoding address:', error);
      }
    }

    // Reverse geocode coordinates to get address using Nominatim
    async function reverseGeocode(lat, lng) {
      try {
        const response = await fetch(
          `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`,
          {
            headers: {
              'User-Agent': 'AFChocolate-Web/1.0'
            }
          }
        );
        
        const result = await response.json();
        
        if (result && result.display_name) {
          addressInput.value = result.display_name;
        }
      } catch (error) {
        console.error('Error reverse geocoding:', error);
      }
    }

    // Update map location and marker
    function updateMapLocation(lat, lng) {
      if (!map || !marker) {
        // Initialize map if not already done
        initializeMap(lat, lng);
      } else {
        marker.setLatLng([lat, lng]);
        map.setView([lat, lng], 15);
      }
      
      // Show map and info
      mapContainer.classList.add('active');
      if (mapInfo) mapInfo.classList.add('active');
    }

    // Get user's current location
    function getUserLocation() {
      if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
      }
      
      // Show loading state
      const btn = event.target.closest('button');
      const originalText = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Getting location...';
      btn.disabled = true;
      
      navigator.geolocation.getCurrentPosition(
        function(position) {
          const lat = position.coords.latitude;
          const lng = position.coords.longitude;
          
          // Update map
          updateMapLocation(lat, lng);
          
          // Reverse geocode to get address
          reverseGeocode(lat, lng);
          
          // Reset button
          btn.innerHTML = originalText;
          btn.disabled = false;
        },
        function(error) {
          console.error('Error getting location:', error);
          alert('Unable to get your location. Please check your browser permissions.');
          
          // Reset button
          btn.innerHTML = originalText;
          btn.disabled = false;
        },
        {
          enableHighAccuracy: true,
          timeout: 10000,
          maximumAge: 0
        }
      );
    }

    // Setup address autocomplete when profile modal opens
    function setupAddressAutocomplete() {
      if (!addressInput || !suggestionsContainer) return;

      // Listen for address input changes
      addressInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        clearTimeout(addressTimeout);
        
        // Only search for addresses with 3+ characters
        if (query.length < 3) {
          suggestionsContainer.style.display = 'none';
          return;
        }
        
        // Debounce requests
        addressTimeout = setTimeout(() => {
          getAddressSuggestions(query);
        }, 500);
      });

      // Hide suggestions when clicking outside
      document.addEventListener('click', function(event) {
        if (!addressInput.contains(event.target) && !suggestionsContainer.contains(event.target)) {
          suggestionsContainer.style.display = 'none';
        }
      });

      // Focus on address input to show it's ready
      addressInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 3) {
          getAddressSuggestions(this.value.trim());
        }
      });
    }

    // Get address suggestions - try Google first, fallback to Nominatim
    async function getAddressSuggestions(query) {
      // Try Google Maps Places API first (if available)
      if (useGooglePlaces && googleAutocompleteService) {
        getGooglePlacesSuggestions(query);
      } else {
        // Fallback to Nominatim (free OpenStreetMap)
        getNominatimSuggestions(query);
      }
    }

    // Get suggestions using Google Places API
    function getGooglePlacesSuggestions(query) {
      const request = {
        input: query,
        componentRestrictions: { country: 'ph' }, // Restrict to Philippines
        types: ['address']
      };

      try {
        googleAutocompleteService.getPlacePredictions(request, (predictions, status) => {
          if (status === google.maps.places.PlacesServiceStatus.OK && predictions) {
            displayGooglePlacesSuggestions(predictions);
          } else {
            // Fallback to Nominatim if Google fails
            console.log('Google Places failed, using Nominatim fallback');
            getNominatimSuggestions(query);
          }
        });
      } catch (error) {
        console.error('Google Places error:', error);
        // Fallback to Nominatim on any error
        getNominatimSuggestions(query);
      }
    }

    // Display Google Places suggestions
    function displayGooglePlacesSuggestions(predictions) {
      suggestionsContainer.innerHTML = '';
      
      if (!predictions || predictions.length === 0) {
        suggestionsContainer.style.display = 'none';
        return;
      }

      predictions.forEach((prediction) => {
        const suggestionElement = document.createElement('div');
        suggestionElement.className = 'address-suggestion';
        
        const mainText = prediction.structured_formatting.main_text;
        const secondaryText = prediction.structured_formatting.secondary_text;
        
        suggestionElement.innerHTML = `
          <strong><i class="fas fa-map-marker-alt" style="color: #4b00b3; margin-right: 5px;"></i>${mainText}</strong>
          <small>${secondaryText || ''}</small>
        `;
        
        suggestionElement.addEventListener('click', () => {
          addressInput.value = prediction.description;
          suggestionsContainer.style.display = 'none';
          
          // Store place ID for later use
          addressInput.dataset.placeId = prediction.place_id;
          
          // Get place details and update map
          if (googlePlacesService && prediction.place_id) {
            const request = {
              placeId: prediction.place_id,
              fields: ['geometry', 'formatted_address']
            };
            
            try {
              googlePlacesService.getDetails(request, (place, status) => {
                if (status === google.maps.places.PlacesServiceStatus.OK && place.geometry) {
                  const location = place.geometry.location;
                  updateMapLocation(location.lat(), location.lng());
                } else {
                  // If Google fails, use Nominatim to geocode
                  console.log('Google Place Details failed, using Nominatim');
                  geocodeAddress(prediction.description);
                }
              });
            } catch (error) {
              console.error('Google Place Details error:', error);
              // Fallback to Nominatim geocoding
              geocodeAddress(prediction.description);
            }
          } else {
            // Fallback to Nominatim geocoding
            geocodeAddress(prediction.description);
          }
        });
        
        suggestionsContainer.appendChild(suggestionElement);
      });
      
      suggestionsContainer.style.display = 'block';
    }

    // Get address suggestions using Nominatim (OpenStreetMap)
    async function getNominatimSuggestions(query) {
      try {
        const response = await fetch(
          `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=ph&limit=5&addressdetails=1`,
          {
            headers: {
              'User-Agent': 'AFChocolate-Web/1.0'
            }
          }
        );
        
        const suggestions = await response.json();
        displayNominatimSuggestions(suggestions);
        
      } catch (error) {
        console.error('Error getting address suggestions:', error);
        suggestionsContainer.style.display = 'none';
      }
    }

    // Display Nominatim (OpenStreetMap) address suggestions in dropdown
    function displayNominatimSuggestions(suggestions) {
      suggestionsContainer.innerHTML = '';
      
      if (!suggestions || suggestions.length === 0) {
        suggestionsContainer.style.display = 'none';
        return;
      }
      
      // Create suggestion elements
      suggestions.forEach(function(suggestion) {
        const suggestionElement = document.createElement('div');
        suggestionElement.className = 'address-suggestion';
        
        // Format the address display
        const mainAddress = suggestion.display_name.split(',').slice(0, 2).join(',');
        const fullAddress = suggestion.display_name;
        
        suggestionElement.innerHTML = `
          <strong><i class="fas fa-map-marker-alt" style="color: #4b00b3; margin-right: 5px;"></i>${mainAddress}</strong>
          <small>${fullAddress}</small>
        `;
        
        // Handle suggestion click
        suggestionElement.addEventListener('click', function() {
          addressInput.value = fullAddress;
          suggestionsContainer.style.display = 'none';
          
          // Store coordinates
          addressInput.dataset.lat = suggestion.lat;
          addressInput.dataset.lon = suggestion.lon;
          
          // Update map with coordinates
          if (suggestion.lat && suggestion.lon) {
            updateMapLocation(parseFloat(suggestion.lat), parseFloat(suggestion.lon));
          }
        });
        
        suggestionsContainer.appendChild(suggestionElement);
      });
      
      suggestionsContainer.style.display = 'block';
    }

    // Load user settings on page load
    document.addEventListener('DOMContentLoaded', function() {
      loadUserSettings();
      setupAddressAutocomplete();
    });

    function loadUserSettings() {
      const formData = new FormData();
      formData.append('action', 'get_user_settings');

      fetch('Settings.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const settings = data.settings;
        }
      })
      .catch(error => {
        console.error('Error loading settings:', error);
      });
    }
  </script>
</body>
</html>