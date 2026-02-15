<!DOCTYPE html>
<html>
<head>
    <title>Refresh Auth Token</title>
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-auth-compat.js"></script>
</head>
<body>
    <h2>Refresh Authentication Token</h2>
    <div id="status">Checking authentication...</div>
    
    <script>
        const firebaseConfig = {
            apiKey: "AIzaSyCJPY70uT6qNqs2J2GW3zWAAKeQ_rQ1tUk",
            authDomain: "anf-chocolate.firebaseapp.com",
            projectId: "anf-chocolate",
            storageBucket: "anf-chocolate.firebasestorage.app",
            messagingSenderId: "899676195175",
            appId: "1:899676195175:web:0c38236d38cb4103cc47c2",
            measurementId: "G-FGSY080FNM"
        };

        firebase.initializeApp(firebaseConfig);
        const auth = firebase.auth();

        auth.onAuthStateChanged(async (user) => {
            const statusDiv = document.getElementById('status');
            
            if (user) {
                statusDiv.innerHTML = 'User authenticated: ' + user.email + '<br>';
                statusDiv.innerHTML += 'Getting fresh token...<br>';
                
                try {
                    const token = await user.getIdToken(true); // Force refresh
                    statusDiv.innerHTML += 'Token refreshed!<br>';
                    
                    // Send new token to PHP session
                    const formData = new FormData();
                    formData.append('idToken', token);
                    formData.append('email', user.email);
                    formData.append('uid', user.uid); // Include uid
                    
                    const response = await fetch('login_popup.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        statusDiv.innerHTML += '<span style="color: green;">✓ Session updated successfully!</span><br>';
                        statusDiv.innerHTML += '<span style="color: blue;">Debug: User ID: ' + user.uid + '</span><br>';
                        statusDiv.innerHTML += '<span style="color: blue;">Debug: Email: ' + user.email + '</span><br>';
                        statusDiv.innerHTML += '<br><strong>You can now access:</strong><br>';
                        statusDiv.innerHTML += '<a href="MainPage.php" style="background:blue;color:white;padding:10px;margin:5px;display:inline-block;text-decoration:none;">Main Page</a><br>';
                        statusDiv.innerHTML += '<a href="Cart.php" style="background:green;color:white;padding:10px;margin:5px;display:inline-block;text-decoration:none;">Go to Cart</a><br>';
                        statusDiv.innerHTML += '<a href="test_cart_debug.php" style="background:orange;color:white;padding:10px;margin:5px;display:inline-block;text-decoration:none;">Test Debug Page</a><br>';
                        statusDiv.innerHTML += '<a href="check_session.php" style="background:purple;color:white;padding:10px;margin:5px;display:inline-block;text-decoration:none;">Check Session</a>';
                    } else {
                        statusDiv.innerHTML += '<span style="color: red;">Error updating session: ' + data.message + '</span>';
                    }
                } catch (error) {
                    statusDiv.innerHTML += '<span style="color: red;">Error: ' + error.message + '</span>';
                }
            } else {
                statusDiv.innerHTML = '<span style="color: orange;">No user logged in. Please <a href="Welcome.php">login</a> first.</span>';
            }
        });
    </script>
</body>
</html>
