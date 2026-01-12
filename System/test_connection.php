<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'Bookstore';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => 'Connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// 检查表是否存在
$tables = ['books', 'customers', 'orders', 'order_items'];
$table_info = [];

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    $table_info[$table] = $result->num_rows > 0 ? 'exists' : 'not exists';
    
    if ($result->num_rows > 0) {
        // 显示表结构
        $columns_result = $conn->query("DESCRIBE $table");
        $columns = [];
        while ($row = $columns_result->fetch_assoc()) {
            $columns[] = $row;
        }
        $table_info[$table . '_columns'] = $columns;
    }
}

echo json_encode([
    'success' => true,
    'database' => $database,
    'tables' => $table_info
]);

$conn->close();
?>