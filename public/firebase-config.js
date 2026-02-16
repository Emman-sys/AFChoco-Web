// Firebase Client Configuration
// This file is used on the frontend (browser)

import { initializeApp } from 'firebase/app';
import { getAuth } from 'firebase/auth';
import { getFirestore } from 'firebase/firestore';
import { getStorage } from 'firebase/storage';
import { getAnalytics } from 'firebase/analytics';

// Your web app's Firebase configuration
// Get these values from Firebase Console → Project Settings
const firebaseConfig = {
  apiKey: "AIzaSyCJPY70uT6qNqs2J2GW3zWAAKeQ_rQ1tUk",
  authDomain: "anf-chocolate.firebaseapp.com",
  projectId: "anf-chocolate",
  storageBucket: "anf-chocolate.firebasestorage.app",
  messagingSenderId: "899676195175",
  appId: "1:899676195175:web:0c38236d38cb4103cc47c2",
  measurementId: "G-FGSY080FNM"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);

// Initialize Firebase services
export const auth = getAuth(app);
export const db = getFirestore(app);
export const storage = getStorage(app);
export const analytics = typeof window !== 'undefined' ? getAnalytics(app) : null;

export default app;
