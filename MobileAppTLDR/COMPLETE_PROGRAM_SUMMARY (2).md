# 📱 AFMobile - Complete Program Summary

**Project:** AFMobile - E-Commerce Chocolate Shop Mobile Application  
**Platform:** Android (Kotlin)  
**Database:** Firebase Firestore (Cloud) + SQLite (Local Cache)  
**Architecture:** MVVM (Model-View-ViewModel) with Repository Pattern  
**Firebase Project:** anf-chocolate  
**Last Updated:** February 16, 2026

---

## 🎯 Application Overview

AFMobile is a fully functional e-commerce mobile application for selling chocolate products. Users can browse products, add items to cart, place orders, and manage their profiles. The app uses Firebase for authentication and cloud data storage, with SQLite for local caching to improve performance.

---

## 🏗️ Architecture & Design Pattern

### **MVVM + Repository Pattern**

```
┌──────────────────────────────────────────────────────────────┐
│                     UI Layer (Activities/Fragments)           │
│  - MainActivity (Login/Signup)                                │
│  - HomeActivity (Container with Bottom Navigation)            │
│  - HomeFragment, CartFragment, OrdersFragment, ProfileFragment│
│  - CheckoutActivity, AddressPickerActivity, etc.              │
└───────────────────────────┬──────────────────────────────────┘
                            │
                            ↓ (observes LiveData)
┌──────────────────────────────────────────────────────────────┐
│                     ViewModel Layer                           │
│  - ProductViewModel (product data & sync)                     │
│  - CartViewModel (cart operations & state)                    │
│  - OrderViewModel (order management)                          │
└───────────────────────────┬──────────────────────────────────┘
                            │
                            ↓ (calls methods)
┌──────────────────────────────────────────────────────────────┐
│                     Repository Layer                          │
│  - ProductRepository (sync Firebase ↔ SQLite)                │
│  - CartRepository (Firebase Firestore operations)             │
│  - OrderRepository (order CRUD operations)                    │
└───────────────────────────┬──────────────────────────────────┘
                            │
                ┌───────────┴───────────┐
                ↓                       ↓
┌─────────────────────────┐   ┌─────────────────────────┐
│   Firebase Firestore    │   │   SQLite Database       │
│   (Cloud Database)      │   │   (Local Cache)         │
│   - users               │   │   - products table      │
│   - products            │   │                         │
│   - cart                │   │                         │
│   - orders              │   │                         │
└─────────────────────────┘   └─────────────────────────┘
```

---

## 🔐 Authentication Flow

### **Firebase Authentication**

```
App Launch
    │
    ↓
MainActivity (Login Screen)
    │
    ├─→ User Enters Email & Password
    │   ├─→ Firebase.auth.signInWithEmailAndPassword()
    │   └─→ Success → Navigate to HomeActivity
    │
    └─→ User Clicks "Sign Up"
        ├─→ Show Sign Up Overlay
        ├─→ User Enters: Username, Email, Password
        ├─→ Firebase.auth.createUserWithEmailAndPassword()
        ├─→ Call Cloud Function: createUserProfile()
        │   └─→ Creates user document in Firestore "users" collection
        └─→ Success → Navigate to HomeActivity
```

### **Firebase Auth Methods:**
- Email/Password authentication
- User session persistence (auto-login)
- Password reset (via Firebase)

---

## 📊 Database Structure

### **1. Firebase Firestore (Cloud Database)**

#### **Collection: `users`**
```javascript
users/
  └─ {userId}
      ├─ uid: String (Firebase Auth UID)
      ├─ username: String
      ├─ email: String
      ├─ phoneNumber: String? (optional)
      ├─ address: String? (optional)
      ├─ profilePicture: String? (optional)
      ├─ createdAt: Timestamp
      └─ updatedAt: Timestamp
```
**Purpose:** Store user profile information  
**Read:** When displaying profile, checkout  
**Write:** On signup, profile updates  

---

#### **Collection: `products`**
```javascript
products/
  └─ {productId}
      ├─ name: String
      ├─ description: String
      ├─ price: Double
      ├─ category: String (WHITE, DARK, MILK, etc.)
      ├─ imageUrl: String
      ├─ sku: String
      ├─ stockLevel: Number
      ├─ salesCount: Number
      ├─ createdAt: Timestamp
      └─ updatedAt: Timestamp
```
**Purpose:** Store all product catalog  
**Read:** On app launch, sync to local SQLite, background sync every 15 minutes  
**Write:** Admin only (via Firebase Console)  
**Security:** Public read, authenticated write (admin)  

