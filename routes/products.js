// Product Routes
import express from 'express';
import admin from '../config/firebase-admin.js';
import { adminDb, adminStorage } from '../config/firebase-admin.js';
import { verifyToken, verifyAdmin, optionalAuth } from './auth.js';

const router = express.Router();

// Get all products with optional filtering
router.get('/', async (req, res) => {
  try {
    const { category, search, limit = 50 } = req.query;
    
    let query = adminDb.collection('products');
    
    // Filter by category if provided (categories: WHITE, DARK, MILK, MIXED, SPECIALTY)
    if (category && category !== 'All') {
      query = query.where('category', '==', category.toUpperCase());
    }
    
    // Order by creation date (newest first)
    query = query.orderBy('createdAt', 'desc').limit(parseInt(limit));
    
    const snapshot = await query.get();
    const products = [];
    
    snapshot.forEach(doc => {
      products.push({
        id: doc.id,
        ...doc.data()
      });
    });
    
    // Client-side search filtering (Firestore doesn't support full-text search natively)
    let filteredProducts = products;
    if (search) {
      const searchLower = search.toLowerCase();
      filteredProducts = products.filter(product => 
        product.name?.toLowerCase().includes(searchLower) ||
        product.description?.toLowerCase().includes(searchLower) ||
        product.sku?.toLowerCase().includes(searchLower)
      );
    }
    
    res.json({ 
      success: true, 
      products: filteredProducts,
      count: filteredProducts.length 
    });

  } catch (error) {
    console.error('Get products error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Get single product by ID
router.get('/:id', async (req, res) => {
  try {
    const productDoc = await adminDb.collection('products').doc(req.params.id).get();
    
    if (!productDoc.exists) {
      return res.status(404).json({ error: 'Product not found' });
    }
    
    res.json({ 
      success: true, 
      product: {
        id: productDoc.id,
        ...productDoc.data()
      }
    });

  } catch (error) {
    console.error('Get product error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Create new product (Admin only)
router.post('/', optionalAuth, verifyAdmin, async (req, res) => {
  try {
    const { name, description, price, category, sku, stockLevel, imageUrl } = req.body;
    
    // Validation
    if (!name || !price || !category || !sku) {
      return res.status(400).json({ 
        error: 'Name, price, category, and SKU are required' 
      });
    }
    
    // Validate category (must be one of: WHITE, DARK, MILK, MIXED, SPECIALTY)
    const validCategories = ['WHITE', 'DARK', 'MILK', 'MIXED', 'SPECIALTY'];
    if (!validCategories.includes(category.toUpperCase())) {
      return res.status(400).json({ 
        error: 'Category must be one of: WHITE, DARK, MILK, MIXED, SPECIALTY' 
      });
    }
    
    const newProduct = {
      name,
      description: description || '',
      price: parseFloat(price),
      category: category.toUpperCase(),
      sku,
      stockLevel: parseInt(stockLevel) || 0,
      imageUrl: imageUrl || '',
      salesCount: 0,
      createdAt: admin.firestore.Timestamp.now(),
      updatedAt: admin.firestore.Timestamp.now()
    };
    
    const docRef = await adminDb.collection('products').add(newProduct);
    
    res.status(201).json({ 
      success: true, 
      message: 'Product created',
      productId: docRef.id,
      product: { id: docRef.id, ...newProduct }
    });

  } catch (error) {
    console.error('Create product error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Update product (Admin only)
router.put('/:id', optionalAuth, verifyAdmin, async (req, res) => {
  try {
    const { name, description, price, category, sku, stockLevel, imageUrl } = req.body;
    
    const updates = {
      ...(name && { name }),
      ...(description !== undefined && { description }),
      ...(price && { price: parseFloat(price) }),
      ...(category && { category: category.toUpperCase() }),
      ...(sku && { sku }),
      ...(stockLevel !== undefined && { stockLevel: parseInt(stockLevel) }),
      ...(imageUrl !== undefined && { imageUrl }),
      updatedAt: admin.firestore.Timestamp.now()
    };
    
    await adminDb.collection('products').doc(req.params.id).update(updates);
    
    res.json({ 
      success: true, 
      message: 'Product updated',
      updates
    });

  } catch (error) {
    console.error('Update product error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Delete product (Admin only)
router.delete('/:id', optionalAuth, verifyAdmin, async (req, res) => {
  try {
    await adminDb.collection('products').doc(req.params.id).delete();
    
    res.json({ 
      success: true, 
      message: 'Product deleted' 
    });

  } catch (error) {
    console.error('Delete product error:', error);
    res.status(500).json({ error: error.message });
  }
});

export default router;
