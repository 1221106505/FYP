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

try {
    // 获取所有订单的基本信息
    $sql = "SELECT * FROM orders ORDER BY order_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    
    if ($result && $result->num_rows > 0) {
        while ($order = $result->fetch_assoc()) {
            $order_id = $order['order_id'];
            
            // 获取订单项详情
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
            $total_quantity = 0;
            
            while ($item = $items_result->fetch_assoc()) {
                $items[] = $item;
                $total_quantity += $item['quantity'];
            }
            $items_stmt->close();
            
            // 计算订单统计信息
            $order['items'] = $items;
            $order['item_count'] = count($items);
            $order['total_quantity'] = $total_quantity;
            
            // 添加客户信息（从orders表中获取）
            $order['customer_name'] = 'Customer ' . $order['customer_id'];
            $order['contact_email'] = $order['contact_email'] ?? '';
            $order['contact_phone'] = $order['contact_phone'] ?? '';
            
            $orders[] = $order;
        }
        
        echo json_encode([
            'success' => true,
            'orders' => $orders,
            'count' => count($orders)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No orders found',
            'orders' => []
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