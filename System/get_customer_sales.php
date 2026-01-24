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

try {
    $customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
    
    if ($customer_id <= 0) {
        die(json_encode(['success' => false, 'error' => 'Invalid customer ID']));
    }
    
    // 获取客户基本信息 - 返回客户信息而不是订单收件人信息
    $sql = "SELECT 
                auto_id as customer_id,
                username,
                email,
                first_name,
                last_name,
                CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name,
                phone,
                address,
                city,
                state,
                zip_code,
                country,
                date_of_birth,
                gender,
                created_at
            FROM customer 
            WHERE auto_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        die(json_encode(['success' => false, 'error' => 'Customer not found']));
    }
    
    $customer = $result->fetch_assoc();
    $stmt->close();
    
    // 获取订单统计 - 包含详细的购买分析
    $stats_sql = "SELECT 
                    COUNT(*) as order_count,
                    SUM(CASE WHEN status NOT IN ('cancelled') THEN total_amount ELSE 0 END) as total_spent,
                    AVG(CASE WHEN status NOT IN ('cancelled') THEN total_amount ELSE NULL END) as avg_order_value,
                    MIN(order_date) as first_order_date,
                    MAX(order_date) as last_order_date,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                    SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders
                  FROM orders 
                  WHERE customer_id = ?";
    
    $stmt = $conn->prepare($stats_sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $stats_result = $stmt->get_result();
    $stats = $stats_result->fetch_assoc();
    
    // 获取客户购买的商品总数
    $items_sql = "SELECT 
                    SUM(oi.quantity) as total_items_purchased,
                    COUNT(DISTINCT oi.book_id) as unique_books_purchased,
                    AVG(oi.quantity) as avg_items_per_order
                  FROM orders o
                  LEFT JOIN order_items oi ON o.order_id = oi.order_id
                  WHERE o.customer_id = ?
                  AND o.status NOT IN ('cancelled')";
    
    $stmt = $conn->prepare($items_sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    $items_stats = $items_result->fetch_assoc();
    
    // 获取客户购买最多的书籍类别
    $category_sql = "SELECT 
                        c.category_name,
                        COUNT(DISTINCT oi.book_id) as books_count,
                        SUM(oi.quantity) as total_quantity
                     FROM orders o
                     LEFT JOIN order_items oi ON o.order_id = oi.order_id
                     LEFT JOIN books b ON oi.book_id = b.book_id
                     LEFT JOIN categories c ON b.category_id = c.category_id
                     WHERE o.customer_id = ?
                     AND o.status NOT IN ('cancelled')
                     AND c.category_name IS NOT NULL
                     GROUP BY c.category_id, c.category_name
                     ORDER BY total_quantity DESC
                     LIMIT 5";
    
    $stmt = $conn->prepare($category_sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $category_result = $stmt->get_result();
    
    $favorite_categories = [];
    while ($row = $category_result->fetch_assoc()) {
        $favorite_categories[] = $row;
    }
    
    // 获取客户最常购买的书籍
    $top_books_sql = "SELECT 
                        b.book_id,
                        b.title,
                        b.author,
                        b.cover_image,
                        SUM(oi.quantity) as total_quantity,
                        SUM(oi.quantity * oi.unit_price) as total_spent
                     FROM orders o
                     LEFT JOIN order_items oi ON o.order_id = oi.order_id
                     LEFT JOIN books b ON oi.book_id = b.book_id
                     WHERE o.customer_id = ?
                     AND o.status NOT IN ('cancelled')
                     AND b.title IS NOT NULL
                     GROUP BY b.book_id, b.title, b.author, b.cover_image
                     ORDER BY total_quantity DESC
                     LIMIT 10";
    
    $stmt = $conn->prepare($top_books_sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $top_books_result = $stmt->get_result();
    
    $favorite_books = [];
    while ($row = $top_books_result->fetch_assoc()) {
        $favorite_books[] = $row;
    }
    
    // 获取购买频率分析
    $frequency_sql = "SELECT 
                        COUNT(DISTINCT DATE(order_date)) as active_days,
                        COUNT(*) / GREATEST(COUNT(DISTINCT DATE(order_date)), 1) as avg_orders_per_day,
                        TIMESTAMPDIFF(DAY, MIN(order_date), MAX(order_date)) as days_between_first_last,
                        COUNT(*) / GREATEST(TIMESTAMPDIFF(DAY, MIN(order_date), CURDATE()), 1) as avg_orders_per_total_days
                     FROM orders 
                     WHERE customer_id = ?
                     AND status NOT IN ('cancelled')";
    
    $stmt = $conn->prepare($frequency_sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $frequency_result = $stmt->get_result();
    $frequency_stats = $frequency_result->fetch_assoc();
    
    // 获取最近订单 - 添加客户名称信息
    $orders_sql = "SELECT 
                    o.order_id,
                    o.recipient_name,
                    o.order_date,
                    o.total_amount,
                    o.status,
                    o.payment_method,
                    (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as items_count,
                    (SELECT SUM(quantity) FROM order_items WHERE order_id = o.order_id) as total_quantity,
                    CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_full_name,
                    c.username as customer_username
                  FROM orders o
                  LEFT JOIN customer c ON o.customer_id = c.auto_id
                  WHERE o.customer_id = ?
                  ORDER BY o.order_date DESC
                  LIMIT 20";
    
    $stmt = $conn->prepare($orders_sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $orders_result = $stmt->get_result();
    
    $orders = [];
    while ($row = $orders_result->fetch_assoc()) {
        // 格式化日期
        if (!empty($row['order_date'])) {
            $row['order_date_formatted'] = date('Y-m-d H:i', strtotime($row['order_date']));
        }
        // 格式化状态
        $row['status_display'] = formatOrderStatus($row['status']);
        // 格式化支付方式
        $row['payment_method_display'] = formatPaymentMethod($row['payment_method']);
        // 格式化金额
        $row['total_amount_formatted'] = number_format($row['total_amount'], 2);
        
        $orders[] = $row;
    }
    
    // 计算客户价值指标
    $customer_value = calculateCustomerValue($stats, $frequency_stats);
    
    echo json_encode([
        'success' => true,
        'customer_info' => $customer,
        'purchase_stats' => array_merge($stats, $items_stats, $frequency_stats),
        'behavior_analysis' => [
            'favorite_categories' => $favorite_categories,
            'favorite_books' => $favorite_books,
            'customer_value' => $customer_value,
            'purchase_frequency' => [
                'active_days' => $frequency_stats['active_days'] ?? 0,
                'avg_orders_per_day' => round($frequency_stats['avg_orders_per_day'] ?? 0, 2),
                'avg_orders_per_total_days' => round($frequency_stats['avg_orders_per_total_days'] ?? 0, 2),
                'days_between_first_last' => $frequency_stats['days_between_first_last'] ?? 0
            ]
        ],
        'recent_orders' => $orders,
        'summary' => [
            'customer_name' => $customer['full_name'] ?: $customer['username'],
            'total_orders' => $stats['order_count'] ?? 0,
            'total_spent' => number_format($stats['total_spent'] ?? 0, 2),
            'avg_order_value' => number_format($stats['avg_order_value'] ?? 0, 2),
            'total_items' => $items_stats['total_items_purchased'] ?? 0,
            'customer_since' => !empty($stats['first_order_date']) ? 
                date('Y-m-d', strtotime($stats['first_order_date'])) : 'No orders yet',
            'last_purchase' => !empty($stats['last_order_date']) ? 
                date('Y-m-d', strtotime($stats['last_order_date'])) : 'Never'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
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

// 辅助函数：格式化支付方式
function formatPaymentMethod($method) {
    if (empty($method)) return 'Not Specified';
    
    $method_map = [
        'card' => 'Credit Card',
        'credit_card' => 'Credit Card',
        'debit_card' => 'Debit Card',
        'cod' => 'Cash on Delivery',
        'cash_on_delivery' => 'Cash on Delivery',
        'bank_transfer' => 'Bank Transfer',
        'paypal' => 'PayPal',
        'VISA' => 'VISA',
        'MASTERCARD' => 'MasterCard',
        'CREDIT' => 'Credit Card',
        'DEBIT' => 'Debit Card',
        'CARD' => 'Credit Card',
        'PAYPAL' => 'PayPal',
        'BANK_TRANSFER' => 'Bank Transfer',
        'CASH_ON_DELIVERY' => 'Cash on Delivery'
    ];
    
    $method_upper = strtoupper($method);
    
    if (isset($method_map[$method_upper])) {
        return $method_map[$method_upper];
    }
    
    return ucwords(strtolower(str_replace('_', ' ', $method)));
}

// 辅助函数：计算客户价值
function calculateCustomerValue($stats, $frequency_stats) {
    $total_spent = $stats['total_spent'] ?? 0;
    $order_count = $stats['order_count'] ?? 0;
    $avg_order_value = $stats['avg_order_value'] ?? 0;
    $days_between = $frequency_stats['days_between_first_last'] ?? 0;
    
    // 客户价值评级
    $value_score = 0;
    
    if ($total_spent > 1000) {
        $value_score = 5; // 高价值客户
    } elseif ($total_spent > 500) {
        $value_score = 4; // 中高价值客户
    } elseif ($total_spent > 200) {
        $value_score = 3; // 中等价值客户
    } elseif ($total_spent > 50) {
        $value_score = 2; // 低价值客户
    } elseif ($total_spent > 0) {
        $value_score = 1; // 新客户
    }
    
    // 购买频率评级
    $frequency_score = 0;
    if ($days_between > 0 && $order_count > 0) {
        $days_per_order = $days_between / $order_count;
        if ($days_per_order < 7) {
            $frequency_score = 5; // 高频购买
        } elseif ($days_per_order < 30) {
            $frequency_score = 4; // 中频购买
        } elseif ($days_per_order < 90) {
            $frequency_score = 3; // 低频购买
        } else {
            $frequency_score = 2; // 极少购买
        }
    }
    
    // 整体价值评级
    $overall_value = round(($value_score + $frequency_score) / 2, 1);
    
    return [
        'value_score' => $value_score,
        'frequency_score' => $frequency_score,
        'overall_value' => $overall_value,
        'value_level' => getValueLevel($overall_value),
        'recommendation' => getRecommendation($value_score, $frequency_score, $order_count)
    ];
}

// 辅助函数：获取价值级别
function getValueLevel($score) {
    if ($score >= 4.5) return 'VIP';
    if ($score >= 4.0) return 'High Value';
    if ($score >= 3.0) return 'Medium Value';
    if ($score >= 2.0) return 'Low Value';
    return 'New Customer';
}

// 辅助函数：获取推荐策略
function getRecommendation($value_score, $frequency_score, $order_count) {
    if ($order_count === 0) {
        return 'New customer - offer welcome discount';
    }
    
    if ($value_score >= 4 && $frequency_score >= 4) {
        return 'VIP customer - offer exclusive benefits';
    }
    
    if ($value_score >= 3 && $frequency_score >= 3) {
        return 'Loyal customer - offer loyalty rewards';
    }
    
    if ($value_score >= 2 && $frequency_score <= 2) {
        return 'Infrequent buyer - send re-engagement offers';
    }
    
    if ($value_score <= 2 && $frequency_score >= 3) {
        return 'Frequent low-value buyer - upsell higher value products';
    }
    
    return 'Regular customer - maintain standard communication';
}
?>