---

#### **Collection: `cart`**
```javascript
cart/
  └─ {cartItemId}
      ├─ userId: String (Firebase Auth UID)
      ├─ productId: String
      ├─ productName: String
      ├─ productPrice: Double
      ├─ productImageUrl: String
      ├─ quantity: Number
      ├─ addedAt: Timestamp
      └─ updatedAt: Timestamp
```
**Purpose:** Store user shopping cart items  
**Read:** When user opens Cart tab, real-time listener for updates  
**Write:** 
- Add to cart (from product detail)
- Update quantity (from cart screen)
- Remove item (from cart screen)
- Clear cart (after checkout)  
**Security:** Users can only access their own cart items  
**Queries:**
- `where("userId", "==", currentUserId).orderBy("addedAt", "DESC")`

---

#### **Collection: `orders`**
```javascript
orders/
  └─ {orderId}
      ├─ userId: String
      ├─ userName: String
      ├─ userEmail: String
      ├─ deliveryAddress: String
      ├─ phoneNumber: String
      ├─ items: Array<OrderItem>
      │   ├─ productId: String
      │   ├─ productName: String
      │   ├─ productImageUrl: String
      │   ├─ productPrice: Double
      │   └─ quantity: Number
      ├─ subtotal: Double
      ├─ deliveryFee: Double
      ├─ totalAmount: Double
      ├─ paymentStatus: String (PENDING, PAID, VERIFIED)
      ├─ orderStatus: String (PENDING, PAID, APPROVED, SHIPPED, DELIVERED, CANCELLED)
      ├─ createdAt: Timestamp
      ├─ paidAt: Timestamp?
      ├─ approvedAt: Timestamp?
      ├─ shippedAt: Timestamp?
      ├─ deliveredAt: Timestamp?
      └─ notes: String
```
**Purpose:** Store all user orders with payment and delivery tracking  
**Read:** When user opens Orders tab  
**Write:** 
- Create order (on checkout)
- Update payment status (when user clicks "Pay")
- Update order status (admin via console)  
**Security:** Users can only access their own orders  
**Queries:**
- `where("userId", "==", currentUserId).orderBy("createdAt", "DESC")`

---

### **2. SQLite Database (Local Cache)**

#### **Table: `products`**
```sql
CREATE TABLE products (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT,
    price REAL NOT NULL,
    category TEXT,
    imageUrl TEXT,
    sku TEXT,
    stockLevel INTEGER DEFAULT 0,
    salesCount INTEGER DEFAULT 0,
    createdAt INTEGER,
    updatedAt INTEGER
)
```
**Purpose:** Local cache of products for fast offline access  
**Read:** All product displays (home screen, search, categories)  
**Write:** Synced from Firebase Firestore  
**Sync Strategy:**
- On app launch (HomeFragment.onViewCreated)
- On pull-to-refresh (user swipes down)
- Background sync every 15 minutes (WorkManager)
- Manual sync (when user action requires fresh data)

---

## 🔄 Data Flow & Operations

### **1. Product Display Flow**

```
User Opens App
    │
    ↓
MainActivity (Login)
    │
    ↓ [successful login]
HomeActivity Launched
    │
    ↓
HomeFragment Displayed
    │
    ├─→ ProductViewModel Initialized
    │   └─→ ProductRepository Initialized
    │       └─→ Loads products from SQLite
    │           └─→ LiveData updates UI
    │
    ├─→ syncProducts() Called
    │   └─→ ProductRepository.syncProductsFromFirebase()
    │       ├─→ Query Firestore: collection("products").get()
    │       ├─→ Parse documents → List<Product>
    │       ├─→ Insert into SQLite (REPLACE strategy)
    │       └─→ refreshAllProducts() → LiveData updates UI
    │
    └─→ ProductSyncWorker Scheduled
        └─→ Repeats sync every 15 minutes in background
```

**Read Operations:**
- `ProductRepository.allProducts` (LiveData) - All products
- `ProductRepository.getProductsByCategory(category)` - Filtered by category
- `ProductRepository.searchProducts(query)` - Search by name/description
- `ProductRepository.getProductById(id)` - Single product lookup

