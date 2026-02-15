// Data Migration Script - MySQL to Firestore
// This script migrates data to match the exact schema used by AFMobile and AFAdmin mobile apps
// Run this script to migrate your existing MySQL data to Firebase Firestore

import mysql from 'mysql2/promise';
import { adminDb } from './firebase-admin.js';
import dotenv from 'dotenv';

dotenv.config();

// MySQL connection config
const mysqlConfig = {
  host: 'localhost',
  user: 'root',
  password: '',
  database: 'a&f chocolate'
};

// Map old category IDs to mobile app category names
const categoryMap = {
  1: 'DARK',     // Dark Chocolate
  2: 'WHITE',    // White Chocolate
  3: 'MILK',     // Milk Chocolate
  4: 'MIXED',    // Mixed Chocolate
  5: 'SPECIALTY', // Specialty
  6: 'SPECIALTY', // Other specialty items
  7: 'SPECIALTY',
  8: 'SPECIALTY'
};

async function migrateData() {
  let connection;
  
  try {
    console.log('🔄 Starting data migration from MySQL to Firestore...');
    console.log('📱 Using mobile app schema (AFMobile + AFAdmin compatible)\n');
    
    // Connect to MySQL
    connection = await mysql.createConnection(mysqlConfig);
    console.log('✅ Connected to MySQL');
    
    // Migrate Products
    console.log('\n📦 Migrating products...');
    const [products] = await connection.execute('SELECT * FROM products');
    
    for (const product of products) {
      const productData = {
        name: product.name,
        description: product.description || '',
        price: parseFloat(product.price),
        category: categoryMap[product.category_id] || 'SPECIALTY', // Map to mobile app categories
        sku: product.sku || `CHO-${Date.now()}-${Math.random().toString(36).substr(2, 6).toUpperCase()}`,
        stockLevel: parseInt(product.stock) || 0,
        imageUrl: product.image_url || product.image || '',
        salesCount: parseInt(product.sales_count) || 0,
        createdAt: adminDb.Timestamp.now(),
        updatedAt: adminDb.Timestamp.now()
      };
      
      await adminDb.collection('products').add(productData);
    }
    console.log(`✅ Migrated ${products.length} products`);
    console.log('   Categories used: WHITE, DARK, MILK, MIXED, SPECIALTY');
    
    // Migrate Users
    console.log('\n👥 Migrating users...');
    console.log('⚠️  NOTE: User passwords cannot be migrated to Firebase Auth.');
    console.log('   Users will need to re-register or use password reset.');
    
    const [users] = await connection.execute('SELECT * FROM users');
    
    for (const user of users) {
      const userData = {
        uid: user.user_id?.toString() || '', // Temporary UID, will be replaced on re-registration
        email: user.email,
        username: user.username || user.first_name || user.email?.split('@')[0] || 'User',
        phoneNumber: user.phone || user.phone_number || '',
        address: user.address || '',
        profilePicture: user.profile_picture || '',
        role: user.role || 'user',
        createdAt: adminDb.Timestamp.now(),
        updatedAt: adminDb.Timestamp.now(),
        migrated: true,
        needsReregistration: true
      };
      
      // Use a placeholder document ID (users will create new accounts)
      await adminDb.collection('users').doc(`migrated_${user.user_id}`).set(userData);
    }
    console.log(`✅ Migrated ${users.length} user profiles (placeholder accounts)`);
    
    // Migrate Orders (if table exists)
    try {
      console.log('\n📋 Migrating orders...');
      const [orders] = await connection.execute('SELECT * FROM orders');
      
      for (const order of orders) {
        // Parse items if stored as JSON
        let items = [];
        try {
          items = typeof order.items === 'string' ? JSON.parse(order.items) : order.items || [];
        } catch (e) {
          console.log(`   ⚠️  Could not parse items for order ${order.order_id}`);
        }
        
        // Transform items to match mobile app schema
        const transformedItems = items.map(item => ({
          productId: item.productId || item.product_id || '',
          productName: item.productName || item.name || '',
          productImageUrl: item.productImageUrl || item.image_url || '',
          productPrice: parseFloat(item.productPrice || item.price || 0),
          quantity: parseInt(item.quantity || 1)
        }));
        
        const orderData = {
          userId: order.user_id?.toString() || '',
          userName: order.user_name || order.customer_name || 'Unknown',
          userEmail: order.user_email || order.email || '',
          items: transformedItems,
          deliveryAddress: order.delivery_address || order.shipping_address || order.address || '',
          phoneNumber: order.phone_number || order.phone || '',
          subtotal: parseFloat(order.subtotal || order.total || 0),
          deliveryFee: parseFloat(order.delivery_fee || 50),
          totalAmount: parseFloat(order.total_amount || order.total || 0),
          orderStatus: (order.order_status || order.status || 'PENDING').toUpperCase(),
          paymentStatus: (order.payment_status || 'PENDING').toUpperCase(),
          notes: order.notes || '',
          createdAt: order.created_at ? adminDb.Timestamp.fromDate(new Date(order.created_at)) : adminDb.Timestamp.now(),
          paidAt: order.paid_at ? adminDb.Timestamp.fromDate(new Date(order.paid_at)) : null,
          approvedAt: order.approved_at ? adminDb.Timestamp.fromDate(new Date(order.approved_at)) : null,
          shippedAt: order.shipped_at ? adminDb.Timestamp.fromDate(new Date(order.shipped_at)) : null,
          deliveredAt: order.delivered_at ? adminDb.Timestamp.fromDate(new Date(order.delivered_at)) : null
        };
        
        await adminDb.collection('orders').add(orderData);
      }
      console.log(`✅ Migrated ${orders.length} orders`);
    } catch (err) {
      console.log('ℹ️  Orders table not found or empty, skipping...');
    }
    
    // NOTE: Cart items are not migrated as they are session-based
    console.log('\nℹ️  Cart items not migrated (users will create new carts)');
    
    // Migrate Comments (if table exists)
    try {
      console.log('\n💬 Migrating comments...');
      const [comments] = await connection.execute('SELECT * FROM comments');
      
      for (const comment of comments) {
        const commentData = {
          productId: comment.product_id?.toString() || '',
          userId: comment.user_id?.toString() || '',
          userName: comment.user_name || comment.username || 'Anonymous',
          text: comment.text || comment.comment || '',
          rating: comment.rating ? parseInt(comment.rating) : null,
          createdAt: comment.created_at ? adminDb.Timestamp.fromDate(new Date(comment.created_at)) : adminDb.Timestamp.now(),
          updatedAt: comment.updated_at ? adminDb.Timestamp.fromDate(new Date(comment.updated_at)) : adminDb.Timestamp.now()
        };
        
        await adminDb.collection('comments').add(commentData);
      }
      console.log(`✅ Migrated ${comments.length} comments`);
    } catch (err) {
      console.log('ℹ️  Comments table not found or empty, skipping...');
    }
    
    console.log('\n✨ Migration completed successfully!');
    console.log('\n📱 DATABASE SCHEMA ALIGNED WITH MOBILE APPS');
    console.log('\n⚠️  IMPORTANT NEXT STEPS:');
    console.log('   1. 🔐 Users need to re-register through Firebase Auth');
    console.log('   2. 📱 Deploy Firestore security rules: firebase deploy --only firestore:rules');
    console.log('   3. 📊 Deploy Firestore indexes: firebase deploy --only firestore:indexes');
    console.log('   4. 🧪 Test web app with mobile apps to ensure data compatibility');
    console.log('   5. 💾 Keep MySQL backup until fully verified');
    console.log('\n🎯 Categories available: WHITE, DARK, MILK, MIXED, SPECIALTY');
    console.log('🎯 Order statuses: PENDING, PAID, APPROVED, PROCESSING, SHIPPED, DELIVERED, CANCELLED\n');
    
  } catch (error) {
    console.error('❌ Migration error:', error);
    throw error;
  } finally {
    if (connection) {
      await connection.end();
      console.log('✅ MySQL connection closed');
    }
  }
}

// Run migration
migrateData()
  .then(() => process.exit(0))
  .catch(error => {
    console.error('Migration failed:', error);
    process.exit(1);
  });
