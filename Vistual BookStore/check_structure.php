<?php
echo "<h3>文件结构检查</h3>";

// 检查关键文件
$files = [
    'Main.html' => '主页面',
    'admin_panel.html' => '管理员面板',
    'logout.php' => '登出页面',
    'get_book.php' => '获取书籍API',
    'get_categories.php' => '获取分类API',
    '../include/database.php' => '数据库配置'
];

foreach ($files as $file => $description) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ $description - 存在</p>";
    } else {
        echo "<p style='color: red;'>❌ $description - 不存在</p>";
        
        // 尝试找到文件
        $found = false;
        $dirs = ['.', '..', '../..'];
        foreach ($dirs as $dir) {
            $path = $dir . '/' . $file;
            if (file_exists($path)) {
                echo "<p style='color: orange;'>📁 文件在: $path</p>";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "<p style='color: red;'>🔍 在所有位置都找不到文件</p>";
        }
    }
}

echo "<h3>当前目录: " . __DIR__ . "</h3>";
echo "<h3>服务器根目录: " . $_SERVER['DOCUMENT_ROOT'] . "</h3>";
?>