<?php
echo "<h2>File Structure Diagnostic</h2>";

// 检查当前目录
echo "<h3>Current Directory: " . __DIR__ . "</h3>";

// 检查可能的数据库文件路径
$paths_to_check = [
    'include/database.php' => __DIR__ . '/include/database.php',
    'includes/database.php' => __DIR__ . '/includes/database.php',
    '../include/database.php' => __DIR__ . '/../include/database.php',
    '../includes/database.php' => __DIR__ . '/../includes/database.php',
];

foreach ($paths_to_check as $label => $path) {
    if (file_exists($path)) {
        echo "<div style='color: green; margin: 10px; padding: 10px; background: #f0fff0; border: 1px solid green;'>";
        echo "✓ FOUND: $label<br>";
        echo "Full path: $path";
        echo "</div>";
    } else {
        echo "<div style='color: red; margin: 10px; padding: 10px; background: #fff0f0; border: 1px solid red;'>";
        echo "✗ NOT FOUND: $label<br>";
        echo "Full path: $path";
        echo "</div>";
    }
}

// 列出当前目录内容
echo "<h3>Files in current directory:</h3>";
$files = scandir(__DIR__);
echo "<ul>";
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..') {
        $full_path = __DIR__ . '\\' . $file;
        if (is_dir($full_path)) {
            echo "<li><strong>[FOLDER]</strong> $file</li>";
        } else {
            echo "<li>[FILE] $file</li>";
        }
    }
}
echo "</ul>";

// 检查父目录
echo "<h3>Files in parent directory:</h3>";
$parent_dir = dirname(__DIR__);
$parent_files = scandir($parent_dir);
echo "<ul>";
foreach ($parent_files as $file) {
    if ($file !== '.' && $file !== '..') {
        $full_path = $parent_dir . '\\' . $file;
        if (is_dir($full_path)) {
            echo "<li><strong>[FOLDER]</strong> $file</li>";
        } else {
            echo "<li>[FILE] $file</li>";
        }
    }
}
echo "</ul>";

// 测试数据库连接
echo "<h3>Database Connection Test:</h3>";
try {
    // 尝试不同的路径
    $db_paths = [
        __DIR__ . '/../includes/database.php',
        __DIR__ . '/../include/database.php',
        __DIR__ . '/includes/database.php',
        __DIR__ . '/include/database.php'
    ];
    
    $db_connected = false;
    foreach ($db_paths as $db_path) {
        if (file_exists($db_path)) {
            require_once $db_path;
            echo "<div style='color: green;'>✓ Database file loaded: " . basename(dirname($db_path)) . "/database.php</div>";
            
            // 测试查询
            $stmt = $pdo->query("SELECT 1 as test");
            $result = $stmt->fetch();
            if ($result['test'] == 1) {
                echo "<div style='color: green;'>✓ Database connection successful!</div>";
                $db_connected = true;
            }
            break;
        }
    }
    
    if (!$db_connected) {
        echo "<div style='color: red;'>✗ Could not establish database connection</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>✗ Database error: " . $e->getMessage() . "</div>";
}
?>