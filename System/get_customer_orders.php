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
    
    // 修复查询，默认所有支付都是完成的
    $orders_sql = "SELECT 
                    o.order_id,
                    o.customer_id,
                    o.recipient_name,
                    o.order_date,
                    o.total_amount,
                    o.status,
                    o.shipping_address,
                    o.billing_address,
                    o.contact_phone,
                    o.contact_email,
                    o.payment_method,
                    o.payment_date,
                    o.updated_at,
                    o.street,
                    o.area,
                    o.city,
                    o.state,
                    o.postcode,
                    p.payment_id,
                    p.payment_status,
                    p.amount AS payment_amount,
                    p.transaction_id,
                    p.payment_date as payment_transaction_date,
                    p.payment_method as payment_payment_method
                   FROM orders o
                   LEFT JOIN payments p ON o.order_id = p.order_id
                   WHERE o.customer_id = ? 
                   ORDER BY o.order_date DESC";
    
    $orders_stmt = $conn->prepare($orders_sql);
    $orders_stmt->bind_param("i", $customer_id);
    $orders_stmt->execute();
    $orders_result = $orders_stmt->get_result();
    
    $orders = [];
    $total_spent = 0;
    $completed_payments = 0;
    $total_orders = 0;
    
    if ($orders_result && $orders_result->num_rows > 0) {
        while ($order = $orders_result->fetch_assoc()) {
            $order_id = $order['order_id'];
            $total_orders++;
            
            // 获取每个订单的订单项详情
            $items_sql = "SELECT 
                            oi.order_item_id,
                            oi.book_id,
                            oi.quantity,
                            oi.unit_price,
                            b.title,
                            b.author,
                            b.cover_image,
                            b.price AS original_price,
                            c.category_name,
                            (oi.quantity * oi.unit_price) as item_total
                          FROM order_items oi
                          LEFT JOIN books b ON oi.book_id = b.book_id
                          LEFT JOIN categories c ON b.category_id = c.category_id
                          WHERE oi.order_id = ?";
            $items_stmt = $conn->prepare($items_sql);
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            $items = [];
            $order_items_total = 0;
            while ($item = $items_result->fetch_assoc()) {
                $item_total = $item['quantity'] * $item['unit_price'];
                $item['item_total'] = number_format($item_total, 2);
                $order_items_total += $item_total;
                $items[] = $item;
            }
            $items_stmt->close();
            
            // 所有订单都视为已支付完成
            $payment_status = 'completed'; // 强制设置为completed
            $payment_amount = $order['payment_amount'] ?? $order['total_amount'];
            $total_spent += floatval($payment_amount);
            $completed_payments++; // 所有订单都计入完成支付
            
            // 格式化订单状态显示
            $order_status = $order['status'] ?? 'confirmed';
            $order['status'] = $order_status;
            $order['status_display'] = formatOrderStatus($order_status);
            $order['status_class'] = getStatusClass($order_status);
            
            // 支付状态强制为完成
            $order['payment_status'] = 'completed';
            $order['payment_status_display'] = 'Completed';
            
            // 格式化支付方式显示
            $payment_method = $order['payment_method'] ?? $order['payment_payment_method'] ?? 'CARD';
            $order['payment_method'] = $payment_method;
            $order['payment_method_display'] = formatPaymentMethod($payment_method);
            
            // 构建完整地址
            $address_parts = [];
            if (!empty($order['street'])) $address_parts[] = $order['street'];
            if (!empty($order['area'])) $address_parts[] = $order['area'];
            if (!empty($order['city'])) $address_parts[] = $order['city'];
            if (!empty($order['state'])) $address_parts[] = $order['state'];
            if (!empty($order['postcode'])) $address_parts[] = $order['postcode'];
            
            if (empty($address_parts) && !empty($order['shipping_address'])) {
                $order['full_address'] = $order['shipping_address'];
            } else {
                $order['full_address'] = implode(', ', $address_parts);
            }
            
            $order['items'] = $items;
            $order['item_count'] = count($items);
            
            // 格式化日期
            if (isset($order['order_date'])) {
                $order['order_date_formatted'] = date('Y-m-d H:i', strtotime($order['order_date']));
            }
            if (isset($order['updated_at'])) {
                $order['updated_at_formatted'] = date('Y-m-d H:i', strtotime($order['updated_at']));
            }
            if (isset($order['payment_date'])) {
                $order['payment_date_formatted'] = date('Y-m-d H:i', strtotime($order['payment_date']));
            }
            
            // 格式化金额
            $order['total_amount_formatted'] = number_format(floatval($order['total_amount']), 2);
            $order['payment_amount_formatted'] = number_format(floatval($payment_amount), 2);
            
            $orders[] = $order;
        }
    }
    
    $orders_stmt->close();
    
    // 获取订单状态统计
    $status_stats_sql = "SELECT 
                            status,
                            COUNT(*) as count,
                            SUM(total_amount) as total_amount
                         FROM orders 
                         WHERE customer_id = ?
                         GROUP BY status";
    
    $stats_stmt = $conn->prepare($status_stats_sql);
    $stats_stmt->bind_param("i", $customer_id);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    $status_stats = [];
    
    while ($row = $stats_result->fetch_assoc()) {
        $status_stats[] = $row;
    }
    $stats_stmt->close();
    
    echo json_encode([
        'success' => true,
        'customer' => [
            'customer_id' => $customer['auto_id'],
            'recipient_name' => $customer['recipient_name'] ?? $customer['first_name'] ?? '',
            'username' => $customer['username'] ?? $customer['first_name'] ?? '',
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
        'order_count' => $total_orders,
        'status_stats' => $status_stats,
        'payment_stats' => [
            'total_spent' => $total_spent,
            'completed_payments' => $completed_payments,
            'total_orders' => $total_orders
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();

// 辅助函数：格式化订单状态
function formatOrderStatus($status) {
    if (empty($status)) return 'Confirmed';
    
    $status_map = [
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled'
    ];
    
    return $status_map[strtolower($status)] ?? ucfirst($status);
}

// 辅助函数：获取状态类名
function getStatusClass($status) {
    $status = strtolower($status);
    $class_map = [
        'confirmed' => 'status-confirmed',
        'processing' => 'status-confirmed',
        'shipped' => 'status-shipped',
        'delivered' => 'status-delivered',
        'cancelled' => 'status-cancelled'
    ];
    
    return $class_map[$status] ?? 'status-confirmed';
}

// 辅助函数：格式化支付状态 - 简化版本
function formatPaymentStatus($status) {
    // 所有支付都默认为完成
    return 'Completed';
}

// 辅助函数：格式化支付方式
// 辅助函数：格式化支付方式
function formatPaymentMethod($method) {
    if (empty($method)) return 'Card';
    
    $method_map = [
        'card' => 'Credit Card',
        'credit_card' => 'Credit Card',
        'debit_card' => 'Debit Card',
        'cod' => 'Cash on Delivery',
        'cash_on_delivery' => 'Cash on Delivery',
        'bank_transfer' => 'Bank Transfer',
        'paypal' => 'PayPal',
        'VISA' => 'VISA',  // 保持 VISA 不变
        'MASTERCARD' => 'MasterCard',
        'CREDIT' => 'Credit Card',
        'DEBIT' => 'Debit Card',
        'CARD' => 'Credit Card',
        'PAYPAL' => 'PayPal',
        'BANK_TRANSFER' => 'Bank Transfer',
        'CASH_ON_DELIVERY' => 'Cash on Delivery'
    ];
    
    $method = strtoupper($method);
    
    // 如果直接匹配，返回对应的显示名称
    if (isset($method_map[$method])) {
        return $method_map[$method];
    }
    
    // 特殊处理：如果包含 VISA、MASTERCARD 等关键字
    if (strpos($method, 'VISA') !== false) {
        return 'VISA';
    }
    if (strpos($method, 'MASTERCARD') !== false) {
        return 'MasterCard';
    }
    
    // 默认处理
    return ucwords(strtolower(str_replace('_', ' ', $method)));
}
?>