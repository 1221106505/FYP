
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
    die(json_encode(['success' => false, 'error' => 'Connection failed']));
}

try {
    // 获取所有订单（修复了SQL注入风险）
    $sql = "SELECT 
                o.*,
                CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                c.email,
                c.phone
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
        
        // 获取订单项（使用预处理语句防止SQL注入）
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
                $item['item_total'] = $item['quantity'] * $item['unit_price'];
                $calculated_total += $item['item_total'];
                $total_quantity += $item['quantity'];
                
                $items[] = $item;
            }
            $items_stmt->close();
            
            // 计算折扣（如果有）
            $discount = 0;
            if ($calculated_total > 0 && $order['total_amount'] > 0) {
                $discount = $calculated_total - $order['total_amount'];
                $discount_percentage = $calculated_total > 0 ? ($discount / $calculated_total) * 100 : 0;
            }
        } else {
            $items = [];
            $total_quantity = 0;
            $calculated_total = 0;
            $discount = 0;
        }
        
        // 获取支付信息
        $payment_sql = "SELECT 
                        payment_method,
                        payment_status,
                        amount,
                        transaction_id,
                        payment_date
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
        
        // 格式化订单状态显示 - 修复：确保pending状态有正确的显示值
        $status_display = $order['status'];
        $status_class = '';
        
        // 转换状态为小写以进行统一比较
        $status_lower = strtolower($order['status']);
        
        switch($status_lower) {
            case 'pending':
                $status_class = 'pending';
                $status_display = 'Pending';
                break;
            case 'confirmed':
                $status_class = 'confirmed';
                $status_display = 'Confirmed';
                break;
            case 'shipped':
                $status_class = 'shipped';
                $status_display = 'Shipped';
                break;
            case 'delivered':
                $status_class = 'delivered';
                $status_display = 'Delivered';
                break;
            case 'cancelled':
                $status_class = 'cancelled';
                $status_display = 'Cancelled';
                break;
            default:
                $status_class = 'pending'; // 默认为pending类
                $status_display = ucfirst($order['status']) ?: 'Pending';
        }
        
        // 添加到订单数据
        $order['items'] = $items;
        $order['item_count'] = count($items);
        $order['total_quantity'] = $total_quantity;
        $order['calculated_total'] = number_format($calculated_total, 2);
        $order['discount'] = number_format($discount, 2);
        $order['payment_info'] = $payment_info;
        
        // 添加状态显示信息
        $order['status_display'] = $status_display;
        $order['status_class'] = $status_class;
        
        // 格式化日期
        $order['order_date_formatted'] = date('Y-m-d H:i', strtotime($order['order_date']));
        $order['updated_at_formatted'] = date('Y-m-d H:i', strtotime($order['updated_at']));
        
        // 添加支付信息到订单
        if ($payment_info) {
            $order['payment_method'] = $payment_info['payment_method'];
            $order['payment_status'] = $payment_info['payment_status'];
            $order['payment_date'] = $payment_info['payment_date'];
            $order['transaction_id'] = $payment_info['transaction_id'];
        }
        
        $orders[] = $order;
    }
    
    // 获取订单状态统计
    $status_stats_sql = "SELECT 
                            status,
                            COUNT(*) as count,
                            SUM(total_amount) as total_amount
                         FROM orders 
                         GROUP BY status 
                         ORDER BY FIELD(status, 'pending', 'confirmed', 'shipped', 'delivered', 'cancelled')";
    
    $status_result = $conn->query($status_stats_sql);
    $status_stats = [];
    
    if ($status_result) {
        while ($row = $status_result->fetch_assoc()) {
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
            'total_amount' => array_sum(array_column($orders, 'total_amount')),
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
?>