# AFChoco-Web Project Structure

## 📂 Directory Organization

```
AFChoco-Web/
├── config/                      # Configuration files
│   ├── firebase-admin.js        # Firebase Admin SDK (server-side)
│   ├── firebase-config.js       # Firebase client config
│   ├── firebase.json            # Firebase CLI config
│   ├── firestore.rules          # Firestore security rules
│   ├── firestore.indexes.json   # Firestore indexes
│   └── storage.rules            # Firebase Storage rules
│
├── routes/                      # API routes
│   ├── auth.js                  # Authentication endpoints
│   ├── products.js              # Product CRUD endpoints
│   ├── cart.js                  # Shopping cart endpoints
│   ├── orders.js                # Order management endpoints
│   ├── categories.js            # Category endpoints
│   └── comments.js              # Product comments/reviews
│
├── public/                      # Frontend files (served statically)
│   ├── main.html               # Main shop page (Firebase version) ⭐
│   ├── api-docs.html           # API documentation landing page
│   ├── firebase-example.html   # Auth/Cart/Checkout example
│   ├── firebase-config.js      # Firebase config (browser accessible)
│   ├── css/                    # Stylesheets
│   │   ├── main.css
│   │   ├── categories.css
│   │   └── comments.css
│   ├── js/                     # Client-side JavaScript
│   │   └── comments.js
│   └── assets/                 # Images and other assets
│
├── images/                      # Product images
│
├── legacy-php/                  # Original PHP files (archived)
│   ├── MainPage.php            # Original PHP main page
│   ├── AdminDashboard.php
│   ├── Cart.php
│   ├── ProductManager.php
│   └── ... (all other PHP files)
│
├── scripts/                     # Utility scripts
│   └── migrate-data.js         # MySQL to Firestore migration
│
├── docs/                        # Documentation
│   ├── DATABASE_SCHEMA.md      # Complete database schema
│   ├── FIREBASE_MIGRATION.md   # Migration guide
│   ├── SCHEMA_ALIGNMENT.md     # Mobile app compatibility
│   └── README.md               # Project README
│
├── node_modules/               # Node.js dependencies
├── server.js                   # Express server entry point ⭐
├── package.json                # Node.js project config
├── package-lock.json
├── .env                        # Environment variables (Firebase credentials)
├── .env.example                # Environment template
└── .gitignore
```

## 🚀 Getting Started

### 1. Install Dependencies
```bash
npm install
```

### 2. Configure Environment
Copy `.env.example` to `.env` and add your Firebase credentials.

### 3. Start Development Server
```bash
npm run dev
```

### 4. Access the Application
- **Main Shop Page**: http://localhost:3000/ (redirects to /main.html) ⭐
- **API Documentation**: http://localhost:3000/api
- **Firebase Example**: http://localhost:3000/firebase-example.html

## 🎯 Key Entry Points

### Frontend
- **main.html** - Main shopping page with categories and products
- **firebase-example.html** - Complete auth/cart/checkout flow example
- **api-docs.html** - API documentation welcome page

### Backend
- **server.js** - Express server configuration
- **routes/** - All API endpoints organized by feature

### Configuration
- **config/firebase-admin.js** - Server-side Firebase Admin SDK
- **config/firebase-config.js** - Client-side Firebase configuration
- **.env** - Environment variables (credentials, secrets)

## 📡 API Endpoints

All API endpoints are prefixed with `/api/`:

- **GET /api/health** - Health check
- **GET /api/products** - List all products
- **GET /api/products?category=DARK** - Filter by category
- **POST /api/cart/add** - Add item to cart (auth required)
- **GET /api/cart** - Get user's cart (auth required)
- **POST /api/orders/create** - Create order (auth required)
- **GET /api/categories** - List categories

Full API documentation at: http://localhost:3000/api

## 🔧 Development Scripts

```bash
# Start development server with auto-reload
npm run dev

# Start production server
npm start

# Run data migration (MySQL → Firestore)
node scripts/migrate-data.js
```

## 📱 Mobile App Compatibility

This web app shares the same Firebase Firestore database with:
- **AFMobile** - Customer mobile app
- **AFAdmin** - Admin mobile app

All apps use identical:
- Database schema (see docs/DATABASE_SCHEMA.md)
- Category names (WHITE, DARK, MILK, MIXED, SPECIALTY)
- Field naming conventions (camelCase)
- Order status workflow

## 🗂️ Legacy Files

Original PHP files are archived in `legacy-php/` for reference. The project has been fully migrated to:
- **Backend**: Node.js + Express.js
- **Database**: Cloud Firestore (replacing MySQL)
- **Authentication**: Firebase Auth (replacing PHP sessions)

## 📚 Documentation

- **docs/DATABASE_SCHEMA.md** - Complete Firestore schema
- **docs/FIREBASE_MIGRATION.md** - Step-by-step migration guide
- **docs/SCHEMA_ALIGNMENT.md** - Cross-platform compatibility notes
- **PROJECT_STRUCTURE.md** - This file

## 🔐 Security

- Firestore security rules: `config/firestore.rules`
- Storage security rules: `config/storage.rules`
- Deploy rules: `firebase deploy --only firestore:rules,storage`

---

**Last Updated**: February 16, 2026
**Firebase Project**: anf-chocolate
