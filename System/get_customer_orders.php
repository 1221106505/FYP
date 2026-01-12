<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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

// 获取 customer_id
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

if ($customer_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid customer ID'
    ]);
    exit();
}

try {
    // 首先获取客户基本信息
    $customer_sql = "SELECT * FROM customer WHERE auto_id = ?";
    $customer_stmt = $conn->prepare($customer_sql);
    $customer_stmt->bind_param("i", $customer_id);
    $customer_stmt->execute();
    $customer_result = $customer_stmt->get_result();
    
    if ($customer_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Customer not found'
        ]);
        exit();
    }
    
    $customer = $customer_result->fetch_assoc();
    $customer_stmt->close();
    
    // 获取客户的所有订单
    $orders_sql = "SELECT * FROM orders WHERE customer_id = ? ORDER BY order_date DESC";
    $orders_stmt = $conn->prepare($orders_sql);
    $orders_stmt->bind_param("i", $customer_id);
    $orders_stmt->execute();
    $orders_result = $orders_stmt->get_result();
    
    $orders = [];
    
    if ($orders_result && $orders_result->num_rows > 0) {
        while ($order = $orders_result->fetch_assoc()) {
            $order_id = $order['order_id'];
            
            // 获取每个订单的订单项详情
            $items_sql = "SELECT 
                            oi.*,
                            b.title,
                            b.author,
                            b.cover_image
                          FROM order_items oi
                          LEFT JOIN books b ON oi.book_id = b.book_id
                          WHERE oi.order_id = ?";
            $items_stmt = $conn->prepare($items_sql);
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            $items = [];
            while ($item = $items_result->fetch_assoc()) {
                $items[] = $item;
            }
            $items_stmt->close();
            
            $order['items'] = $items;
            $orders[] = $order;
        }
    }
    
    $orders_stmt->close();
    
    echo json_encode([
        'success' => true,
        'customer' => [
            'customer_id' => $customer['auto_id'],
            'username' => $customer['username'],
            'email' => $customer['email'] ?? '',
            'first_name' => $customer['first_name'] ?? '',
            'last_name' => $customer['last_name'] ?? '',
            'phone' => $customer['phone'] ?? '',
            'address' => $customer['address'] ?? '',
            'city' => $customer['city'] ?? '',
            'state' => $customer['state'] ?? '',
            'zip_code' => $customer['zip_code'] ?? '',
            'country' => $customer['country'] ?? ''
        ],
        'orders' => $orders,
        'order_count' => count($orders)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>