<?php
session_start();

echo "<h2>Session Debug Info</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n\n";
echo "Session Contents:\n";
print_r($_SESSION);
echo "</pre>";

echo "<hr>";
echo "<h3>Quick Actions:</h3>";
echo "<a href='Welcome.php'>Go to Welcome</a> | ";
echo "<a href='refresh_token.php'>Refresh Token</a> | ";
echo "<a href='Cart.php'>Go to Cart</a> | ";
echo "<a href='test_cart_debug.php'>Debug Cart</a>";
?>
