<?php
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
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $conn->connect_error]));
}

// 设置字符集
$conn->set_charset("utf8mb4");

try {
    // 获取所有订单 - 修正：customer表没有recipient_name字段
    $sql = "SELECT 
                o.*,
                c.username as customer_username,
                c.email,
                c.phone,
                CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_full_name
            FROM orders o
            LEFT JOIN customer c ON o.customer_id = c.auto_id
            ORDER BY o.order_date DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Query failed: ' . $conn->error);
    }
    
    $orders = [];
    
    while ($order = $result->fetch_assoc()) {
        $order_id = $order['order_id'];
        
        // 获取订单项
        $items_sql = "SELECT 
                        oi.*,
                        b.title,
                        b.author,
                        b.cover_image,
                        b.price as original_price,
                        b.category_id,
                        cat.category_name
                      FROM order_items oi
                      LEFT JOIN books b ON oi.book_id = b.book_id
                      LEFT JOIN categories cat ON b.category_id = cat.category_id
                      WHERE oi.order_id = ?";
        
        $items_stmt = $conn->prepare($items_sql);
        if ($items_stmt) {
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            $items = [];
            $total_quantity = 0;
            $calculated_total = 0;
            
            while ($item = $items_result->fetch_assoc()) {
                // 计算每个商品的总价
                $item_total = $item['quantity'] * $item['unit_price'];
                $item['item_total'] = $item_total;
                $item['item_total_formatted'] = number_format($item_total, 2);
                $calculated_total += $item_total;
                $total_quantity += $item['quantity'];
                
                $items[] = $item;
            }
            $items_stmt->close();
            
            // 计算折扣（如果有）
            $discount = 0;
            $discount_percentage = 0;
            if ($calculated_total > 0 && $order['total_amount'] > 0) {
                $discount = $calculated_total - floatval($order['total_amount']);
                if ($calculated_total > 0) {
                    $discount_percentage = ($discount / $calculated_total) * 100;
                }
            }
        } else {
            $items = [];
            $total_quantity = 0;
            $calculated_total = 0;
            $discount = 0;
            $discount_percentage = 0;
        }
        
        // 获取支付信息
        $payment_sql = "SELECT 
                        payment_id,
                        payment_method,
                        payment_status,
                        amount,
                        transaction_id,
                        payment_date,
                        created_at,
                        updated_at
                      FROM payments 
                      WHERE order_id = ? 
                      ORDER BY payment_date DESC 
                      LIMIT 1";
        
        $payment_stmt = $conn->prepare($payment_sql);
        $payment_info = null;
        if ($payment_stmt) {
            $payment_stmt->bind_param("i", $order_id);
            $payment_stmt->execute();
            $payment_result = $payment_stmt->get_result();
            
            if ($payment_result && $payment_result->num_rows > 0) {
                $payment_info = $payment_result->fetch_assoc();
            }
            $payment_stmt->close();
        }
        
        // 格式化订单状态显示
        $status_display = $order['status'] ?? 'confirmed';
        $status_class = getStatusClass($status_display);
        $status_display = formatOrderStatus($status_display);
        
        // 如果没有订单recipient_name，使用客户名称
        if (empty($order['recipient_name'])) {
            $order['recipient_name'] = $order['customer_full_name'] ?: $order['customer_username'] ?: 'Unknown Customer';
        }
        
        // 添加到订单数据
        $order['items'] = $items;
        $order['item_count'] = count($items);
        $order['total_quantity'] = $total_quantity;
        $order['calculated_total'] = number_format($calculated_total, 2);
        $order['discount'] = number_format($discount, 2);
        $order['discount_percentage'] = number_format($discount_percentage, 2);
        $order['payment_info'] = $payment_info;
        
        // 添加状态显示信息
        $order['status_display'] = $status_display;
        $order['status_class'] = $status_class;
        
        // 格式化日期
        if (!empty($order['order_date'])) {
            $order['order_date_formatted'] = date('Y-m-d H:i', strtotime($order['order_date']));
        }
        if (!empty($order['updated_at'])) {
            $order['updated_at_formatted'] = date('Y-m-d H:i', strtotime($order['updated_at']));
        }
        if (!empty($order['payment_date'])) {
            $order['payment_date_formatted'] = date('Y-m-d H:i', strtotime($order['payment_date']));
        }
        
        // 添加支付信息到订单
        if ($payment_info) {
            $order['payment_method'] = $payment_info['payment_method'];
            $order['payment_status'] = $payment_info['payment_status'];
            $order['payment_date'] = $payment_info['payment_date'];
            $order['transaction_id'] = $payment_info['transaction_id'];
        }
        
        // 格式化金额
        $order['total_amount_formatted'] = number_format(floatval($order['total_amount']), 2);
        
        $orders[] = $order;
    }
    
    // 获取订单状态统计
    $status_stats_sql = "SELECT 
                            status,
                            COUNT(*) as count,
                            SUM(total_amount) as total_amount
                         FROM orders 
                         GROUP BY status 
                         ORDER BY FIELD(status, 'confirmed', 'shipped', 'delivered', 'cancelled')";
    
    $status_result = $conn->query($status_stats_sql);
    $status_stats = [];
    
    if ($status_result) {
        while ($row = $status_result->fetch_assoc()) {
            $row['status_display'] = formatOrderStatus($row['status']);
            $row['total_amount_formatted'] = number_format($row['total_amount'], 2);
            $status_stats[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'count' => count($orders),
        'status_stats' => $status_stats,
        'summary' => [
            'total_orders' => count($orders),
            'total_amount' => number_format(array_sum(array_column($orders, 'total_amount')), 2),
            'status_distribution' => $status_stats
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

$conn->close();

// 辅助函数：格式化订单状态
function formatOrderStatus($status) {
    if (empty($status)) return 'Confirmed';
    
    $status_map = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled'
    ];
    
    $status_lower = strtolower($status);
    return $status_map[$status_lower] ?? ucfirst($status);
}

// 辅助函数：获取状态类名
function getStatusClass($status) {
    $status = strtolower($status);
    $class_map = [
        'pending' => 'status-pending',
        'confirmed' => 'status-confirmed',
        'processing' => 'status-processing',
        'shipped' => 'status-shipped',
        'delivered' => 'status-delivered',
        'cancelled' => 'status-cancelled'
    ];
    
    return $class_map[$status] ?? 'status-confirmed';
}
?>