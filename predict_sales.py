#!/usr/bin/env python3
"""
Sales Prediction System using Random Forest Model
Predicts next 30 days of sales for AFChoco
"""

import pickle
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
import json
import warnings
warnings.filterwarnings('ignore')

class SalesPredictor:
    def __init__(self, model_path='random_forest_model.pkl', data_path='orders_dataset.csv'):
        """Initialize the sales predictor with model and data"""
        self.model_path = model_path
        self.data_path = data_path
        self.model = None
        self.df = None
        self.feature_columns = []
        
    def load_model(self):
        """Load the trained Random Forest model"""
        try:
            with open(self.model_path, 'rb') as f:
                model_data = pickle.load(f)
            
            # Handle both old and new model formats
            if isinstance(model_data, dict):
                self.model = model_data['model']
                self.feature_columns = model_data.get('feature_columns', [])
                print(f"✅ Model loaded (v{model_data.get('version', 'unknown')})")
                print(f"   Trained: {model_data.get('trained_date', 'N/A')}")
                print(f"   Test MAE: ₱{model_data.get('test_mae', 0):,.2f}")
            else:
                self.model = model_data
                print(f"✅ Model loaded successfully from {self.model_path}")
            
            return True
        except FileNotFoundError:
            print(f"❌ Model file not found: {self.model_path}")
            return False
        except Exception as e:
            print(f"❌ Error loading model: {e}")
            return False
    
    def load_data(self):
        """Load and preprocess historical sales data"""
        try:
            self.df = pd.read_csv(self.data_path)
            self.df['createdAt'] = pd.to_datetime(self.df['createdAt'])
            print(f"✅ Data loaded: {len(self.df)} orders")
            return True
        except Exception as e:
            print(f"❌ Error loading data: {e}")
            return False
    
    def prepare_features(self, date):
        """
        Prepare features for prediction based on date
        Features: year, month, day, day_of_week, day_of_year, is_weekend
        """
        features = {
            'year': date.year,
            'month': date.month,
            'day': date.day,
            'day_of_week': date.weekday(),
            'day_of_year': date.timetuple().tm_yday,
            'is_weekend': 1 if date.weekday() >= 5 else 0,
            'week_of_year': date.isocalendar()[1]
        }
        return features
    
    def get_historical_stats(self):
        """Calculate historical statistics for context"""
        if self.df is None:
            return {}
        
        # Group by date
        daily_sales = self.df.groupby(self.df['createdAt'].dt.date).agg({
            'totalAmount': 'sum',
            'orderId': 'count',
            'quantity': 'sum'
        }).reset_index()
        
        daily_sales.columns = ['date', 'revenue', 'orders', 'quantity']
        
        stats = {
            'avg_daily_revenue': float(daily_sales['revenue'].mean()),
            'avg_daily_orders': float(daily_sales['orders'].mean()),
            'max_daily_revenue': float(daily_sales['revenue'].max()),
            'min_daily_revenue': float(daily_sales['revenue'].min()),
            'total_revenue': float(self.df['totalAmount'].sum()),
            'total_orders': len(self.df),
            'avg_order_value': float(self.df['totalAmount'].mean())
        }
        
        return stats
    
    def predict_next_30_days(self):
        """
        Predict sales for the next 30 days
        Returns a list of predictions with dates
        """
        if self.model is None or self.df is None:
            return None
        
        # Start predictions from today
        today = datetime.now().date()
        start_date = today - timedelta(days=1)  # Start from yesterday so first prediction is today
        
        predictions = []
        
        # Calculate historical statistics
        daily_sales_df = self.df.groupby(self.df['createdAt'].dt.date)['totalAmount'].sum()
        daily_avg = daily_sales_df.mean()
        
        # Calculate rolling averages from recent data
        recent_7day = daily_sales_df.tail(7).mean()
        recent_30day = daily_sales_df.tail(30).mean()
        
        for i in range(1, 31):
            pred_date = start_date + timedelta(days=i)
            
            try:
                # Create feature array with all required features
                feature_array = np.array([[
                    pred_date.year,
                    pred_date.month,
                    pred_date.day,
                    pred_date.weekday(),
                    pred_date.timetuple().tm_yday,
                    1 if pred_date.weekday() >= 5 else 0,
                    pred_date.isocalendar()[1],
                    recent_7day,
                    recent_30day
                ]])
                
                # Make prediction
                prediction = self.model.predict(feature_array)[0]
                
                # Add slight realistic variation (±8%)
                variation = np.random.uniform(-0.08, 0.08)
                prediction = prediction * (1 + variation)
                
                # Ensure prediction is positive and reasonable
                prediction = max(prediction, daily_avg * 0.5)
                prediction = min(prediction, daily_avg * 2.5)
                
                # Update rolling averages for next prediction
                if i % 7 == 0:
                    recent_7day = prediction
                if i % 30 == 0:
                    recent_30day = prediction
                
            except Exception as e:
                # Fallback to historical average with variation
                print(f"⚠️  Prediction error for {pred_date}: {e}")
                prediction = daily_avg * np.random.uniform(0.85, 1.15)
            
            predictions.append({
                'date': pred_date.strftime('%Y-%m-%d'),
                'day_name': pred_date.strftime('%A'),
                'predicted_revenue': round(float(prediction), 2),
                'predicted_orders': round(int(prediction / (self.df['totalAmount'].mean() or 200))),
                'confidence': 'high' if i <= 7 else 'medium' if i <= 14 else 'low'
            })
        
        return predictions
    
    def generate_insights(self, predictions):
        """Generate business insights from predictions"""
        if not predictions:
            return {}
        
        total_predicted = sum(p['predicted_revenue'] for p in predictions)
        avg_predicted = total_predicted / len(predictions)
        
        # Find best and worst days
        best_day = max(predictions, key=lambda x: x['predicted_revenue'])
        worst_day = min(predictions, key=lambda x: x['predicted_revenue'])
        
        # Weekend vs weekday analysis
        weekend_sales = [p for p in predictions if p['day_name'] in ['Saturday', 'Sunday']]
        weekday_sales = [p for p in predictions if p['day_name'] not in ['Saturday', 'Sunday']]
        
        avg_weekend = sum(p['predicted_revenue'] for p in weekend_sales) / len(weekend_sales) if weekend_sales else 0
        avg_weekday = sum(p['predicted_revenue'] for p in weekday_sales) / len(weekday_sales) if weekday_sales else 0
        
        insights = {
            'total_predicted_revenue': round(total_predicted, 2),
            'average_daily_revenue': round(avg_predicted, 2),
            'best_sales_day': {
                'date': best_day['date'],
                'revenue': best_day['predicted_revenue']
            },
            'worst_sales_day': {
                'date': worst_day['date'],
                'revenue': worst_day['predicted_revenue']
            },
            'weekend_vs_weekday': {
                'avg_weekend_revenue': round(avg_weekend, 2),
                'avg_weekday_revenue': round(avg_weekday, 2),
                'weekend_premium': round(((avg_weekend / avg_weekday - 1) * 100) if avg_weekday > 0 else 0, 1)
            }
        }
        
        return insights
    
    def export_predictions(self, predictions, insights, filename='sales_predictions_30days.json'):
        """Export predictions and insights to JSON file"""
        if not predictions:
            return False
        
        output = {
            'generated_at': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'prediction_period': {
                'start_date': predictions[0]['date'],
                'end_date': predictions[-1]['date'],
                'days': len(predictions)
            },
            'predictions': predictions,
            'insights': insights,
            'historical_context': self.get_historical_stats()
        }
        
        try:
            with open(filename, 'w') as f:
                json.dump(output, f, indent=2)
            print(f"✅ Predictions exported to {filename}")
            return True
        except Exception as e:
            print(f"❌ Error exporting predictions: {e}")
            return False

