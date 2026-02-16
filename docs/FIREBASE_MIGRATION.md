# A&F Chocolate - Firebase Migration Guide

This guide will help you migrate your PHP/MySQL project to Firebase/Node.js.

## 🚀 Overview

Your project is being migrated from:
- **From:** PHP + MySQL + Apache
- **To:** Node.js + Firebase (Firestore, Authentication, Storage) + Express

## 📋 Prerequisites

1. **Node.js** (v16 or later) - [Download here](https://nodejs.org/)
2. **Firebase Account** - [Sign up here](https://firebase.google.com/)
3. **Firebase CLI** - Install with: `npm install -g firebase-tools`

## 🔧 Step 1: Firebase Console Setup

### 1.1 Create Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com)
2. Click "Add project" or select existing project
3. Enter project name (e.g., "afchoco-web")
4. Enable/disable Google Analytics (optional)
5. Click "Create project"

### 1.2 Enable Firebase Services

#### Enable Authentication
1. In Firebase Console, go to **Authentication** → **Get Started**
2. Click **Sign-in method** tab
3. Enable **Email/Password** provider
4. Click **Save**

#### Enable Firestore Database
1. Go to **Firestore Database** → **Create database**
2. Select **Start in test mode** (we'll update rules later)
3. Choose a Cloud Firestore location (closest to your users)
4. Click **Enable**

#### Enable Cloud Storage
1. Go to **Storage** → **Get started**
2. Start in **test mode**
3. Choose location
4. Click **Done**

### 1.3 Get Firebase Configuration

1. In Firebase Console, go to **Project Settings** (gear icon)
2. Scroll to "Your apps" section
3. Click **Web** icon (</>) to add a web app
4. Register app with nickname (e.g., "AFChoco Web")
5. Copy the `firebaseConfig` object

Example:
```javascript
const firebaseConfig = {
  apiKey: "AIzaSyXXXXXXXXXXXXXXXXXXXXXXXXXXXX",
  authDomain: "your-project.firebaseapp.com",
  projectId: "your-project-id",
  storageBucket: "your-project.appspot.com",
  messagingSenderId: "123456789012",
  appId: "1:123456789012:web:abc123def456"
};
```

### 1.4 Get Service Account Key (for backend)

1. Go to **Project Settings** → **Service accounts**
2. Click **Generate new private key**
3. Download the JSON file
4. **Rename it to `serviceAccountKey.json`**
5. **Place it in your project root** (it's in .gitignore, never commit it!)

## 📦 Step 2: Install Dependencies

```bash
# Navigate to your project directory
cd "/home/plantsed11/VSCode Projects/AFChoco-Web"

# Install Node.js dependencies
npm install

# Install Firebase CLI globally (if not already installed)
npm install -g firebase-tools

# Login to Firebase
firebase login

# Initialize Firebase in your project
firebase init
```

When prompted during `firebase init`:
- Select: **Firestore**, **Hosting**, **Storage**
- Use existing project (select your project)
- Accept default files (firestore.rules, firestore.indexes.json, etc.)
- Set public directory to `public`
- Configure as single-page app: **Yes**
- Don't overwrite existing files

## ⚙️ Step 3: Configure Environment Variables

1. Copy the example environment file:
```bash
cp .env.example .env
```

2. Edit `.env` and add your Firebase configuration:
```env
FIREBASE_API_KEY=your_api_key_from_step_1.3
FIREBASE_AUTH_DOMAIN=your-project.firebaseapp.com
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_STORAGE_BUCKET=your-project.appspot.com
FIREBASE_MESSAGING_SENDER_ID=your_sender_id
FIREBASE_APP_ID=your_app_id

PORT=3000
NODE_ENV=development
SESSION_SECRET=generate_a_random_string_here
```

3. Update `firebase-config.js` with your config values (for frontend)

## 🔄 Step 4: Migrate Data from MySQL to Firestore

**⚠️ IMPORTANT: Backup your MySQL database first!**

```bash
# Export MySQL database
mysqldump -u root -p "a&f chocolate" > backup.sql
```

### Option A: Automated Migration (Recommended)

1. Install MySQL driver:
```bash
npm install mysql2
```

2. Update MySQL credentials in `migrate-data.js` if needed

3. Run the migration script:
```bash
node migrate-data.js
```

This will migrate:
- Categories
- Products
- User profiles (without passwords)
- Orders
- Comments

**Note:** User passwords cannot be migrated. Users will need to re-register or reset their passwords.

### Option B: Manual Migration

You can also manually add data through Firebase Console:
1. Go to Firestore Database
2. Click "Start collection"
3. Add documents manually

## 🏃 Step 5: Start the Node.js Server

```bash
# Development mode (with auto-reload)
npm run dev

# Production mode
npm start
```

Your server should now be running at `http://localhost:3000`

Test the API:
```bash
# Health check
curl http://localhost:3000/api/health

# Get products
curl http://localhost:3000/api/products

# Get categories
curl http://localhost:3000/api/categories
```

## 🌐 Step 6: Update Frontend

### 6.1 Move HTML files to `public` folder

```bash
# Create public directory if it doesn't exist
mkdir -p public

# Move your HTML/CSS/JS files
mv *.html public/
mv *.php public/ # We'll convert these
```

### 6.2 Convert PHP to JavaScript

See `public/firebase-example.html` for examples of:
- User authentication (signup/login/logout)
- Loading products from Firestore
- Adding items to cart with API calls

### 6.3 Update API calls in your JavaScript

**Old PHP way:**
```javascript
fetch('add_to_cart.php', {
  method: 'POST',
  body: formData
})
```

**New Firebase way:**
```javascript
// Get user's ID token
const user = auth.currentUser;
const idToken = await user.getIdToken();

// Call API with token
fetch('http://localhost:3000/api/cart/add', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${idToken}`
  },
  body: JSON.stringify({
    productId: '123',
    quantity: 1,
    price: 10.99
  })
})
```

## 🚀 Step 7: Deploy to Firebase Hosting

```bash
# Build/prepare your files
# (If you have a build step)

# Deploy to Firebase
firebase deploy

# Or deploy only hosting
firebase deploy --only hosting

# Or deploy only Firestore rules
firebase deploy --only firestore:rules
```

Your site will be live at: `https://your-project-id.firebaseapp.com`

## 🔒 Step 8: Update Security Rules

### Firestore Rules

The `firestore.rules` file is already configured with basic security:
- Users can only read/write their own data
- Products and categories are public (read-only)
- Only admins can modify products and categories

### Storage Rules

The `storage.rules` file allows:
- Anyone can read images
- Only admins can upload product images
- Users can upload their own profile images

## 📊 Step 9: Set Up First Admin User

1. Register a user through your app
2. Go to Firebase Console → Firestore Database
3. Find that user in the `users` collection
4. Edit the document and set `role: "admin"`

## ✅ Testing Checklist

- [ ] User signup works
- [ ] User login works
- [ ] Products display correctly
- [ ] Add to cart works
- [ ] Checkout creates orders
- [ ] Admin can add/edit products
- [ ] Images upload correctly
- [ ] Comments system works

## 🐛 Troubleshooting

### CORS Errors
If you get CORS errors, update `server.js`:
```javascript
app.use(cors({
  origin: 'https://your-firebase-domain.web.app',
  credentials: true
}));
```

### "Permission Denied" in Firestore
- Check your Firestore rules
- Make sure user is authenticated
- Verify ID token is being sent correctly

### "Module not found" errors
```bash
npm install
```

### Port already in use
```bash
# Change PORT in .env file
PORT=3001
```

## 📁 Project Structure

```
AFChoco-Web/
├── public/                 # Frontend files (HTML, CSS, images)
│   ├── index.html
│   ├── firebase-example.html
│   └── ...
├── routes/                 # API routes
│   ├── auth.js            # Authentication endpoints
│   ├── products.js        # Product CRUD
│   ├── cart.js            # Shopping cart
│   ├── orders.js          # Order management
│   ├── categories.js      # Categories
│   └── comments.js        # Product comments
├── server.js              # Express server
├── firebase-config.js     # Frontend Firebase config
├── firebase-admin.js      # Backend Firebase Admin SDK
├── migrate-data.js        # MySQL to Firestore migration
├── package.json           # Node.js dependencies
├── firebase.json          # Firebase configuration
├── firestore.rules        # Firestore security rules
├── storage.rules          # Storage security rules
├── .env                   # Environment variables (don't commit!)
└── serviceAccountKey.json # Firebase admin key (don't commit!)
```

## 🔗 API Endpoints

### Authentication
- POST `/api/auth/create-profile` - Create user profile
- GET `/api/auth/profile` - Get current user profile
- PUT `/api/auth/profile` - Update user profile

### Products
- GET `/api/products` - Get all products
- GET `/api/products/:id` - Get single product
- POST `/api/products` - Create product (admin)
- PUT `/api/products/:id` - Update product (admin)
- DELETE `/api/products/:id` - Delete product (admin)

### Cart
- GET `/api/cart` - Get user's cart
- POST `/api/cart/add` - Add item to cart
- PUT `/api/cart/update` - Update cart item quantity
- DELETE `/api/cart/remove/:productId` - Remove item from cart
- DELETE `/api/cart/clear` - Clear entire cart

### Orders
- POST `/api/orders/create` - Create new order
- GET `/api/orders/my-orders` - Get user's orders
- GET `/api/orders/:orderId` - Get specific order
- GET `/api/orders/admin/all` - Get all orders (admin)
- PUT `/api/orders/:orderId/status` - Update order status (admin)

### Categories
- GET `/api/categories` - Get all categories
- GET `/api/categories/:id` - Get single category
- POST `/api/categories` - Create category (admin)
- PUT `/api/categories/:id` - Update category (admin)
- DELETE `/api/categories/:id` - Delete category (admin)

### Comments
- GET `/api/comments/product/:productId` - Get product comments
- POST `/api/comments` - Add comment
- PUT `/api/comments/:id` - Update comment
- DELETE `/api/comments/:id` - Delete comment

## 📚 Additional Resources

- [Firebase Documentation](https://firebase.google.com/docs)
- [Firestore Queries](https://firebase.google.com/docs/firestore/query-data/queries)
- [Firebase Authentication](https://firebase.google.com/docs/auth)
- [Express.js Documentation](https://expressjs.com/)

## 🆘 Need Help?

- Check Firebase Console logs
- Check browser console for errors
- Check Node.js server logs
- Review Firestore security rules

## 📝 Notes

- The old PHP files are still in your project. Once everything works, you can delete them.
- Keep your MySQL backup safe until you're 100% confident everything works
- Test thoroughly before going live
- Consider setting up Firebase Analytics for insights

Good luck with your migration! 🎉
