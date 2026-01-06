<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 数据库连接
require_once '../include/database.php';

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
    // 1. 获取今日销售数据
    $today_sales_sql = "
        SELECT 
            COUNT(DISTINCT o.order_id) as today_orders,
            COUNT(DISTINCT o.customer_id) as today_customers,
            COALESCE(SUM(oi.subtotal), 0) as today_revenue,
            COALESCE(AVG(oi.subtotal), 0) as today_avg_order,
            COALESCE(SUM(oi.quantity), 0) as today_items_sold
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE DATE(o.order_date) = CURDATE()
        AND o.status IN ('confirmed', 'shipped', 'delivered')
    ";
    
    $today_result = $conn->query($today_sales_sql);
    $today_sales = $today_result->fetch_assoc();
    
    // 2. 获取昨日销售数据（用于对比）
    $yesterday_sales_sql = "
        SELECT 
            COUNT(DISTINCT o.order_id) as yesterday_orders,
            COALESCE(SUM(oi.subtotal), 0) as yesterday_revenue
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE DATE(o.order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        AND o.status IN ('confirmed', 'shipped', 'delivered')
    ";
    
    $yesterday_result = $conn->query($yesterday_sales_sql);
    $yesterday_sales = $yesterday_result->fetch_assoc();
    
    // 3. 获取本周销售数据
    $week_sales_sql = "
        SELECT 
            COUNT(DISTINCT o.order_id) as week_orders,
            COUNT(DISTINCT o.customer_id) as week_customers,
            COALESCE(SUM(oi.subtotal), 0) as week_revenue,
            COALESCE(SUM(oi.quantity), 0) as week_items_sold
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE YEARWEEK(o.order_date, 1) = YEARWEEK(CURDATE(), 1)
        AND o.status IN ('confirmed', 'shipped', 'delivered')
    ";
    
    $week_result = $conn->query($week_sales_sql);
    $week_sales = $week_result->fetch_assoc();
    
    // 4. 获取本月销售数据
    $month_sales_sql = "
        SELECT 
            COUNT(DISTINCT o.order_id) as month_orders,
            COUNT(DISTINCT o.customer_id) as month_customers,
            COALESCE(SUM(oi.subtotal), 0) as month_revenue,
            COALESCE(SUM(oi.quantity), 0) as month_items_sold
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE MONTH(o.order_date) = MONTH(CURDATE())
        AND YEAR(o.order_date) = YEAR(CURDATE())
        AND o.status IN ('confirmed', 'shipped', 'delivered')
    ";
    
    $month_result = $conn->query($month_sales_sql);
    $month_sales = $month_result->fetch_assoc();
    
    // 5. 获取今年销售数据
    $year_sales_sql = "
        SELECT 
            COUNT(DISTINCT o.order_id) as year_orders,
            COUNT(DISTINCT o.customer_id) as year_customers,
            COALESCE(SUM(oi.subtotal), 0) as year_revenue,
            COALESCE(SUM(oi.quantity), 0) as year_items_sold
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE YEAR(o.order_date) = YEAR(CURDATE())
        AND o.status IN ('confirmed', 'shipped', 'delivered')
    ";
    
    $year_result = $conn->query($year_sales_sql);
    $year_sales = $year_result->fetch_assoc();
    
    // 6. 获取实时销售数据（最近24小时）
    $recent_sales_sql = "
        SELECT 
            HOUR(o.order_date) as hour,
            COUNT(DISTINCT o.order_id) as hourly_orders,
            COALESCE(SUM(oi.subtotal), 0) as hourly_revenue
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
    
    // 7. 获取待处理订单统计
    $pending_orders_sql = "
        SELECT 
            COUNT(*) as pending_count,
            COALESCE(SUM(total_amount), 0) as pending_amount
        FROM orders 
        WHERE status = 'pending'
    ";
    
    $pending_result = $conn->query($pending_orders_sql);
    $pending_orders = $pending_result->fetch_assoc();
    
    // 8. 获取活跃客户统计
    $active_customers_sql = "
        SELECT 
            COUNT(DISTINCT customer_id) as active_customers,
            COUNT(DISTINCT CASE WHEN DATE(order_date) = CURDATE() THEN customer_id END) as today_active_customers
        FROM orders 
        WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ";
    
    $active_result = $conn->query($active_customers_sql);
    $active_customers = $active_result->fetch_assoc();
    
    // 9. 获取库存概览
    $inventory_overview_sql = "
        SELECT 
            COUNT(*) as total_books,
            COALESCE(SUM(stock_quantity), 0) as total_stock,
            SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
            SUM(CASE WHEN stock_quantity BETWEEN 1 AND 10 THEN 1 ELSE 0 END) as low_stock,
            SUM(CASE WHEN stock_quantity > 10 THEN 1 ELSE 0 END) as in_stock
        FROM books
    ";
    
    $inventory_result = $conn->query($inventory_overview_sql);
    $inventory_overview = $inventory_result->fetch_assoc();
    
    // 计算增长率
    $yesterday_orders = $yesterday_sales['yesterday_orders'] ?: 1;
    $yesterday_revenue = $yesterday_sales['yesterday_revenue'] ?: 1;
    
    $orders_growth = $yesterday_orders > 0 ? 
        (($today_sales['today_orders'] - $yesterday_orders) / $yesterday_orders) * 100 : 0;
    
    $revenue_growth = $yesterday_revenue > 0 ? 
        (($today_sales['today_revenue'] - $yesterday_revenue) / $yesterday_revenue) * 100 : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'today' => [
                'orders' => intval($today_sales['today_orders']),
                'customers' => intval($today_sales['today_customers']),
                'revenue' => floatval($today_sales['today_revenue']),
                'avg_order' => floatval($today_sales['today_avg_order']),
                'items_sold' => intval($today_sales['today_items_sold'])
            ],
            'yesterday' => [
                'orders' => intval($yesterday_sales['yesterday_orders']),
                'revenue' => floatval($yesterday_sales['yesterday_revenue'])
            ],
            'week' => [
                'orders' => intval($week_sales['week_orders']),
                'customers' => intval($week_sales['week_customers']),
                'revenue' => floatval($week_sales['week_revenue']),
                'items_sold' => intval($week_sales['week_items_sold'])
            ],
            'month' => [
                'orders' => intval($month_sales['month_orders']),
                'customers' => intval($month_sales['month_customers']),
                'revenue' => floatval($month_sales['month_revenue']),
                'items_sold' => intval($month_sales['month_items_sold'])
            ],
            'year' => [
                'orders' => intval($year_sales['year_orders']),
                'customers' => intval($year_sales['year_customers']),
                'revenue' => floatval($year_sales['year_revenue']),
                'items_sold' => intval($year_sales['year_items_sold'])
            ],
            'recent_sales' => $recent_sales,
            'pending_orders' => [
                'count' => intval($pending_orders['pending_count']),
                'amount' => floatval($pending_orders['pending_amount'])
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
                'in_stock' => intval($inventory_overview['in_stock'])
            ],
            'growth_rates' => [
                'orders_growth' => floatval($orders_growth),
                'revenue_growth' => floatval($revenue_growth)
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>