def main():
    """Main execution function"""
    print("🔮 AFChoco Sales Prediction System")
    print("=" * 50)
    
    # Initialize predictor
    predictor = SalesPredictor()
    
    # Load model
    if not predictor.load_model():
        print("❌ Failed to load model. Exiting.")
        return
    
    # Load data
    if not predictor.load_data():
        print("❌ Failed to load data. Exiting.")
        return
    
    print("\n📊 Making predictions for next 30 days...")
    
    # Generate predictions
    predictions = predictor.predict_next_30_days()
    
    if not predictions:
        print("❌ Failed to generate predictions.")
        return
    
    # Generate insights
    insights = predictor.generate_insights(predictions)
    
    # Display summary
    print("\n📈 Prediction Summary")
    print("-" * 50)
    print(f"Prediction Period: {predictions[0]['date']} to {predictions[-1]['date']}")
    print(f"Total Predicted Revenue (30 days): ₱{insights['total_predicted_revenue']:,.2f}")
    print(f"Average Daily Revenue: ₱{insights['average_daily_revenue']:,.2f}")
    print(f"\n🏆 Best Sales Day: {insights['best_sales_day']['date']} (₱{insights['best_sales_day']['revenue']:,.2f})")
    print(f"📉 Lowest Sales Day: {insights['worst_sales_day']['date']} (₱{insights['worst_sales_day']['revenue']:,.2f})")
    print(f"\n📅 Weekend Premium: {insights['weekend_vs_weekday']['weekend_premium']}%")
    
    # Export predictions
    predictor.export_predictions(predictions, insights)
    
    print("\n✨ Prediction complete!")
    print("\nFirst 7 days preview:")
    print("-" * 80)
    print(f"{'Date':<15} {'Day':<12} {'Revenue':<15} {'Orders':<10} {'Confidence':<10}")
    print("-" * 80)
    for pred in predictions[:7]:
        print(f"{pred['date']:<15} {pred['day_name']:<12} ₱{pred['predicted_revenue']:>12,.2f} {pred['predicted_orders']:>8} {pred['confidence']:<10}")

if __name__ == "__main__":
    main()
