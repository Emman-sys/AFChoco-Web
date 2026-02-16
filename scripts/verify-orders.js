// Verify orders in Firestore
import { adminDb } from '../config/firebase-admin.js';

async function verifyOrders() {
  try {
    const snapshot = await adminDb.collection('orders').limit(5).get();
    
    console.log(`\n📊 Total orders in Firestore: ${snapshot.size > 0 ? 'Found orders!' : 'No orders'}`);
    
    // Get total count
    const allOrders = await adminDb.collection('orders').count().get();
    console.log(`   Total count: ${allOrders.data().count}`);
    
    console.log('\n📋 Sample orders:');
    console.log('='.repeat(80));
    
    snapshot.forEach(doc => {
      const data = doc.data();
      console.log(`\n🆔 Order ID: ${doc.id}`);
      console.log(`   Email: ${data.userEmail}`);
      console.log(`   Total: ₱${data.totalAmount}`);
      console.log(`   Status: ${data.orderStatus} / ${data.paymentStatus}`);
      console.log(`   Items: ${data.items.length} product(s)`);
      console.log(`   Created: ${data.createdAt.toDate().toISOString()}`);
    });
    
    console.log('\n' + '='.repeat(80));
    console.log('✅ Verification complete!\n');
    
  } catch (error) {
    console.error('❌ Error:', error);
  }
  
  process.exit(0);
}

verifyOrders();
