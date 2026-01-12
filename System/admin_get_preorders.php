<?php
// admin_get_preorders.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 数据库配置
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'bookstore';

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
    // 移除 customer_id 参数检查，管理员可以查看所有预订单
    
    // 获取所有预购订单（管理员可以查看所有客户的）
    $sql = "SELECT 
                po.pre_order_id,
                po.customer_id,
                po.book_id,
                po.quantity,
                po.order_date,
                po.expected_delivery_date,
                po.status,
                po.total_amount,
                po.shipping_address,
                po.billing_address,
                po.contact_phone,
                po.contact_email,
                po.notes,
                po.updated_at,
                b.title as book_title,
                b.author as book_author,
                b.price as book_price,
                c.username as customer_name,
                c.email as customer_email,
                c.phone as customer_phone
            FROM pre_orders po
            LEFT JOIN books b ON po.book_id = b.book_id
            LEFT JOIN customer c ON po.customer_id = c.auto_id
            ORDER BY po.order_date DESC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        $preorders = [];
        while ($row = $result->fetch_assoc()) {
            // 格式化数据
            $row['order_date_formatted'] = date('Y-m-d H:i:s', strtotime($row['order_date']));
            if ($row['expected_delivery_date']) {
                $row['expected_delivery_date_formatted'] = date('Y-m-d', strtotime($row['expected_delivery_date']));
            }
            if ($row['updated_at']) {
                $row['updated_at_formatted'] = date('Y-m-d H:i:s', strtotime($row['updated_at']));
            }
            $preorders[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'preorders' => $preorders,
            'total' => count($preorders)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Database query failed: ' . $conn->error
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>