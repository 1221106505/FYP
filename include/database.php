<?php
// 数据库配置
$host = 'localhost';
$username = 'root';      // 默认是root
$password = '';          // 默认是空密码
$database = 'Bookstore'; // 您的数据库名

// 创建连接
$conn = new mysqli($host, $username, $password, $database);

// 检查连接
if ($conn->connect_error) {
    // 如果是开发环境，显示详细错误信息
    if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] == 'localhost') {
        die("Database connection failed: " . $conn->connect_error);
    } else {
        // 生产环境显示通用错误信息
        die("Database connection failed. Please try again later.");
    }
}

// 设置字符集
$conn->set_charset("utf8mb4");

// 可选：设置时区
date_default_timezone_set('Asia/Kuala_Lumpur');
?>