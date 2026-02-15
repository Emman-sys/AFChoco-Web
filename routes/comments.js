// Comment Routes
import express from 'express';
import admin from '../config/firebase-admin.js';
import { adminDb } from '../config/firebase-admin.js';
import { verifyToken } from './auth.js';

const router = express.Router();

// Get comments for a product
router.get('/product/:productId', async (req, res) => {
  try {
    const { productId } = req.params;
    
    const snapshot = await adminDb.collection('comments')
      .where('productId', '==', productId)
      .orderBy('createdAt', 'desc')
      .get();
    
    const comments = [];
    snapshot.forEach(doc => {
      comments.push({
        id: doc.id,
        ...doc.data()
      });
    });
    
    res.json({ 
      success: true, 
      comments,
      count: comments.length
    });

  } catch (error) {
    console.error('Get comments error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Add comment (requires authentication)
router.post('/', verifyToken, async (req, res) => {
  try {
    const { productId, text, rating } = req.body;
    const userId = req.user.uid;
    
    if (!productId || !text) {
      return res.status(400).json({ 
        error: 'Product ID and comment text are required' 
      });
    }
    
    // Get user info
    const userDoc = await adminDb.collection('users').doc(userId).get();
    const userData = userDoc.data();
    
    const newComment = {
      productId,
      userId,
      userName: userData?.username || req.user.email?.split('@')[0] || 'Anonymous',
      text,
      rating: rating ? parseInt(rating) : null,
      createdAt: admin.firestore.Timestamp.now(),
      updatedAt: admin.firestore.Timestamp.now()
    };
    
    const docRef = await adminDb.collection('comments').add(newComment);
    
    res.status(201).json({ 
      success: true, 
      message: 'Comment added',
      commentId: docRef.id,
      comment: { id: docRef.id, ...newComment }
    });

  } catch (error) {
    console.error('Add comment error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Update comment (only by owner)
router.put('/:id', verifyToken, async (req, res) => {
  try {
    const { id } = req.params;
    const { text, rating } = req.body;
    const userId = req.user.uid;
    
    const commentDoc = await adminDb.collection('comments').doc(id).get();
    
    if (!commentDoc.exists) {
      return res.status(404).json({ error: 'Comment not found' });
    }
    
    const commentData = commentDoc.data();
    
    if (commentData.userId !== userId) {
      return res.status(403).json({ error: 'Access denied' });
    }
    
    const updates = {
      ...(text && { text }),
      ...(rating !== undefined && { rating: parseInt(rating) }),
      updatedAt: admin.firestore.Timestamp.now()
    };
    
    await adminDb.collection('comments').doc(id).update(updates);
    
    res.json({ 
      success: true, 
      message: 'Comment updated',
      updates
    });

  } catch (error) {
    console.error('Update comment error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Delete comment (only by owner or admin)
router.delete('/:id', verifyToken, async (req, res) => {
  try {
    const { id } = req.params;
    const userId = req.user.uid;
    
    const commentDoc = await adminDb.collection('comments').doc(id).get();
    
    if (!commentDoc.exists) {
      return res.status(404).json({ error: 'Comment not found' });
    }
    
    const commentData = commentDoc.data();
    
    // Check if user is owner or admin
    const userDoc = await adminDb.collection('users').doc(userId).get();
    const isAdmin = userDoc.exists && userDoc.data().role === 'admin';
    
    if (commentData.userId !== userId && !isAdmin) {
      return res.status(403).json({ error: 'Access denied' });
    }
    
    await adminDb.collection('comments').doc(id).delete();
    
    res.json({ 
      success: true, 
      message: 'Comment deleted' 
    });

  } catch (error) {
    console.error('Delete comment error:', error);
    res.status(500).json({ error: error.message });
  }
});

export default router;
