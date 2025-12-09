<?php
// admin_get_single_preorder.php
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

// 获取 pre_order_id
$pre_order_id = isset($_GET['pre_order_id']) ? intval($_GET['pre_order_id']) : 0;

if ($pre_order_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid pre-order ID'
    ]);
    exit();
}

try {
    // 获取预购订单详细信息
    $sql = "SELECT 
                po.*,
                b.title as book_title,
                b.author as book_author,
                b.price as book_price,
                b.isbn as book_isbn,
                b.publisher as book_publisher,
                cat.category_name as book_category,
                c.username as customer_name,
                c.email as customer_email,
                c.phone as customer_phone,
                c.address as customer_address,
                c.city as customer_city,
                c.state as customer_state,
                c.country as customer_country
            FROM pre_orders po
            LEFT JOIN books b ON po.book_id = b.book_id
            LEFT JOIN categories cat ON b.category_id = cat.category_id
            LEFT JOIN customer c ON po.customer_id = c.auto_id
            WHERE po.pre_order_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $pre_order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $preorder = $result->fetch_assoc();
        
        // 格式化日期
        $preorder['order_date_formatted'] = date('Y-m-d H:i:s', strtotime($preorder['order_date']));
        if ($preorder['expected_delivery_date']) {
            $preorder['expected_delivery_date_formatted'] = date('Y-m-d', strtotime($preorder['expected_delivery_date']));
        }
        if ($preorder['updated_at']) {
            $preorder['updated_at_formatted'] = date('Y-m-d H:i:s', strtotime($preorder['updated_at']));
        }
        
        echo json_encode([
            'success' => true,
            'preorder' => $preorder
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Pre-order not found'
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