**Write Operations:**
- `ProductRepository.syncProductsFromFirebase()` - Sync from cloud
- `ProductRepository.insertProduct(product)` - Add/update single product

---

### **2. Cart Operations Flow**

```
User Clicks Product Card
    │
    ↓
ProductDetailBottomSheet Opens
    │
    ├─→ User Adjusts Quantity
    └─→ User Clicks "Add to Cart"
        │
        ↓
CartViewModel.addToCart(product, quantity)
    │
    ↓
CartRepository.addToCart(product, quantity)
    │
    ├─→ Check if item already exists:
    │   Firestore.collection("cart")
    │     .where("userId", "==", currentUserId)
    │     .where("productId", "==", productId)
    │     .get()
    │
    ├─→ If exists: Update quantity
    │   └─→ document.update("quantity", existingQty + newQty)
    │
    └─→ If new: Create new cart item
        └─→ collection("cart").add({
              userId, productId, productName,
              productPrice, productImageUrl, quantity,
              addedAt, updatedAt
            })
```

**Cart Fragment (Real-time Updates):**
```
CartFragment Opened
    │
    ↓
CartViewModel.setupCartListener()
    │
    ↓
Firestore Real-time Listener Attached
collection("cart")
  .where("userId", "==", currentUserId)
  .orderBy("addedAt", "DESC")
  .addSnapshotListener()
    │
    └─→ On any change → LiveData updates UI immediately
```

**Read Operations:**
- `CartRepository.loadCartItems()` - Load user's cart
- Real-time listener for automatic updates
- `CartRepository.getCartItemCount()` - Badge count
- `CartRepository.getCartTotalPrice()` - Calculate total

**Write Operations:**
- `CartRepository.addToCart(product, quantity)` - Add item
- `CartRepository.updateCartItemQuantity(itemId, quantity)` - Update qty
- `CartRepository.removeFromCart(itemId)` - Delete item
- `CartRepository.clearCart()` - Remove all items (after checkout)

---

### **3. Checkout & Order Flow**

```
User Clicks "Checkout" in Cart
    │
    ↓
CheckoutActivity Launched
    │
    ├─→ Load cart items (from CartViewModel)
    ├─→ Load user profile (from Firestore "users")
    │   ├─→ Display: Name, Email, Phone, Address
    │   └─→ If missing, prompt to add
    │
    └─→ Calculate:
        ├─→ Subtotal = sum(item.price × item.quantity)
        ├─→ Delivery Fee = ₱50.00
        └─→ Total = Subtotal + Delivery Fee
    │
    ↓
User Clicks "Place Order"
    │
    ↓
OrderViewModel.createOrder()
    │
    ↓
OrderRepository.createOrder()
    │
    ├─→ Validate cart items & stock
    ├─→ Create order document in Firestore:
    │   collection("orders").add({
    │     userId, userName, userEmail,
    │     deliveryAddress, phoneNumber,
    │     items: [...cartItems],
    │     subtotal, deliveryFee, totalAmount,
    │     paymentStatus: "PENDING",
    │     orderStatus: "PENDING",
    │     createdAt: serverTimestamp()
    │   })
    │
    └─→ Return orderId
    │
    ↓
Show Payment Dialog
    │
    ├─→ User Confirms Payment Method
    └─→ OrderViewModel.markOrderAsPaid(orderId)
        │
        ├─→ Update order:
        │   └─→ paymentStatus: "PAID"
        │   └─→ orderStatus: "PAID"
        │   └─→ paidAt: serverTimestamp()
        │
        └─→ Clear cart:
            └─→ CartViewModel.clearCart()
    │
    ↓
Navigate to Orders Tab
    │
    └─→ User sees new order with status "Payment received"
```

**Order Status Lifecycle:**
```
PENDING → PAID → APPROVED → SHIPPED → DELIVERED
   ↓        ↓        ↓
CANCELLED CANCELLED CANCELLED
```

**Read Operations:**
- `OrderRepository.loadUserOrders()` - Get all user orders
- `OrderRepository.getOrder(orderId)` - Get single order
- Real-time updates via LiveData

