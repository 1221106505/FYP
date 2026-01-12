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
    // 获取请求参数
    $period = isset($_GET['period']) ? $_GET['period'] : '30days';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    
    // 根据时间段设置日期范围
    $dateCondition = '';
    if ($start_date && $end_date) {
        $dateCondition = "WHERE o.order_date BETWEEN '$start_date' AND '$end_date'";
    } else {
        switch($period) {
            case '7days':
                $dateCondition = "WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case '30days':
                $dateCondition = "WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case '90days':
                $dateCondition = "WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
                break;
            case 'year':
                $dateCondition = "WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
            default:
                $dateCondition = "WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
    }
    
    // 1. 获取销售趋势数据
    $trend_sql = "
        SELECT 
            DATE(o.order_date) as date,
            COUNT(DISTINCT o.order_id) as order_count,
            COUNT(DISTINCT o.customer_id) as customer_count,
            COALESCE(SUM(oi.subtotal), 0) as total_revenue,
            COALESCE(SUM(oi.quantity), 0) as total_quantity
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        $dateCondition
        AND o.status IN ('confirmed', 'shipped', 'delivered')
        GROUP BY DATE(o.order_date)
        ORDER BY date DESC
        LIMIT 30
    ";
    
    $trend_result = $conn->query($trend_sql);
    $sales_trend = [];
    
    if ($trend_result && $trend_result->num_rows > 0) {
        while($row = $trend_result->fetch_assoc()) {
            $sales_trend[] = [
                'date' => $row['date'],
                'order_count' => intval($row['order_count']),
                'customer_count' => intval($row['customer_count']),
                'total_revenue' => floatval($row['total_revenue']),
                'total_quantity' => intval($row['total_quantity'])
            ];
        }
    }
    
    // 2. 获取畅销书籍（Top 10）- 修正SQL
    $top_books_sql = "
        SELECT 
            b.book_id,
            b.title,
            b.author,
            COALESCE(c.category_name, 'Uncategorized') as category_name,
            COUNT(DISTINCT oi.order_id) as order_count,
            COALESCE(SUM(oi.quantity), 0) as total_quantity,
            COALESCE(SUM(oi.subtotal), 0) as total_revenue,
            COALESCE(AVG(oi.unit_price), 0) as avg_price,
            b.stock_quantity
        FROM books b
        LEFT JOIN order_items oi ON b.book_id = oi.book_id
        LEFT JOIN orders o ON oi.order_id = o.order_id
        LEFT JOIN categories c ON b.category_id = c.category_id
        $dateCondition
        AND o.status IN ('confirmed', 'shipped', 'delivered')
        GROUP BY b.book_id, b.title, b.author, c.category_name, b.stock_quantity
        ORDER BY total_revenue DESC
        LIMIT 10
    ";
    
    $top_books_result = $conn->query($top_books_sql);
    $top_books = [];
    
    if ($top_books_result && $top_books_result->num_rows > 0) {
        while($row = $top_books_result->fetch_assoc()) {
            $top_books[] = [
                'book_id' => $row['book_id'],
                'title' => $row['title'],
                'author' => $row['author'],
                'category' => $row['category_name'],
                'order_count' => intval($row['order_count']),
                'total_quantity' => intval($row['total_quantity']),
                'total_revenue' => floatval($row['total_revenue']),
                'avg_price' => floatval($row['avg_price']),
                'stock_quantity' => intval($row['stock_quantity'])
            ];
        }
    }
    
    // 3. 按类别获取收入 - 修正SQL
    $category_revenue_sql = "
        SELECT 
            COALESCE(c.category_name, 'Uncategorized') as category_name,
            COUNT(DISTINCT oi.order_id) as order_count,
            COALESCE(SUM(oi.quantity), 0) as total_quantity,
            COALESCE(SUM(oi.subtotal), 0) as total_revenue,
            COUNT(DISTINCT o.customer_id) as customer_count
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        LEFT JOIN books b ON oi.book_id = b.book_id
        LEFT JOIN categories c ON b.category_id = c.category_id
        $dateCondition
        AND o.status IN ('confirmed', 'shipped', 'delivered')
        GROUP BY COALESCE(c.category_name, 'Uncategorized')
        ORDER BY total_revenue DESC
        LIMIT 10
    ";
    
    $category_revenue_result = $conn->query($category_revenue_sql);
    $category_revenue = [];
    
    if ($category_revenue_result && $category_revenue_result->num_rows > 0) {
        while($row = $category_revenue_result->fetch_assoc()) {
            $category_revenue[] = [
                'category_name' => $row['category_name'],
                'order_count' => intval($row['order_count']),
                'total_quantity' => intval($row['total_quantity']),
                'total_revenue' => floatval($row['total_revenue']),
                'customer_count' => intval($row['customer_count'])
            ];
        }
    }
    
    // 4. 获取销售摘要统计
    $summary_sql = "
        SELECT 
            COUNT(DISTINCT o.order_id) as total_orders,
            COUNT(DISTINCT o.customer_id) as total_customers,
            COALESCE(SUM(oi.subtotal), 0) as total_revenue,
            COALESCE(AVG(oi.subtotal), 0) as avg_order_value,
            MAX(o.order_date) as last_order_date,
            MIN(o.order_date) as first_order_date
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        $dateCondition
        AND o.status IN ('confirmed', 'shipped', 'delivered')
    ";
    
    $summary_result = $conn->query($summary_sql);
    $summary = [];
    
    if ($summary_result && $summary_result->num_rows > 0) {
        $row = $summary_result->fetch_assoc();
        $summary = [
            'total_orders' => intval($row['total_orders']),
            'total_customers' => intval($row['total_customers']),
            'total_revenue' => floatval($row['total_revenue']),
            'avg_order_value' => floatval($row['avg_order_value']),
            'last_order_date' => $row['last_order_date'],
            'first_order_date' => $row['first_order_date']
        ];
    }
    
    // 5. 获取每日销售数据用于图表
    $daily_sales_sql = "
        SELECT 
            DATE(o.order_date) as date,
            DAYNAME(o.order_date) as day_name,
            DAYOFWEEK(o.order_date) as day_of_week,
            COALESCE(SUM(CASE WHEN HOUR(o.order_date) < 12 THEN oi.subtotal ELSE 0 END), 0) as morning_sales,
            COALESCE(SUM(CASE WHEN HOUR(o.order_date) >= 12 AND HOUR(o.order_date) < 18 THEN oi.subtotal ELSE 0 END), 0) as afternoon_sales,
            COALESCE(SUM(CASE WHEN HOUR(o.order_date) >= 18 THEN oi.subtotal ELSE 0 END), 0) as evening_sales,
            COALESCE(SUM(oi.subtotal), 0) as daily_total,
            COUNT(DISTINCT o.order_id) as daily_orders
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        $dateCondition
        AND o.status IN ('confirmed', 'shipped', 'delivered')
        GROUP BY DATE(o.order_date), DAYNAME(o.order_date), DAYOFWEEK(o.order_date)
        ORDER BY date ASC
        LIMIT 30
    ";
    
    $daily_sales_result = $conn->query($daily_sales_sql);
    $daily_sales = [];
    
    if ($daily_sales_result && $daily_sales_result->num_rows > 0) {
        while($row = $daily_sales_result->fetch_assoc()) {
            $daily_sales[] = [
                'date' => $row['date'],
                'day_name' => $row['day_name'],
                'day_of_week' => intval($row['day_of_week']),
                'morning_sales' => floatval($row['morning_sales']),
                'afternoon_sales' => floatval($row['afternoon_sales']),
                'evening_sales' => floatval($row['evening_sales']),
                'daily_total' => floatval($row['daily_total']),
                'daily_orders' => intval($row['daily_orders'])
            ];
        }
    }
    
    // 获取当前用户数量（用于活跃用户统计）
    $total_customers_sql = "SELECT COUNT(*) as total FROM customer";
    $total_customers_result = $conn->query($total_customers_sql);
    $total_customers_row = $total_customers_result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'summary' => array_merge($summary, ['total_customers' => intval($total_customers_row['total'])]),
        'sales_trend' => $sales_trend,
        'top_books' => $top_books,
        'category_revenue' => $category_revenue,
        'daily_sales' => $daily_sales,
        'period' => $period,
        'date_condition' => $dateCondition
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>