<?php
// 直接测试数据库连接
$host = 'localhost';
$dbname = 'BookStore';
$username = 'root';
$password = '';

echo "<h3>Database Connection Test (PDO)</h3>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ Database connected successfully!</p>";
    
    // 检查表
    $tables = ['books', 'categories', 'users'];
    foreach ($tables as $table) {
        $result = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Table '$table' exists</p>";
            
            // 显示表结构
            $structure = $pdo->query("DESCRIBE $table")->fetchAll();
            echo "<p>Table $table structure:</p>";
            echo "<ul>";
            foreach ($structure as $column) {
                echo "<li>{$column['Field']} - {$column['Type']}</li>";
            }
            echo "</ul>";
            
            // 显示数据数量
            $count = $pdo->query("SELECT COUNT(*) as count FROM $table")->fetch();
            echo "<p>Records in $table: {$count['count']}</p>";
            
        } else {
            echo "<p style='color: red;'>❌ Table '$table' does not exist</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Connection failed: " . $e->getMessage() . "</p>";
}
?>