**Write Operations:**
- `OrderRepository.createOrder()` - Create new order
- `OrderRepository.markOrderAsPaid(orderId)` - Update payment status
- `OrderRepository.cancelOrder(orderId)` - Cancel order

---

### **4. Profile Management Flow**

```
User Opens Profile Tab
    │
    ↓
ProfileFragment Displayed
    │
    ├─→ Check Firebase Auth
    │   └─→ If not signed in → Show "Sign In" button
    │
    └─→ If signed in:
        │
        ├─→ Load User Profile
        │   └─→ Firestore.collection("users")
        │         .document(userId)
        │         .get()
        │       └─→ Display: Username, Email, Initials
        │
        └─→ Setup Menu Options:
            ├─→ Your Orders → Navigate to Orders Tab
            ├─→ My Cart → Navigate to Cart Tab
            ├─→ My Address → Open AddressPickerActivity
            │   └─→ Google Maps integration for address selection
            ├─→ Phone Number → Show dialog to add/edit
            ├─→ Settings → Open SettingsActivity
            └─→ Log Out → Sign out & return to MainActivity
```

**Read Operations:**
- `Firestore.collection("users").document(userId).get()` - Load profile

**Write Operations:**
- User profile created on signup (via Cloud Function)
- `Firestore.collection("users").document(userId).update()` - Update profile
- Update address (from AddressPickerActivity)
- Update phone number (from dialog)

---

## 🔒 Security Rules

### **Firestore Security Rules** (`firestore.rules`)

```javascript
// Users Collection
match /users/{userId} {
  allow read: if request.auth != null;
  allow create, update, delete: if request.auth.uid == userId;
}

// Products Collection
match /products/{productId} {
  allow read: if true;  // Public browsing
  allow write: if false;  // Admin only (via console)
}

// Cart Collection
match /cart/{cartItemId} {
  allow read, write: if request.auth != null 
                     && resource.data.userId == request.auth.uid;
}

// Orders Collection
match /orders/{orderId} {
  allow read, write: if request.auth != null 
                     && resource.data.userId == request.auth.uid;
}
```

**Key Security Features:**
- All operations require authentication (except product browsing)
- Users can only access their own data (cart, orders, profile)
- Products are read-only for users
- Server-side validation via Cloud Functions

---

## ☁️ Cloud Functions (Node.js)

**Location:** `/functions/index.js`

### **1. createUserProfile**
```javascript
exports.createUserProfile = functions.https.onCall(async (data, context) => {
  // Called when user signs up
  // Creates user document in Firestore
  // Input: { uid, username, email }
  // Output: { success, message, uid }
});
```

### **2. updateUserProfile**
```javascript
exports.updateUserProfile = functions.https.onCall(async (data, context) => {
  // Updates user profile fields
  // Input: { username?, phoneNumber?, address?, profilePicture? }
  // Output: { success, message }
});
```

### **3. getUserProfile**
```javascript
exports.getUserProfile = functions.https.onCall(async (data, context) => {
  // Retrieves user profile data
  // Input: { uid? } (defaults to authenticated user)
  // Output: { success, profile }
});
```

### **4. deleteUserAccount**
```javascript
exports.deleteUserAccount = functions.https.onCall(async (data, context) => {
  // Deletes user account and all data
  // Removes: user doc, cart items, orders
  // Deletes Firebase Auth account
});
```

### **5. onUserDelete**
```javascript
exports.onUserDelete = functions.auth.user().onDelete(async (user) => {
  // Cleanup trigger when user deleted
  // Ensures no orphaned data in Firestore
});
```

---

## 🎨 UI Navigation Structure

```
HomeActivity (Container)
    │
    └─── Bottom Navigation Bar
          ├─── Home Tab → HomeFragment
          │     ├─ Search bar
          │     ├─ Category chips (All, WHITE, DARK, MILK)
          │     ├─ Product grid (RecyclerView)
          │     └─ Swipe to refresh
          │
          ├─── Cart Tab → CartFragment
          │     ├─ If not signed in: Sign-in prompt
          │     └─ If signed in:
          │         ├─ Cart items list (RecyclerView)
          │         ├─ Quantity controls (+/-)
          │         ├─ Remove button per item
          │         ├─ Cart summary (subtotal, total)
          │         └─ Checkout button
          │
          ├─── Orders Tab → OrdersFragment
          │     ├─ If not signed in: Sign-in prompt
          │     └─ If signed in:
          │         ├─ Orders list (RecyclerView)
          │         ├─ Order cards (ID, date, status, total)
          │         ├─ "Pay" button for pending orders
          │         └─ Click order → Show details dialog
          │
          └─── Profile Tab → ProfileFragment
                ├─ If not signed in: Sign-in prompt
                └─ If signed in:
                    ├─ Profile header (name, email, initials)
                    ├─ Your Orders
                    ├─ My Cart
                    ├─ My Address (Google Maps picker)
                    ├─ Phone Number
                    ├─ Payment Methods
                    ├─ Settings
                    ├─ Help & Support
                    └─ Log Out
```

