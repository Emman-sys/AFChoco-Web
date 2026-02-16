// Order Routes
import express from 'express';
import admin from '../config/firebase-admin.js';
import { adminDb } from '../config/firebase-admin.js';
import { verifyToken, verifyAdmin, optionalAuth } from './auth.js';

const router = express.Router();

// Create new order (checkout)
router.post('/create', verifyToken, async (req, res) => {
  try {
    const userId = req.user.uid;
    const { items, deliveryAddress, phoneNumber, notes } = req.body;
    
    if (!items || items.length === 0) {
      return res.status(400).json({ error: 'Cart is empty' });
    }
    
    if (!deliveryAddress || !phoneNumber) {
      return res.status(400).json({ error: 'Delivery address and phone number are required' });
    }
    
    // Get user info
    const userDoc = await adminDb.collection('users').doc(userId).get();
    const userData = userDoc.exists ? userDoc.data() : {};
    
    // Calculate totals
    const subtotal = items.reduce((sum, item) => 
      sum + (item.productPrice * item.quantity), 0
    );
    const deliveryFee = 50.00; // Fixed delivery fee
    const totalAmount = subtotal + deliveryFee;
    
    const newOrder = {
      userId,
      userName: userData.username || req.user.email?.split('@')[0] || 'Unknown',
      userEmail: req.user.email || '',
      items,
      deliveryAddress,
      phoneNumber,
      subtotal: parseFloat(subtotal.toFixed(2)),
      deliveryFee: parseFloat(deliveryFee.toFixed(2)),
      totalAmount: parseFloat(totalAmount.toFixed(2)),
      orderStatus: 'PENDING',
      paymentStatus: 'PENDING',
      notes: notes || '',
      createdAt: admin.firestore.Timestamp.now()
    };
    
    const orderRef = await adminDb.collection('orders').add(newOrder);
    
    // Clear user's cart after successful order
    const cartSnapshot = await adminDb.collection('cart')
      .where('userId', '==', userId)
      .get();
    
    const batch = adminDb.batch();
    cartSnapshot.docs.forEach(doc => {
      batch.delete(doc.ref);
    });
    await batch.commit();
    
    res.status(201).json({ 
      success: true, 
      message: 'Order created successfully',
      orderId: orderRef.id,
      order: { id: orderRef.id, ...newOrder }
    });

  } catch (error) {
    console.error('Create order error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Get user's orders
router.get('/my-orders', verifyToken, async (req, res) => {
  try {
    const userId = req.user.uid;
    
    const ordersSnapshot = await adminDb.collection('orders')
      .where('userId', '==', userId)
      .orderBy('createdAt', 'desc')
      .get();
    
    const orders = [];
    ordersSnapshot.forEach(doc => {
      orders.push({
        id: doc.id,
        ...doc.data()
      });
    });
    
    res.json({ 
      success: true, 
      orders,
      count: orders.length
    });

  } catch (error) {
    console.error('Get orders error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Get single order
router.get('/:orderId', verifyToken, async (req, res) => {
  try {
    const userId = req.user.uid;
    const { orderId } = req.params;
    
    const orderDoc = await adminDb.collection('orders').doc(orderId).get();
    
    if (!orderDoc.exists) {
      return res.status(404).json({ error: 'Order not found' });
    }
    
    const orderData = orderDoc.data();
    
    // Check if user owns this order or is admin
    const userDoc = await adminDb.collection('users').doc(userId).get();
    const isAdmin = userDoc.exists && userDoc.data().role === 'admin';
    
    if (orderData.userId !== userId && !isAdmin) {
      return res.status(403).json({ error: 'Access denied' });
    }
    
    res.json({ 
      success: true, 
      order: {
        id: orderDoc.id,
        ...orderData
      }
    });

  } catch (error) {
    console.error('Get order error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Get all orders (Admin only)
router.get('/admin/all', optionalAuth, verifyAdmin, async (req, res) => {
  try {
    const { status, limit = 100 } = req.query;
    
    let query = adminDb.collection('orders');
    
    if (status) {
      query = query.where('orderStatus', '==', status.toUpperCase());
    }
    
    query = query.orderBy('createdAt', 'desc').limit(parseInt(limit));
    
    const snapshot = await query.get();
    const orders = [];
    
    snapshot.forEach(doc => {
      orders.push({
        id: doc.id,
        ...doc.data()
      });
    });
    
    res.json({ 
      success: true, 
      orders,
      count: orders.length
    });

  } catch (error) {
    console.error('Get all orders error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Update order status (Admin only)
router.put('/:orderId/status', optionalAuth, verifyAdmin, async (req, res) => {
  try {
    const { orderId } = req.params;
    const { orderStatus } = req.body;
    
    const validStatuses = ['PENDING', 'PAID', 'APPROVED', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED'];
    
    if (!validStatuses.includes(orderStatus.toUpperCase())) {
      return res.status(400).json({ 
        error: 'Invalid status',
        validStatuses
      });
    }
    
    const updates = {
      orderStatus: orderStatus.toUpperCase()
    };
    
    // Add timestamp based on status
    const status = orderStatus.toUpperCase();
    if (status === 'PAID') {
      updates.paidAt = admin.firestore.Timestamp.now();
      updates.paymentStatus = 'PAID';
    } else if (status === 'APPROVED') {
      updates.approvedAt = admin.firestore.Timestamp.now();
    } else if (status === 'SHIPPED') {
      updates.shippedAt = admin.firestore.Timestamp.now();
    } else if (status === 'DELIVERED') {
      updates.deliveredAt = admin.firestore.Timestamp.now();
    }
    
    await adminDb.collection('orders').doc(orderId).update(updates);
    
    res.json({ 
      success: true, 
      message: 'Order status updated',
      orderStatus: updates.orderStatus
    });

  } catch (error) {
    console.error('Update order status error:', error);
    res.status(500).json({ error: error.message });
  }
});

export default router;
