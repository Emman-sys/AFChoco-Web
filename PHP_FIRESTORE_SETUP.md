# PHP with Firestore Setup Guide

## Architecture

```
Browser
   ↓
PHP Server (Port 8000)
   ↓
Node.js API Server (Port 3000)
   ↓
Firebase Firestore
```

## How It Works

1. **PHP Frontend** (port 8000) - Serves MainPage.php and other PHP UI files
2. **Node.js Backend** (port 3000) - Handles API requests to Firestore
3. **Firebase API Bridge** (firebase_api.php) - PHP client that calls Node.js API

## Starting the Servers

### Terminal 1: Start Node.js API Server
```bash
cd "/home/plantsed11/VSCode Projects/AFChoco-Web"
npm run dev
```
Server runs on: http://localhost:3000

### Terminal 2: Start PHP Server
```bash
cd "/home/plantsed11/VSCode Projects/AFChoco-Web"
php -S localhost:8000
```
Server runs on: http://localhost:8000

## Access Your Application

**Main Page**: http://localhost:8000/MainPage.php

## Files Created

### 1. firebase_api.php
PHP client that communicates with the Node.js API.

**Methods:**
- `getProducts($category, $search)` - Get products
- `addToCart($productId, $quantity)` - Add to cart
- `getCart()` - Get cart items
- `createOrder($data)` - Create order
- `getCategories()` - Get categories

### 2. db_connect.php
Compatibility layer that makes the Node.js/Firestore backend work with existing PHP code.

**Features:**
- Mimics mysqli interface
- Converts SQL queries to API calls
- Maps Firestore data to MySQL format
- Category ID to name conversion

### 3. Updated PHP Files
MainPage.php and other files now use Firestore through the API bridge.

## Quick Start

```bash
# Terminal 1: Start Node.js backend
npm run dev

# Terminal 2: Start PHP frontend (in a new terminal)
php -S localhost:8000

# Open in browser
# http://localhost:8000/MainPage.php
```

## Configuration

Edit `firebase_api.php` if your Node.js server runs on a different port:

```php
$firebaseAPI = new FirebaseAPI('http://localhost:3000');
```

## Category Mapping

The system automatically converts between MySQL category IDs and Firestore category names:

| MySQL ID | Firestore Name |
|----------|----------------|
| 1        | DARK           |
| 2        | WHITE          |
| 3        | MILK           |
| 4        | MIXED          |
| 5        | SPECIALTY      |

## Testing

### Test API Connection
```bash
curl http://localhost:3000/api/health
```

### Test PHP Server
```bash
curl http://localhost:8000/MainPage.php
```

## Troubleshooting

### Port Already in Use
```bash
# Kill process on port 3000
lsof -ti:3000 | xargs kill -9

# Kill process on port 8000
lsof -ti:8000 | xargs kill -9
```

### API Connection Failed
Make sure Node.js server is running on port 3000:
```bash
curl http://localhost:3000/api/health
```

Should return: `{"status":"ok","message":"AFChoco API is running"}`

## Development Notes

- PHP code uses familiar mysqli syntax
- All database operations route through Node.js API
- Firebase Auth tokens stored in PHP sessions
- Automatic data format conversion between MySQL and Firestore
