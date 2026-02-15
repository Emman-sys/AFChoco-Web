<?php
/**
 * Admin Dashboard - Redirect to Legacy Version with Firestore
 * 
 * The legacy AdminDashboard has been updated to use Firestore instead of MySQL.
 * It maintains the original UI/UX while connecting to Firebase backend.
 */

// Redirect to the legacy dashboard (now with Firestore integration)
header('Location: legacy-php/AdminDashboard.php');
exit;
