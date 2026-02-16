// Express Server with Firebase Backend
import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';
import session from 'express-session';

// Import routes
import authRoutes from './routes/auth.js';
import productRoutes from './routes/products.js';
import cartRoutes from './routes/cart.js';
import orderRoutes from './routes/orders.js';
import categoryRoutes from './routes/categories.js';
import commentRoutes from './routes/comments.js';
import predictionsRoutes from './routes/predictions.js';

dotenv.config();

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors({
  origin: ['http://localhost:5500', 'http://localhost:8000', process.env.CLIENT_URL].filter(Boolean),
  credentials: true
}));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Session middleware (optional - Firebase handles auth on client side)
app.use(session({
  secret: process.env.SESSION_SECRET || 'your-secret-key',
  resave: false,
  saveUninitialized: false,
  cookie: { 
    secure: process.env.NODE_ENV === 'production',
    maxAge: 24 * 60 * 60 * 1000 // 24 hours
  }
}));

// Root route - Show main shop page
app.get('/', (req, res) => {
  res.redirect('/main.html');
});

// Serve static files
app.use(express.static('public'));
app.use('/images', express.static('images'));
app.use('/css', express.static('public/css'));
app.use('/js', express.static('public/js'));
app.use('/config', express.static('config'));

// API Routes
app.use('/api/auth', authRoutes);
app.use('/api/products', productRoutes);
app.use('/api/cart', cartRoutes);
app.use('/api/orders', orderRoutes);
app.use('/api/categories', categoryRoutes);
app.use('/api/comments', commentRoutes);
app.use('/api/predictions', predictionsRoutes);

// API documentation endpoint
app.get('/api', (req, res) => {
  res.json({
    name: 'AFChoco API',
    version: '1.0.0',
    description: 'Firebase-powered E-commerce API for A&F Chocolate',
    project: 'anf-chocolate',
    endpoints: {
      health: 'GET /api/health',
      products: {
        list: 'GET /api/products',
        detail: 'GET /api/products/:id',
        create: 'POST /api/products (admin)',
        update: 'PUT /api/products/:id (admin)',
        delete: 'DELETE /api/products/:id (admin)'
      },
      cart: {
        get: 'GET /api/cart (auth)',
        add: 'POST /api/cart/add (auth)',
        update: 'PUT /api/cart/update/:cartItemId (auth)',
        remove: 'DELETE /api/cart/remove/:cartItemId (auth)',
        clear: 'DELETE /api/cart/clear (auth)'
      },
      orders: {
        create: 'POST /api/orders/create (auth)',
        myOrders: 'GET /api/orders/my-orders (auth)',
        detail: 'GET /api/orders/:orderId (auth)',
        adminAll: 'GET /api/orders/admin/all (admin)',
        updateStatus: 'PUT /api/orders/:orderId/status (admin)'
      },
      categories: {
        list: 'GET /api/categories',
        stats: 'GET /api/categories/stats'
      },
      comments: {
        byProduct: 'GET /api/comments/product/:productId',
        create: 'POST /api/comments (auth)',
        update: 'PUT /api/comments/:id (auth)',
        delete: 'DELETE /api/comments/:id (auth)'
      },
      auth: {
        createProfile: 'POST /api/auth/create-profile (auth)',
        getProfile: 'GET /api/auth/profile (auth)',
        updateProfile: 'PUT /api/auth/profile (auth)'
      }
    },
    examples: {
      demo: '/firebase-example.html',
      health: '/api/health',
      products: '/api/products',
      categories: '/api/categories'
    },
    documentation: {
      schema: 'See DATABASE_SCHEMA.md',
      migration: 'See FIREBASE_MIGRATION.md',
      alignment: 'See SCHEMA_ALIGNMENT.md'
    },
    categories: ['WHITE', 'DARK', 'MILK', 'MIXED', 'SPECIALTY'],
    orderStatuses: ['PENDING', 'PAID', 'APPROVED', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED']
  });
});

// Health check endpoint
app.get('/api/health', (req, res) => {
  res.json({ status: 'ok', message: 'AFChoco API is running' });
});

// Error handling middleware
app.use((err, req, res, next) => {
  console.error('Error:', err);
  res.status(err.status || 500).json({
    error: err.message || 'Internal server error',
    ...(process.env.NODE_ENV === 'development' && { stack: err.stack })
  });
});

// Start server
app.listen(PORT, () => {
  console.log(`🚀 AFChoco server running on http://localhost:${PORT}`);
  console.log(`📊 Environment: ${process.env.NODE_ENV || 'development'}`);
});

export default app;