### **Additional Screens:**

- **MainActivity** - Login & Sign Up
- **CheckoutActivity** - Order review & payment
- **AddressPickerActivity** - Google Maps address selection
- **SettingsActivity** - App settings & preferences

---

## 📱 Complete User Journey

### **First-Time User:**
```
1. Launch App → MainActivity (Login Screen)
2. Click "Sign Up" → Enter: Username, Email, Password
3. Account Created → Cloud Function creates profile
4. Navigate to HomeActivity
5. HomeFragment loads → Sync products from Firebase
6. Browse products → Click product card
7. ProductDetailBottomSheet opens → Select quantity
8. Click "Add to Cart" → Item saved to Firestore
9. Navigate to Cart Tab → See added items
10. Click "Checkout" → CheckoutActivity opens
11. If address/phone missing → Prompt to add
12. Review order → Click "Place Order"
13. Order created in Firestore (status: PENDING)
14. Payment dialog → Confirm payment method
15. Order updated (status: PAID) → Cart cleared
16. Navigate to Orders Tab → See new order
```

### **Returning User:**
```
1. Launch App → Auto-login (Firebase Auth persistence)
2. Navigate to HomeActivity → HomeFragment
3. Products loaded from SQLite cache (instant)
4. Background sync updates products from Firebase
5. Cart badge shows item count from Firestore
6. Orders tab shows all previous orders
7. Profile tab shows saved address & phone
```

---

## 🔧 Key Technologies & Libraries

### **Android/Kotlin:**
- **Minimum SDK:** 24 (Android 7.0)
- **Target SDK:** 34 (Android 14)
- **Language:** Kotlin
- **Build System:** Gradle (Kotlin DSL)

### **Firebase:**
- **Firebase Authentication** - Email/password login
- **Cloud Firestore** - NoSQL cloud database
- **Cloud Functions** - Server-side logic (Node.js)
- **Firebase Storage** - Product images (optional)

### **Local Database:**
- **SQLite** - Lightweight local storage
- **Custom SQLiteOpenHelper** - Database management

### **Architecture Components:**
- **LiveData** - Observable data holder
- **ViewModel** - UI state management
- **Lifecycle** - Lifecycle-aware components
- **Coroutines** - Asynchronous operations
- **WorkManager** - Background sync tasks

### **UI/UX:**
- **Material Design 3** - Modern UI components
- **RecyclerView** - Efficient list/grid displays
- **ViewBinding** - Type-safe view access
- **Navigation Component** - Fragment navigation
- **SwipeRefreshLayout** - Pull-to-refresh
- **BottomNavigationView** - Tab navigation

### **Image Loading:**
- **Glide** - Image loading & caching

### **Google Services:**
- **Google Maps SDK** - Address selection
- **Places API** - Address autocomplete
- **Location Services** - Current location

---

## 📊 Data Synchronization Strategy

### **Product Sync:**
```
┌─────────────────────────────────────────┐
│   Firebase Firestore (Source of Truth)  │
│   - Products added/updated by admin     │
└──────────────────┬──────────────────────┘
                   │
                   ↓ [sync]
┌──────────────────────────────────────────┐
│   SQLite (Local Cache)                   │
│   - Fast read access                     │
│   - Offline capability                   │
│   - Updated via sync operations          │
└──────────────────┬──────────────────────┘
                   │
                   ↓ [LiveData]
┌──────────────────────────────────────────┐
│   UI (RecyclerView)                      │
│   - Displays cached products instantly   │
│   - Updates when sync completes          │
└──────────────────────────────────────────┘
```

