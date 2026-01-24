<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 数据库配置
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'Bookstore';

// 创建连接
$conn = new mysqli($host, $username, $password, $database);

// 检查连接
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => 'Connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// 获取 order_id
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid order ID'
    ]);
    exit();
}

try {
    // 获取订单基本信息
    $sql = "SELECT 
                o.*,
                o.recipient_name as recipient_name  -- 重命名为recipient_name
            FROM orders o 
            WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $order = $result->fetch_assoc();
        
        // 确保recipient_name存在
        $order['recipient_name'] = $order['recipient_name'] ?? $order['customer_name'] ?? 'Customer';
        
        // 获取订单项
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
        
        echo json_encode([
            'success' => true,
            'order' => $order
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Order not found'
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>