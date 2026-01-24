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
    echo json_encode([
        'success' => false,
        'error' => 'Connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// 设置字符集
$conn->set_charset("utf8mb4");

try {
    // 1. 获取今日销售数据 - 修复：确保使用正确的订单状态
    $today_sales_sql = "
        SELECT 
            COUNT(DISTINCT o.order_id) as today_orders,
            COUNT(DISTINCT o.customer_id) as today_customers,
            COALESCE(SUM(o.total_amount), 0) as today_revenue,
            COALESCE(AVG(o.total_amount), 0) as today_avg_order,
            COALESCE(SUM(oi.quantity), 0) as today_items_sold
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE DATE(o.order_date) = CURDATE()
        AND o.status IN ('confirmed', 'shipped', 'delivered')
    ";
    
    $today_result = $conn->query($today_sales_sql);
    $today_sales = $today_result->fetch_assoc();
    
    // 2. 获取昨日销售数据（用于对比）- 修复：确保查询正确
    $yesterday_sales_sql = "
        SELECT 
            COUNT(DISTINCT o.order_id) as yesterday_orders,
            COALESCE(SUM(o.total_amount), 0) as yesterday_revenue
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE DATE(o.order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        AND o.status IN ('confirmed', 'shipped', 'delivered')
    ";
    
    $yesterday_result = $conn->query($yesterday_sales_sql);
    $yesterday_sales = $yesterday_result->fetch_assoc();
    
    // 3. 获取本周销售数据 - 修复：确保查询正确
    $week_sales_sql = "
        SELECT 
            COUNT(DISTINCT o.order_id) as week_orders,
            COUNT(DISTINCT o.customer_id) as week_customers,
            COALESCE(SUM(o.total_amount), 0) as week_revenue,
            COALESCE(SUM(oi.quantity), 0) as week_items_sold
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE YEARWEEK(o.order_date, 1) = YEARWEEK(CURDATE(), 1)
        AND o.status IN ('confirmed', 'shipped', 'delivered')
    ";
    
    $week_result = $conn->query($week_sales_sql);
    $week_sales = $week_result->fetch_assoc();
    
    // 4. 获取本月销售数据 - 修复：确保查询正确
    $month_sales_sql = "
        SELECT 
            COUNT(DISTINCT o.order_id) as month_orders,
            COUNT(DISTINCT o.customer_id) as month_customers,
            COALESCE(SUM(o.total_amount), 0) as month_revenue,
            COALESCE(SUM(oi.quantity), 0) as month_items_sold
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE MONTH(o.order_date) = MONTH(CURDATE())
        AND YEAR(o.order_date) = YEAR(CURDATE())
        AND o.status IN ('confirmed', 'shipped', 'delivered')
    ";
    
    $month_result = $conn->query($month_sales_sql);
    $month_sales = $month_result->fetch_assoc();
    
    // 5. 获取今年销售数据 - 修复：确保查询正确
    $year_sales_sql = "
        SELECT 
            COUNT(DISTINCT o.order_id) as year_orders,
            COUNT(DISTINCT o.customer_id) as year_customers,
            COALESCE(SUM(o.total_amount), 0) as year_revenue,
            COALESCE(SUM(oi.quantity), 0) as year_items_sold
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE YEAR(o.order_date) = YEAR(CURDATE())
        AND o.status IN ('confirmed', 'shipped', 'delivered')
    ";
    
    $year_result = $conn->query($year_sales_sql);
    $year_sales = $year_result->fetch_assoc();
    
    // 6. 获取实时销售数据（最近24小时）- 修复：添加默认值
    $recent_sales_sql = "
        SELECT 
            HOUR(o.order_date) as hour,
            COUNT(DISTINCT o.order_id) as hourly_orders,
            COALESCE(SUM(o.total_amount), 0) as hourly_revenue
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND o.status IN ('confirmed', 'shipped', 'delivered')
        GROUP BY HOUR(o.order_date)
        ORDER BY hour
    ";
    
    $recent_result = $conn->query($recent_sales_sql);
    $recent_sales = [];
    
    if ($recent_result && $recent_result->num_rows > 0) {
        while($row = $recent_result->fetch_assoc()) {
            $recent_sales[] = [
                'hour' => intval($row['hour']),
                'hourly_orders' => intval($row['hourly_orders']),
                'hourly_revenue' => floatval($row['hourly_revenue'])
            ];
        }
    }
    
    // 7. 获取待处理订单统计 - 修复：orders表没有'pending'状态，使用'confirmed'
    $pending_orders_sql = "
        SELECT 
            COUNT(*) as pending_count,
            COALESCE(SUM(total_amount), 0) as pending_amount
        FROM orders 
        WHERE status = 'confirmed'
    ";
    
    $pending_result = $conn->query($pending_orders_sql);
    $pending_orders = $pending_result->fetch_assoc();
    
    // 8. 获取活跃客户统计 - 修复：确保查询正确
    $active_customers_sql = "
        SELECT 
            COUNT(DISTINCT customer_id) as active_customers,
            COUNT(DISTINCT CASE WHEN DATE(order_date) = CURDATE() THEN customer_id END) as today_active_customers
        FROM orders 
        WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND status NOT IN ('cancelled')
    ";
    
    $active_result = $conn->query($active_customers_sql);
    $active_customers = $active_result->fetch_assoc();
    
    // 9. 获取库存概览 - 修复：确保查询正确
    $inventory_overview_sql = "
        SELECT 
            COUNT(*) as total_books,
            COALESCE(SUM(stock_quantity), 0) as total_stock,
            SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
            SUM(CASE WHEN stock_quantity BETWEEN 1 AND 10 THEN 1 ELSE 0 END) as low_stock,
            SUM(CASE WHEN stock_quantity > 10 THEN 1 ELSE 0 END) as in_stock
        FROM books
        WHERE 1=1
    ";
    
    $inventory_result = $conn->query($inventory_overview_sql);
    $inventory_overview = $inventory_result->fetch_assoc();
    
    // 10. 获取销售排行榜（最畅销书籍）- 添加用于调试
    $top_selling_sql = "
        SELECT 
            b.book_id,
            b.title,
            b.author,
            b.cover_image,
            COALESCE(SUM(oi.quantity), 0) as total_sold,
            COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_revenue,
            b.stock_quantity
        FROM books b
        LEFT JOIN order_items oi ON b.book_id = oi.book_id
        LEFT JOIN orders o ON oi.order_id = o.order_id
        WHERE (o.status IN ('confirmed', 'shipped', 'delivered') OR o.status IS NULL)
        AND (o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) OR o.order_date IS NULL)
        GROUP BY b.book_id, b.title, b.author, b.cover_image, b.stock_quantity
        ORDER BY total_sold DESC, total_revenue DESC
        LIMIT 10
    ";
    
    $top_result = $conn->query($top_selling_sql);
    $top_selling_books = [];
    
    if ($top_result && $top_result->num_rows > 0) {
        while($row = $top_result->fetch_assoc()) {
            $top_selling_books[] = [
                'book_id' => intval($row['book_id']),
                'title' => $row['title'],
                'author' => $row['author'],
                'cover_image' => $row['cover_image'],
                'total_sold' => intval($row['total_sold']),
                'total_revenue' => floatval($row['total_revenue']),
                'stock_quantity' => intval($row['stock_quantity'])
            ];
        }
    }
    
    // 11. 获取支付方式统计
    $payment_methods_sql = "
        SELECT 
            COALESCE(p.payment_method, o.payment_method, 'Unknown') as payment_method,
            COUNT(DISTINCT o.order_id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_amount
        FROM orders o
        LEFT JOIN payments p ON o.order_id = p.order_id
        WHERE o.status IN ('confirmed', 'shipped', 'delivered')
        AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY COALESCE(p.payment_method, o.payment_method, 'Unknown')
        ORDER BY total_amount DESC
        LIMIT 10
    ";
    
    $payment_result = $conn->query($payment_methods_sql);
    $payment_methods = [];
    
    if ($payment_result && $payment_result->num_rows > 0) {
        while($row = $payment_result->fetch_assoc()) {
            $payment_methods[] = [
                'payment_method' => $row['payment_method'],
                'order_count' => intval($row['order_count']),
                'total_amount' => floatval($row['total_amount'])
            ];
        }
    }
    
    // 计算增长率 - 修复：防止除零错误
    $yesterday_orders = floatval($yesterday_sales['yesterday_orders']) ?? 0;
    $yesterday_revenue = floatval($yesterday_sales['yesterday_revenue']) ?? 0;
    
    $today_orders = floatval($today_sales['today_orders']) ?? 0;
    $today_revenue = floatval($today_sales['today_revenue']) ?? 0;
    
    $orders_growth = 0;
    if ($yesterday_orders > 0) {
        $orders_growth = (($today_orders - $yesterday_orders) / $yesterday_orders) * 100;
    } else if ($today_orders > 0) {
        $orders_growth = 100; // 从0到有订单，增长100%
    }
    
    $revenue_growth = 0;
    if ($yesterday_revenue > 0) {
        $revenue_growth = (($today_revenue - $yesterday_revenue) / $yesterday_revenue) * 100;
    } else if ($today_revenue > 0) {
        $revenue_growth = 100; // 从0到有收入，增长100%
    }
    
    // 12. 获取总销售统计
    $total_stats_sql = "
        SELECT 
            COUNT(DISTINCT o.order_id) as total_all_orders,
            COUNT(DISTINCT o.customer_id) as total_all_customers,
            COALESCE(SUM(o.total_amount), 0) as total_all_revenue,
            MIN(o.order_date) as first_order_date,
            MAX(o.order_date) as last_order_date
        FROM orders o
        WHERE o.status IN ('confirmed', 'shipped', 'delivered')
    ";
    
    $total_stats_result = $conn->query($total_stats_sql);
    $total_stats = $total_stats_result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'today_query_conditions' => 'DATE(order_date) = CURDATE() AND status IN (confirmed, shipped, delivered)',
            'today_date' => date('Y-m-d'),
            'query_executed' => 'success'
        ],
        'data' => [
            'today' => [
                'orders' => intval($today_sales['today_orders']),
                'customers' => intval($today_sales['today_customers']),
                'revenue' => floatval($today_sales['today_revenue']),
                'revenue_formatted' => 'RM ' . number_format($today_sales['today_revenue'], 2),
                'avg_order' => floatval($today_sales['today_avg_order']),
                'avg_order_formatted' => 'RM ' . number_format($today_sales['today_avg_order'], 2),
                'items_sold' => intval($today_sales['today_items_sold'])
            ],
            'yesterday' => [
                'orders' => intval($yesterday_sales['yesterday_orders']),
                'revenue' => floatval($yesterday_sales['yesterday_revenue']),
                'revenue_formatted' => 'RM ' . number_format($yesterday_sales['yesterday_revenue'], 2)
            ],
            'week' => [
                'orders' => intval($week_sales['week_orders']),
                'customers' => intval($week_sales['week_customers']),
                'revenue' => floatval($week_sales['week_revenue']),
                'revenue_formatted' => 'RM ' . number_format($week_sales['week_revenue'], 2),
                'items_sold' => intval($week_sales['week_items_sold'])
            ],
            'month' => [
                'orders' => intval($month_sales['month_orders']),
                'customers' => intval($month_sales['month_customers']),
                'revenue' => floatval($month_sales['month_revenue']),
                'revenue_formatted' => 'RM ' . number_format($month_sales['month_revenue'], 2),
                'items_sold' => intval($month_sales['month_items_sold'])
            ],
            'year' => [
                'orders' => intval($year_sales['year_orders']),
                'customers' => intval($year_sales['year_customers']),
                'revenue' => floatval($year_sales['year_revenue']),
                'revenue_formatted' => 'RM ' . number_format($year_sales['year_revenue'], 2),
                'items_sold' => intval($year_sales['year_items_sold'])
            ],
            'total' => [
                'orders' => intval($total_stats['total_all_orders']),
                'customers' => intval($total_stats['total_all_customers']),
                'revenue' => floatval($total_stats['total_all_revenue']),
                'revenue_formatted' => 'RM ' . number_format($total_stats['total_all_revenue'], 2),
                'first_order_date' => $total_stats['first_order_date'],
                'last_order_date' => $total_stats['last_order_date']
            ],
            'recent_sales' => $recent_sales,
            'pending_orders' => [
                'count' => intval($pending_orders['pending_count']),
                'amount' => floatval($pending_orders['pending_amount']),
                'amount_formatted' => 'RM ' . number_format($pending_orders['pending_amount'], 2)
            ],
            'active_customers' => [
                'total' => intval($active_customers['active_customers']),
                'today' => intval($active_customers['today_active_customers'])
            ],
            'inventory' => [
                'total_books' => intval($inventory_overview['total_books']),
                'total_stock' => intval($inventory_overview['total_stock']),
                'out_of_stock' => intval($inventory_overview['out_of_stock']),
                'low_stock' => intval($inventory_overview['low_stock']),
                'in_stock' => intval($inventory_overview['in_stock']),
                'stock_percentage' => $inventory_overview['total_books'] > 0 ? 
                    round(($inventory_overview['in_stock'] / $inventory_overview['total_books']) * 100, 2) : 0
            ],
            'growth_rates' => [
                'orders_growth' => floatval($orders_growth),
                'revenue_growth' => floatval($revenue_growth),
                'orders_growth_formatted' => number_format($orders_growth, 2) . '%',
                'revenue_growth_formatted' => number_format($revenue_growth, 2) . '%',
                'status' => $orders_growth > 0 ? 'up' : ($orders_growth < 0 ? 'down' : 'neutral')
            ],
            'top_selling_books' => $top_selling_books,
            'payment_methods' => $payment_methods,
            'summary' => [
                'total_revenue' => floatval($total_stats['total_all_revenue']),
                'total_revenue_formatted' => 'RM ' . number_format($total_stats['total_all_revenue'], 2),
                'total_orders' => intval($total_stats['total_all_orders']),
                'total_customers' => intval($total_stats['total_all_customers']),
                'total_books' => intval($inventory_overview['total_books']),
                'today_summary' => 'Today: ' . intval($today_sales['today_orders']) . ' orders, RM ' . 
                    number_format($today_sales['today_revenue'], 2) . ' revenue',
                'this_month_summary' => 'This Month: ' . intval($month_sales['month_orders']) . ' orders, RM ' . 
                    number_format($month_sales['month_revenue'], 2) . ' revenue'
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

$conn->close();
?>