**Sync Triggers:**
1. App launch (HomeFragment.onViewCreated)
2. User pull-to-refresh
3. WorkManager periodic sync (every 15 minutes)
4. Manual sync after user actions

### **Cart/Orders Real-time:**
- **No local cache** - Always from Firestore
- **Real-time listeners** - Instant updates
- **Requires internet** - Online-only operations

---

## 🚀 Deployment & Setup

### **Prerequisites:**
1. Node.js (v18+)
2. Firebase CLI (`npm install -g firebase-tools`)
3. Android Studio
4. Firebase project: `anf-chocolate`

### **Deployment Steps:**

**1. Deploy Cloud Functions:**
```bash
cd /home/plantsed11/AndroidStudioProjects/AFMobile
firebase login
firebase use anf-chocolate
firebase deploy --only functions
```

**2. Deploy Firestore Rules:**
```bash
firebase deploy --only firestore:rules
```

**3. Enable Firebase Services (Console):**
- Authentication → Email/Password
- Firestore Database → Create database
- Storage → Enable (for product images)

**4. Build Android App:**
```bash
./gradlew build
./gradlew installDebug
```

---

## 📈 Data Storage Summary

### **What is Stored:**

| Data Type | Storage | Purpose | Access |
|-----------|---------|---------|--------|
| **Users** | Firestore | Profile info (name, email, phone, address) | User's own data only |
| **Products** | Firestore + SQLite | Product catalog (name, price, image, stock) | Public read |
| **Cart** | Firestore | Shopping cart items (product, quantity) | User's own cart only |
| **Orders** | Firestore | Order history (items, payment, delivery) | User's own orders only |
| **Auth** | Firebase Auth | Email, password (hashed) | Managed by Firebase |
| **Images** | Firebase Storage or External URL | Product images | Public URL access |

### **Data Flows:**

**Read Operations:**
- **Products:** Firestore → SQLite → UI (cached)
- **Cart:** Firestore → UI (real-time)
- **Orders:** Firestore → UI (on-demand)
- **Profile:** Firestore → UI (on-demand)

**Write Operations:**
- **Signup:** App → Firebase Auth → Cloud Function → Firestore (users)
- **Add to Cart:** App → Firestore (cart)
- **Checkout:** App → Firestore (orders) → Firestore (update cart)
- **Update Profile:** App → Firestore (users)

---

## 🎯 Key Features Summary

### ✅ **Implemented Features:**

1. **Authentication**
   - Email/password signup & login
   - Auto-login (session persistence)
   - Logout functionality

2. **Product Browsing**
   - Grid display of products
   - Category filtering (All, WHITE, DARK, MILK, etc.)
   - Search functionality
   - Product detail view with quantity selector
   - Swipe to refresh
   - Offline support (cached products)

3. **Shopping Cart**
   - Add products to cart
   - Update quantity (+/-)
   - Remove items
   - Real-time cart updates
   - Cart badge with item count
   - Price calculation (subtotal, total)
   - Clear cart option

4. **Orders**
   - Create orders from cart
   - Order tracking (status, payment)
   - Order history view
   - Payment confirmation
   - Delivery fee calculation
   - Order details view

5. **User Profile**
   - Display user info (name, email)
   - Add/edit phone number
   - Add/edit delivery address (Google Maps)
   - Profile initials avatar
   - Settings access
   - Logout

6. **Background Sync**
   - Auto-sync products every 15 minutes
   - WorkManager for reliable background tasks

7. **Real-time Updates**
   - Cart items update instantly
   - Order status changes reflected immediately
   - Firestore snapshot listeners

8. **Data Security**
   - User-specific data isolation
   - Firestore security rules
   - Firebase Authentication required
   - Server-side validation (Cloud Functions)

---

## 📂 Project File Structure

