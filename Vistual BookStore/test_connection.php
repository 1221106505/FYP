<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$host = 'localhost';
$username = 'root'; // 根据您的数据库配置修改
$password = ''; // 根据您的数据库配置修改
$database = 'Bookstore'; // 根据您的数据库名称修改

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => 'Connection failed: ' . $conn->connect_error
    ]);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => 'Database connection successful'
]);

$conn->close();
?>