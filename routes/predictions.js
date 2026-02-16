// Sales Prediction API Routes
import express from 'express';
import { exec } from 'child_process';
import { promisify } from 'util';
import fs from 'fs/promises';
import path from 'path';

const router = express.Router();
const execAsync = promisify(exec);

// Get sales predictions for next 30 days
router.get('/sales/30days', async (req, res) => {
  try {
    console.log('📊 Generating sales predictions...');
    
    // Run the Python prediction script
    const { stdout, stderr } = await execAsync('python3 predict_sales.py', {
      cwd: process.cwd(),
      timeout: 30000 // 30 second timeout
    });
    
    if (stderr && !stderr.includes('warning')) {
      console.warn('Python script warnings:', stderr);
    }
    
    console.log('✅ Prediction script executed');
    
    // Read the generated predictions file
    const predictionsPath = path.join(process.cwd(), 'sales_predictions_30days.json');
    const predictionsData = await fs.readFile(predictionsPath, 'utf-8');
    const predictions = JSON.parse(predictionsData);
    
    res.json({
      success: true,
      ...predictions
    });
    
  } catch (error) {
    console.error('❌ Prediction error:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to generate predictions',
      message: error.message
    });
  }
});

// Get cached predictions (without regenerating)
router.get('/sales/cached', async (req, res) => {
  try {
    const predictionsPath = path.join(process.cwd(), 'sales_predictions_30days.json');
    
    // Check if file exists
    try {
      await fs.access(predictionsPath);
    } catch {
      return res.status(404).json({
        success: false,
        error: 'No cached predictions found',
        message: 'Please generate predictions first by calling /api/predictions/sales/30days'
      });
    }
    
    const predictionsData = await fs.readFile(predictionsPath, 'utf-8');
    const predictions = JSON.parse(predictionsData);
    
    res.json({
      success: true,
      cached: true,
      ...predictions
    });
    
  } catch (error) {
    console.error('❌ Error reading cached predictions:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to read cached predictions',
      message: error.message
    });
  }
});

// Get prediction summary only (faster)
router.get('/sales/summary', async (req, res) => {
  try {
    const predictionsPath = path.join(process.cwd(), 'sales_predictions_30days.json');
    
    // Check if file exists
    try {
      await fs.access(predictionsPath);
    } catch {
      return res.status(404).json({
        success: false,
        error: 'No predictions found',
        message: 'Please generate predictions first'
      });
    }
    
    const predictionsData = await fs.readFile(predictionsPath, 'utf-8');
    const predictions = JSON.parse(predictionsData);
    
    // Return only summary information
    res.json({
      success: true,
      generated_at: predictions.generated_at,
      prediction_period: predictions.prediction_period,
      insights: predictions.insights,
      historical_context: predictions.historical_context
    });
    
  } catch (error) {
    console.error('❌ Error reading prediction summary:', error);
    res.status(500).json({
      success: false,
      error: 'Failed to read prediction summary',
      message: error.message
    });
  }
});

export default router;
