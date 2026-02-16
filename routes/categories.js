// Category Routes
import express from 'express';
import { adminDb } from '../config/firebase-admin.js';
import { verifyToken, verifyAdmin } from './auth.js';

const router = express.Router();

// Category values used in mobile app: WHITE, DARK, MILK, MIXED, SPECIALTY
const VALID_CATEGORIES = [
  { value: 'WHITE', name: 'White Chocolate', gradient: 'linear-gradient(to bottom, #F3E794, #BFB886)' },
  { value: 'DARK', name: 'Dark Chocolate', gradient: 'linear-gradient(to bottom, #55361A, #CDACB1)' },
  { value: 'MILK', name: 'Milk Chocolate', gradient: 'linear-gradient(to bottom, #D97272, #F8DDDD)' },
  { value: 'MIXED', name: 'Mixed Chocolate', gradient: 'linear-gradient(to bottom, #71EEEC, #C1ACAC)' },
  { value: 'SPECIALTY', name: 'Specialty', gradient: 'linear-gradient(to bottom, #E6E6FA, #DDA0DD)' }
];

// Get all categories
router.get('/', async (req, res) => {
  try {
    res.json({ 
      success: true, 
      categories: VALID_CATEGORIES,
      count: VALID_CATEGORIES.length
    });

  } catch (error) {
    console.error('Get categories error:', error);
    res.status(500).json({ error: error.message });
  }
});

// Get products count by category
router.get('/stats', async (req, res) => {
  try {
    const stats = [];
    
    for (const category of VALID_CATEGORIES) {
      const snapshot = await adminDb.collection('products')
        .where('category', '==', category.value)
        .get();
      
      stats.push({
        ...category,
        productCount: snapshot.size
      });
    }
    
    res.json({ 
      success: true, 
      stats
    });

  } catch (error) {
    console.error('Get category stats error:', error);
    res.status(500).json({ error: error.message });
  }
});

export default router;
