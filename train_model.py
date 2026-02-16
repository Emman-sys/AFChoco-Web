#!/usr/bin/env python3
"""
Train a Random Forest model for sales prediction using historical data
"""

import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestRegressor
from sklearn.model_selection import train_test_split
from sklearn.metrics import mean_absolute_error, r2_score
import pickle
from datetime import datetime

print("🤖 Training Random Forest Model for Sales Prediction")
print("=" * 60)

# Load data
print("\n📊 Loading historical sales data...")
df = pd.read_csv('orders_dataset.csv')
df['createdAt'] = pd.to_datetime(df['createdAt'])

print(f"✅ Loaded {len(df)} orders from {df['createdAt'].min()} to {df['createdAt'].max()}")

# Aggregate daily sales
print("\n📈 Aggregating daily sales...")
daily_sales = df.groupby(df['createdAt'].dt.date).agg({
    'totalAmount': 'sum',
    'orderId': 'count',
    'quantity': 'sum'
}).reset_index()

daily_sales.columns = ['date', 'revenue', 'orders', 'quantity']
daily_sales['date'] = pd.to_datetime(daily_sales['date'])

print(f"✅ Aggregated to {len(daily_sales)} days of sales data")

# Feature engineering
print("\n🔧 Engineering features...")
daily_sales['year'] = daily_sales['date'].dt.year
daily_sales['month'] = daily_sales['date'].dt.month
daily_sales['day'] = daily_sales['date'].dt.day
daily_sales['day_of_week'] = daily_sales['date'].dt.dayofweek
daily_sales['day_of_year'] = daily_sales['date'].dt.dayofyear
daily_sales['is_weekend'] = (daily_sales['day_of_week'] >= 5).astype(int)
daily_sales['week_of_year'] = daily_sales['date'].dt.isocalendar().week

# Calculate rolling averages
daily_sales['revenue_7day_avg'] = daily_sales['revenue'].rolling(7, min_periods=1).mean()
daily_sales['revenue_30day_avg'] = daily_sales['revenue'].rolling(30, min_periods=1).mean()

# Prepare features and target
feature_columns = [
    'year', 'month', 'day', 'day_of_week', 'day_of_year', 
    'is_weekend', 'week_of_year', 'revenue_7day_avg', 'revenue_30day_avg'
]

X = daily_sales[feature_columns]
y = daily_sales['revenue']

print(f"✅ Features: {', '.join(feature_columns)}")
print(f"✅ Target: revenue")

# Split data
print("\n✂️  Splitting data (80% train, 20% test)...")
X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, random_state=42
)

print(f"✅ Training set: {len(X_train)} samples")
print(f"✅ Test set: {len(X_test)} samples")

# Train model
print("\n🌲 Training Random Forest model...")
model = RandomForestRegressor(
    n_estimators=100,
    max_depth=10,
    min_samples_split=5,
    min_samples_leaf=2,
    random_state=42,
    n_jobs=-1
)

model.fit(X_train, y_train)
print("✅ Model trained successfully!")

# Evaluate model
print("\n📊 Evaluating model performance...")
train_pred = model.predict(X_train)
test_pred = model.predict(X_test)

train_mae = mean_absolute_error(y_train, train_pred)
test_mae = mean_absolute_error(y_test, test_pred)
train_r2 = r2_score(y_train, train_pred)
test_r2 = r2_score(y_test, test_pred)

print(f"\n📈 Training Performance:")
print(f"   MAE: ₱{train_mae:,.2f}")
print(f"   R² Score: {train_r2:.3f}")

print(f"\n📊 Test Performance:")
print(f"   MAE: ₱{test_mae:,.2f}")
print(f"   R² Score: {test_r2:.3f}")

# Feature importance
print("\n🎯 Feature Importance:")
feature_importance = pd.DataFrame({
    'feature': feature_columns,
    'importance': model.feature_importances_
}).sort_values('importance', ascending=False)

for _, row in feature_importance.iterrows():
    print(f"   {row['feature']:<20} {row['importance']:.3f}")

# Save model
model_file = 'random_forest_model.pkl'
print(f"\n💾 Saving model to {model_file}...")

# Save model with metadata
model_data = {
    'model': model,
    'feature_columns': feature_columns,
    'trained_date': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
    'train_mae': train_mae,
    'test_mae': test_mae,
    'train_r2': train_r2,
    'test_r2': test_r2,
    'version': '1.0'
}

with open(model_file, 'wb') as f:
    pickle.dump(model_data, f)

print(f"✅ Model saved successfully!")

# Test prediction
print("\n🧪 Testing prediction for tomorrow...")
tomorrow = datetime.now().date()
test_features = [[
    tomorrow.year,
    tomorrow.month,
    tomorrow.day,
    tomorrow.weekday(),
    tomorrow.timetuple().tm_yday,
    1 if tomorrow.weekday() >= 5 else 0,
    tomorrow.isocalendar()[1],
    daily_sales['revenue'].tail(7).mean(),
    daily_sales['revenue'].tail(30).mean()
]]

prediction = model.predict(test_features)[0]
print(f"✅ Predicted revenue for {tomorrow}: ₱{prediction:,.2f}")

print("\n✨ Model training complete!")
