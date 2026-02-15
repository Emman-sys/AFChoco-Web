// Authentication Routes
import express from 'express';
import admin from '../config/firebase-admin.js';
import { adminAuth, adminDb } from '../config/firebase-admin.js';

const router = express.Router();

// Middleware to verify Firebase ID token
export const verifyToken = async (req, res, next) => {
  const authHeader = req.headers.authorization;
  
  if (!authHeader || !authHeader.startsWith('Bearer ')) {
    return res.status(401).json({ error: 'Unauthorized - No token provided' });
  }

  const idToken = authHeader.split('Bearer ')[1];

  try {
    const decodedToken = await adminAuth.verifyIdToken(idToken);
    req.user = decodedToken;
    next();
  } catch (error) {
    console.error('Token verification error:', error);
    return res.status(401).json({ error: 'Unauthorized - Invalid token' });
  }
};

// Sign up new user
router.post('/signup', async (req, res) => {
  try {
    const { email, password, firstName, lastName, phone } = req.body;

    // Create user in Firebase Auth (this is typically done on the client side)
    // But we can create user data in Firestore here
    
    // Validate input
    if (!email || !password) {
      return res.status(400).json({ error: 'Email and password are required' });
    }

    // Note: User creation in Firebase Auth should be done on client side
    // This endpoint is for creating user profile in Firestore
    res.json({ 
      message: 'User registration should be done on client side using Firebase Auth SDK',
      success: false 
    });

  } catch (error) {
    console.error('Signup error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Create user profile in Firestore (called after Firebase Auth signup)
router.post('/create-profile', verifyToken, async (req, res) => {
  try {
    const { username, phoneNumber, address, profilePicture } = req.body;
    const userId = req.user.uid;

    const userProfile = {
      uid: userId,
      email: req.user.email,
      username: username || req.user.email?.split('@')[0] || 'User',
      phoneNumber: phoneNumber || '',
      address: address || '',
      profilePicture: profilePicture || '',
      role: 'user', // default role
      createdAt: admin.firestore.Timestamp.now(),
      updatedAt: admin.firestore.Timestamp.now()
    };

    await adminDb.collection('users').doc(userId).set(userProfile);

    res.json({ 
      success: true, 
      message: 'User profile created',
      user: userProfile 
    });

  } catch (error) {
    console.error('Create profile error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Get current user profile
router.get('/profile', verifyToken, async (req, res) => {
  try {
    const userId = req.user.uid;
    const userDoc = await adminDb.collection('users').doc(userId).get();

    if (!userDoc.exists) {
      return res.status(404).json({ error: 'User profile not found' });
    }

    res.json({ 
      success: true, 
      user: userDoc.data() 
    });

  } catch (error) {
    console.error('Get profile error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Update user profile
router.put('/profile', verifyToken, async (req, res) => {
  try {
    const userId = req.user.uid;
    const { username, phoneNumber, address, profilePicture } = req.body;

    const updates = {
      ...(username && { username }),
      ...(phoneNumber !== undefined && { phoneNumber }),
      ...(address !== undefined && { address }),
      ...(profilePicture !== undefined && { profilePicture }),
      updatedAt: admin.firestore.Timestamp.now()
    };

    await adminDb.collection('users').doc(userId).update(updates);

    res.json({ 
      success: true, 
      message: 'Profile updated',
      updates 
    });

  } catch (error) {
    console.error('Update profile error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Verify admin role
export const verifyAdmin = async (req, res, next) => {
  try {
    const userId = req.user.uid;
    const userDoc = await adminDb.collection('users').doc(userId).get();
    
    if (!userDoc.exists || userDoc.data().role !== 'admin') {
      return res.status(403).json({ error: 'Forbidden - Admin access required' });
    }
    
    next();
  } catch (error) {
    console.error('Admin verification error:', error);
    res.status(500).json({ error: error.message });
  }
};

export default router;