```
AFMobile/
├── app/src/main/java/com/example/afmobile/
│   ├── MainActivity.kt              # Login & Signup
│   ├── HomeActivity.kt              # Main container with bottom nav
│   ├── HomeFragment.kt              # Product browsing
│   ├── CartFragment.kt              # Shopping cart
│   ├── OrdersFragment.kt            # Order history
│   ├── ProfileFragment.kt           # User profile
│   ├── CheckoutActivity.kt          # Order checkout
│   ├── AddressPickerActivity.kt     # Google Maps address picker
│   ├── SettingsActivity.kt          # App settings
│   │
│   ├── adapters/
│   │   ├── ProductAdapter.kt        # Product grid adapter
│   │   ├── CartAdapter.kt           # Cart items adapter
│   │   ├── OrderAdapter.kt          # Orders list adapter
│   │   └── CheckoutAdapter.kt       # Checkout items adapter
│   │
│   ├── data/
│   │   ├── Product.kt               # Product data models
│   │   ├── CartItem.kt              # Cart item models
│   │   ├── Order.kt                 # Order models
│   │   ├── FirebaseUser.kt          # User model
│   │   ├── ProductDatabaseHelper.kt # SQLite helper
│   │   ├── ProductRepository.kt     # Product data operations
│   │   ├── CartRepository.kt        # Cart data operations
│   │   └── OrderRepository.kt       # Order data operations
│   │
│   ├── viewmodels/
│   │   ├── ProductViewModel.kt      # Product UI state
│   │   ├── CartViewModel.kt         # Cart UI state
│   │   └── OrderViewModel.kt        # Order UI state
│   │
│   └── workers/
│       └── ProductSyncWorker.kt     # Background sync worker
│
├── functions/
│   ├── index.js                     # Cloud Functions
│   └── package.json                 # Node.js dependencies
│
├── firestore.rules                  # Firestore security rules
├── firebase.json                    # Firebase configuration
├── google-services.json             # Firebase app config
└── build.gradle.kts                 # App dependencies
```

---

## 🔄 Complete Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        USER ACTIONS                              │
└───────────────────────────┬─────────────────────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
        ↓                   ↓                   ↓
┌──────────────┐   ┌──────────────┐   ┌──────────────┐
│   Browse     │   │   Add to     │   │   Checkout   │
│   Products   │   │   Cart       │   │   & Order    │
└──────┬───────┘   └──────┬───────┘   └──────┬───────┘
       │                  │                  │
       ↓                  ↓                  ↓
┌──────────────┐   ┌──────────────┐   ┌──────────────┐
│ ProductVM    │   │  CartVM      │   │  OrderVM     │
└──────┬───────┘   └──────┬───────┘   └──────┬───────┘
       │                  │                  │
       ↓                  ↓                  ↓
┌──────────────┐   ┌──────────────┐   ┌──────────────┐
│ ProductRepo  │   │  CartRepo    │   │  OrderRepo   │
└──────┬───────┘   └──────┬───────┘   └──────┬───────┘
       │                  │                  │
       ↓                  ↓                  ↓
┌──────────────┐   ┌──────────────────────────────────┐
│   SQLite     │   │      Firebase Firestore          │
│   (Cache)    │   │  - users                         │
│              │   │  - cart                          │
│   ↕          │   │  - orders                        │
│              │   │  - products (source of truth)    │
│   Firestore  │   │                                  │
│   (Sync)     │   │  Real-time Listeners:            │
└──────────────┘   │  - Cart changes                  │
                   │  - Order updates                 │
                   └──────────────────────────────────┘
```

---

## 📝 Summary

**AFMobile** is a complete e-commerce mobile application featuring:

- **Full user authentication** with Firebase
- **Product catalog** with category filtering & search
- **Shopping cart** with real-time updates
- **Order management** with payment tracking
- **User profiles** with address & phone management
- **Offline support** via SQLite caching
- **Background sync** for product updates
- **Real-time data** via Firestore listeners
- **Secure access** via Firestore security rules
- **Cloud functions** for server-side operations

**Database Strategy:**
- **Firebase Firestore:** Cloud storage for users, cart, orders, products (source of truth)
- **SQLite:** Local cache for products (fast offline access)
- **Real-time sync:** Cart and orders always live from cloud
- **Periodic sync:** Products synced every 15 minutes

**Architecture:**
- **MVVM pattern** for clean separation of concerns
- **Repository pattern** for data abstraction
- **LiveData** for reactive UI updates
- **Coroutines** for async operations
- **WorkManager** for background tasks

This app provides a complete e-commerce experience from browsing to checkout, with robust data management and security.

---

**End of Complete Program Summary**

**Project:** AFMobile  
**Firebase Project:** anf-chocolate  
**Status:** ✅ Fully Functional  
**Last Updated:** February 16, 2026
