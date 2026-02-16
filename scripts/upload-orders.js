// Upload orders from CSV to Firestore
import admin from '../config/firebase-admin.js';
import { adminDb } from '../config/firebase-admin.js';
import fs from 'fs';
import { parse } from 'csv-parse/sync';

async function uploadOrders() {
  console.log('📦 Starting orders upload to Firestore...');
  console.log('=' .repeat(60));
  
  try {
    // Read CSV file
    const csvContent = fs.readFileSync('orders_dataset.csv', 'utf-8');
    const records = parse(csvContent, {
      columns: true,
      skip_empty_lines: true
    });
    
    console.log(`✅ Loaded ${records.length} orders from CSV`);
    
    // Prepare batch writes (Firestore allows max 500 per batch)
    const batchSize = 500;
    let uploaded = 0;
    let skipped = 0;
    
    for (let i = 0; i < records.length; i += batchSize) {
      const batch = adminDb.batch();
      const chunk = records.slice(i, i + batchSize);
      
      for (const record of chunk) {
        try {
          // Parse date
          const createdAt = new Date(record.createdAt);
          
          // Determine timestamp fields based on status
          const timestamps = {
            createdAt: admin.firestore.Timestamp.fromDate(createdAt)
          };
          
          // Add timestamps based on order status
          if (record.orderStatus === 'DELIVERED' && record.paymentStatus === 'PAID') {
            timestamps.paidAt = admin.firestore.Timestamp.fromDate(
              new Date(createdAt.getTime() + 5 * 60000) // 5 mins after creation
            );
            timestamps.approvedAt = admin.firestore.Timestamp.fromDate(
              new Date(createdAt.getTime() + 2 * 3600000) // 2 hours after
            );
            timestamps.shippedAt = admin.firestore.Timestamp.fromDate(
              new Date(createdAt.getTime() + 24 * 3600000) // 1 day after
            );
            timestamps.deliveredAt = admin.firestore.Timestamp.fromDate(
              new Date(createdAt.getTime() + 72 * 3600000) // 3 days after
            );
          } else if (record.paymentStatus === 'PAID') {
            timestamps.paidAt = admin.firestore.Timestamp.fromDate(
              new Date(createdAt.getTime() + 5 * 60000)
            );
          }
          
          // Create order document
          const orderData = {
            userId: record.userId || '4Qw6l0ZqRLcjg0eDUg62NhtIAAk1',
            userName: record.userEmail.split('@')[0] || 'Customer',
            userEmail: record.userEmail,
            items: [{
              productId: record.productId,
              productName: record.productName,
              productPrice: parseFloat(record.productPrice),
              quantity: parseInt(record.quantity),
              productImageUrl: ''
            }],
            deliveryAddress: record.deliveryAddress,
            phoneNumber: '+639171234567', // Default phone
            subtotal: parseFloat(record.subtotal),
            deliveryFee: parseFloat(record.deliveryFee),
            totalAmount: parseFloat(record.totalAmount),
            orderStatus: record.orderStatus || 'PENDING',
            paymentStatus: record.paymentStatus || 'PENDING',
            notes: '',
            ...timestamps
          };
          
          // Use the orderId from CSV as document ID
          const docRef = adminDb.collection('orders').doc(record.orderId);
          batch.set(docRef, orderData);
          uploaded++;
          
        } catch (error) {
          console.error(`❌ Error processing order ${record.orderId}:`, error.message);
          skipped++;
        }
      }
      
      // Commit batch
      await batch.commit();
      console.log(`📝 Uploaded batch ${Math.floor(i / batchSize) + 1}: ${chunk.length} orders`);
    }
    
    console.log('\n' + '='.repeat(60));
    console.log(`✅ Upload complete!`);
    console.log(`   Total uploaded: ${uploaded}`);
    console.log(`   Skipped: ${skipped}`);
    console.log('='.repeat(60));
    
  } catch (error) {
    console.error('❌ Upload failed:', error);
    process.exit(1);
  }
  
  process.exit(0);
}

// Run the upload
uploadOrders();
