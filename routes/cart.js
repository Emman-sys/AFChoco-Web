// Cart Routes
import express from 'express';
import admin from '../config/firebase-admin.js';
import { adminDb } from '../config/firebase-admin.js';
import { verifyToken } from './auth.js';

const router = express.Router();

// Get user's cart
router.get('/', verifyToken, async (req, res) => {
  try {
    const userId = req.user.uid;
    
    const cartSnapshot = await adminDb.collection('cart')
      .where('userId', '==', userId)
      .orderBy('addedAt', 'desc')
      .get();
    
    const cartItems = [];
    cartSnapshot.forEach(doc => {
      cartItems.push({
        id: doc.id,
        ...doc.data()
      });
    });
    
    // Calculate totals
    const subtotal = cartItems.reduce((sum, item) => 
      sum + (item.productPrice * item.quantity), 0
    );
    
    res.json({ 
      success: true, 
      cart: {
        items: cartItems,
        count: cartItems.length,
        subtotal: subtotal
      }
    });

  } catch (error) {
    console.error('Get cart error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Add item to cart
router.post('/add', verifyToken, async (req, res) => {
  try {
    const userId = req.user.uid;
    const { productId, productName, productPrice, productImageUrl, quantity = 1 } = req.body;
    
    if (!productId || !productName || !productPrice) {
      return res.status(400).json({ 
        error: 'Product ID, name, and price are required' 
      });
    }
    
    // Check if item already exists in cart
    const existingItems = await adminDb.collection('cart')
      .where('userId', '==', userId)
      .where('productId', '==', productId)
      .get();
    
    if (!existingItems.empty) {
      // Update existing item quantity
      const existingDoc = existingItems.docs[0];
      const existingData = existingDoc.data();
      const newQuantity = existingData.quantity + parseInt(quantity);
      
      await existingDoc.ref.update({
        quantity: newQuantity,
        updatedAt: admin.firestore.Timestamp.now()
      });
      
      res.json({ 
        success: true, 
        message: 'Cart item quantity updated',
        cartItemId: existingDoc.id
      });
    } else {
      // Create new cart item
      const newCartItem = {
        userId,
        productId,
        productName,
        productPrice: parseFloat(productPrice),
        productImageUrl: productImageUrl || '',
        quantity: parseInt(quantity),
        addedAt: admin.firestore.Timestamp.now(),
        updatedAt: admin.firestore.Timestamp.now()
      };
      
      const docRef = await adminDb.collection('cart').add(newCartItem);
      
      res.json({ 
        success: true, 
        message: 'Item added to cart',
        cartItemId: docRef.id
      });
    }

  } catch (error) {
    console.error('Add to cart error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Update cart item quantity
router.put('/update/:cartItemId', verifyToken, async (req, res) => {
  try {
    const userId = req.user.uid;
    const { cartItemId } = req.params;
    const { quantity } = req.body;
    
    if (quantity === undefined) {
      return res.status(400).json({ 
        error: 'Quantity is required' 
      });
    }
    
    const cartItemRef = adminDb.collection('cart').doc(cartItemId);
    const cartItemDoc = await cartItemRef.get();
    
    if (!cartItemDoc.exists) {
      return res.status(404).json({ error: 'Cart item not found' });
    }
    
    // Verify ownership
    if (cartItemDoc.data().userId !== userId) {
      return res.status(403).json({ error: 'Access denied' });
    }
    
    if (parseInt(quantity) <= 0) {
      // Remove item if quantity is 0 or less
      await cartItemRef.delete();
      res.json({ 
        success: true, 
        message: 'Cart item removed'
      });
    } else {
      // Update quantity
      await cartItemRef.update({
        quantity: parseInt(quantity),
        updatedAt: admin.firestore.Timestamp.now()
      });
      
      res.json({ 
        success: true, 
        message: 'Cart item updated'
      });
    }

  } catch (error) {
    console.error('Update cart error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Remove item from cart
router.delete('/remove/:cartItemId', verifyToken, async (req, res) => {
  try {
    const userId = req.user.uid;
    const { cartItemId } = req.params;
    
    const cartItemRef = adminDb.collection('cart').doc(cartItemId);
    const cartItemDoc = await cartItemRef.get();
    
    if (!cartItemDoc.exists) {
      return res.status(404).json({ error: 'Cart item not found' });
    }
    
    // Verify ownership
    if (cartItemDoc.data().userId !== userId) {
      return res.status(403).json({ error: 'Access denied' });
    }
    
    await cartItemRef.delete();
    
    res.json({ 
      success: true, 
      message: 'Item removed from cart'
    });

  } catch (error) {
    console.error('Remove from cart error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Clear cart
router.delete('/clear', verifyToken, async (req, res) => {
  try {
    const userId = req.user.uid;
    
    // Delete all cart items for this user
    const cartSnapshot = await adminDb.collection('cart')
      .where('userId', '==', userId)
      .get();
    
    const batch = adminDb.batch();
    cartSnapshot.docs.forEach(doc => {
      batch.delete(doc.ref);
    });
    
    await batch.commit();
    
    res.json({ 
      success: true, 
      message: 'Cart cleared' 
    });

  } catch (error) {
    console.error('Clear cart error:', error);
    res.status(500).json({ error: error.message });
  }
});

export default router;
