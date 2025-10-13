<?php
echo "<h1>Testing Database Connection Fix</h1>";

try {
    require_once __DIR__ . '/../include/database.php';
    echo "<div style='color: green;'>✓ Database connection successful!</div>";
    
    // 测试查询
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin");
    $result = $stmt->fetch();
    echo "<div>Admin users: " . $result['count'] . "</div>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customer");
    $result = $stmt->fetch();
    echo "<div>Customer users: " . $result['count'] . "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>✗ Error: " . $e->getMessage() . "</div>";
}

echo "<h2>Test Forms:</h2>";
echo '
<form action="Register.php" method="POST" style="margin-bottom: 20px; padding: 20px; border: 1px solid #ccc;">
    <h3>Test Registration</h3>
    <input type="text" name="username" placeholder="Username" required style="display: block; margin: 10px 0; padding: 5px;">
    <input type="password" name="password" placeholder="Password" required style="display: block; margin: 10px 0; padding: 5px;">
    <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none;">Test Register</button>
</form>

<form action="Login.php" method="POST" style="padding: 20px; border: 1px solid #ccc;">
    <h3>Test Login</h3>
    <input type="text" name="username" placeholder="Username" required style="display: block; margin: 10px 0; padding: 5px;">
    <input type="password" name="password" placeholder="Password" required style="display: block; margin: 10px 0; padding: 5px;">
    <button type="submit" style="padding: 10px 20px; background: #28a745; color: white; border: none;">Test Login</button>
</form>
';
?>