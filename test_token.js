// Test token verification with Firebase Admin SDK
import admin from './config/firebase-admin.js';

const testToken = process.argv[2];

if (!testToken) {
    console.log('Usage: node test_token.js <firebase-id-token>');
    process.exit(1);
}

console.log('Testing token verification...\n');
console.log('Token preview:', testToken.substring(0, 50) + '...');
console.log('Token length:', testToken.length);
console.log('\nVerifying with Firebase Admin SDK...\n');

admin.auth().verifyIdToken(testToken)
    .then(decodedToken => {
        console.log('✓ Token is VALID!\n');
        console.log('User ID:', decodedToken.uid);
        console.log('Email:', decodedToken.email);
        console.log('Email Verified:', decodedToken.email_verified);
        console.log('Issued At:', new Date(decodedToken.iat * 1000).toISOString());
        console.log('Expires At:', new Date(decodedToken.exp * 1000).toISOString());
        console.log('\nFull decoded token:');
        console.log(JSON.stringify(decodedToken, null, 2));
        process.exit(0);
    })
    .catch(error => {
        console.log('✗ Token is INVALID!\n');
        console.log('Error Code:', error.code);
        console.log('Error Message:', error.message);
        console.log('\nFull error:');
        console.log(JSON.stringify(error, null, 2));
        process.exit(1);
    });
