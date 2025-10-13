<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
</head>
<body>
    <h1>Session Debug Information</h1>
    
    <h2>Current Session Data:</h2>
    <pre><?php print_r($_SESSION); ?></pre>
    
    <h2>Session ID:</h2>
    <p><?php echo session_id(); ?></p>
    
    <h2>Cookie Data:</h2>
    <pre><?php print_r($_COOKIE); ?></pre>
    
    <h2>Test Links:</h2>
    <a href="check_login.php" target="_blank">Test check_login.php</a><br>
    <a href="Main.html">Go to Main Page</a><br>
    <a href="user_profile.html">Go to User Profile</a>
    
    <h2>Manual Session Test:</h2>
    <form method="post">
        <input type="text" name="test_username" placeholder="Username to test" value="<?php echo $_SESSION['username'] ?? ''; ?>">
        <button type="submit" name="set_session">Set Test Session</button>
        <button type="submit" name="clear_session">Clear Session</button>
    </form>
    
    <?php
    if (isset($_POST['set_session'])) {
        $_SESSION['username'] = $_POST['test_username'];
        $_SESSION['user_type'] = 'test';
        echo "<p>Session set for user: " . $_POST['test_username'] . "</p>";
        header("Refresh:0");
    }
    
    if (isset($_POST['clear_session'])) {
        session_destroy();
        echo "<p>Session cleared</p>";
        header("Refresh:0");
    }
    ?>
</body>
</html>