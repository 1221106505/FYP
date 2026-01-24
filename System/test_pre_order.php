<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'bookstore';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");

// 获取所有表
$tables_result = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $tables_result->fetch_array()) {
    $tables[] = $row[0];
}

// 获取orders表结构
$orders_structure = [];
if (in_array('orders', $tables)) {
    $result = $conn->query("DESCRIBE orders");
    while ($row = $result->fetch_assoc()) {
        $orders_structure[] = $row;
    }
}

// 获取customer表结构
$customer_structure = [];
if (in_array('customer', $tables)) {
    $result = $conn->query("DESCRIBE customer");
    while ($row = $result->fetch_assoc()) {
        $customer_structure[] = $row;
    }
}

// 如果有customer表，获取示例数据
$customer_sample = [];
if (in_array('customer', $tables)) {
    $result = $conn->query("SELECT * FROM customer LIMIT 5");
    while ($row = $result->fetch_assoc()) {
        $customer_sample[] = $row;
    }
}

// 检查orders和customer的关联
$orders_sample = [];
if (in_array('orders', $tables)) {
    $result = $conn->query("
        SELECT DISTINCT customer_id, COUNT(*) as order_count 
        FROM orders 
        GROUP BY customer_id 
        LIMIT 10
    ");
    while ($row = $result->fetch_assoc()) {
        $orders_sample[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'database' => $database,
    'tables' => $tables,
    'orders_structure' => $orders_structure,
    'customer_structure' => $customer_structure,
    'customer_sample' => $customer_sample,
    'orders_customer_ids' => $orders_sample,
    'customer_exists' => in_array('customer', $tables),
    'orders_exists' => in_array('orders', $tables)
]);

$conn->close();
?>