<?php
// /cart/get_preorders.php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please login first']);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // 1. 获取购物车中的预购项目
    $cart_preorders = [];
    $cart_stmt = $conn->prepare("
        SELECT 
            c.cart_id,
            c.customer_id,
            c.book_id,
            c.quantity,
            c.added_date,
            c.is_pre_order,
            c.pre_order_status,
            c.expected_date,
            b.title,
            b.author,
            b.price,
            b.pre_order_price,
            b.stock_quantity,
            b.pre_order_available,
            cat.category_name
        FROM cart c
        JOIN books b ON c.book_id = b.book_id
        LEFT JOIN categories cat ON b.category_id = cat.category_id
        WHERE c.customer_id = ? AND c.is_pre_order = 1
        ORDER BY c.added_date DESC
    ");
    
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();
    
    while ($row = $cart_result->fetch_assoc()) {
        $cart_preorders[] = $row;
    }
    $cart_stmt->close();
    
    // 2. 获取独立的预购订单
    $pre_orders = [];
    $preorders_stmt = $conn->prepare("
        SELECT 
            po.pre_order_id,
            po.customer_id,
            po.book_id,
            po.quantity,
            po.order_date,
            po.expected_delivery_date,
            po.status,
            po.total_amount,
            b.title,
            b.author,
            b.price,
            b.pre_order_price
        FROM pre_orders po
        LEFT JOIN books b ON po.book_id = b.book_id
        WHERE po.customer_id = ? 
        ORDER BY po.order_date DESC
    ");
    
    $preorders_stmt->bind_param("i", $user_id);
    $preorders_stmt->execute();
    $preorders_result = $preorders_stmt->get_result();
    
    while ($row = $preorders_result->fetch_assoc()) {
        $pre_orders[] = $row;
    }
    $preorders_stmt->close();
    
    // 3. 获取预购历史（已完成或取消的预购）
    $pre_order_history = [];
    
    // 先获取购物车中的预购历史
    $cart_history_stmt = $conn->prepare("
        SELECT 
            'cart' as source,
            c.cart_id as id,
            c.book_id,
            c.quantity,
            c.added_date as order_date,
            c.pre_order_status as status,
            (c.quantity * b.price) as total_amount,
            b.title,
            b.price,
            b.author
        FROM cart c
        JOIN books b ON c.book_id = b.book_id
        WHERE c.customer_id = ? 
            AND c.is_pre_order = 1 
            AND c.pre_order_status IN ('cancelled')
    ");
    
    $cart_history_stmt->bind_param("i", $user_id);
    $cart_history_stmt->execute();
    $cart_history_result = $cart_history_stmt->get_result();
    
    while ($row = $cart_history_result->fetch_assoc()) {
        $pre_order_history[] = $row;
    }
    $cart_history_stmt->close();
    
    // 获取独立预购订单的历史
    $preorder_history_stmt = $conn->prepare("
        SELECT 
            'pre_order' as source,
            po.pre_order_id as id,
            po.book_id,
            po.quantity,
            po.order_date,
            po.status,
            po.total_amount,
            b.title,
            b.price,
            b.author
        FROM pre_orders po
        LEFT JOIN books b ON po.book_id = b.book_id
        WHERE po.customer_id = ? 
            AND po.status IN ('cancelled', 'delivered')
        ORDER BY po.order_date DESC
        LIMIT 10
    ");
    
    $preorder_history_stmt->bind_param("i", $user_id);
    $preorder_history_stmt->execute();
    $preorder_history_result = $preorder_history_stmt->get_result();
    
    while ($row = $preorder_history_result->fetch_assoc()) {
        $pre_order_history[] = $row;
    }
    $preorder_history_stmt->close();
    
    // 4. 计算统计数据
    $stats = [
        'pending' => 0,
        'confirmed' => 0,
        'available' => 0,
        'cancelled' => 0,
        'total' => 0
    ];
    
    // 从购物车预购统计
    foreach ($cart_preorders as $item) {
        $status = $item['pre_order_status'];
        if (isset($stats[$status])) {
            $stats[$status]++;
        } else {
            $stats['pending']++;
        }
        $stats['total']++;
    }
    
    // 从独立预购订单统计
    foreach ($pre_orders as $order) {
        $status = $order['status'];
        if ($status === 'shipped' || $status === 'delivered') {
            $stats['confirmed']++;
        } elseif (isset($stats[$status])) {
            $stats[$status]++;
        } else {
            $stats['pending']++;
        }
        $stats['total']++;
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'cart_preorders' => $cart_preorders,
        'pre_orders' => $pre_orders,
        'pre_order_history' => $pre_order_history
    ]);
    
} catch (Exception $e) {
    error_log("Database error in get_preorders.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>