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
    // 获取请求参数
    $period = isset($_GET['period']) ? $_GET['period'] : '30days';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    $customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
    
    // 客户筛选条件
    $customer_condition = "";
    if ($customer_id > 0) {
        $customer_condition = " AND o.customer_id = $customer_id";
    }
    
    // 根据时间段设置日期范围
    $dateCondition = '';
    if ($start_date && $end_date) {
        $dateCondition = "WHERE o.order_date BETWEEN '$start_date' AND '$end_date'";
    } else {
        switch($period) {
            case 'today':
                $dateCondition = "WHERE DATE(o.order_date) = CURDATE()";
                break;
            case 'yesterday':
                $dateCondition = "WHERE DATE(o.order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case '7days':
                $dateCondition = "WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case '30days':
                $dateCondition = "WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case '90days':
                $dateCondition = "WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
                break;
            case 'month':
                $dateCondition = "WHERE MONTH(o.order_date) = MONTH(CURDATE()) AND YEAR(o.order_date) = YEAR(CURDATE())";
                break;
            case 'year':
                $dateCondition = "WHERE YEAR(o.order_date) = YEAR(CURDATE())";
                break;
            default:
                $dateCondition = "WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
    }
    
    // 合并所有条件
    $where_condition = $dateCondition;
    if ($customer_condition) {
        if ($where_condition) {
            $where_condition .= $customer_condition;
        } else {
            $where_condition = "WHERE 1=1" . $customer_condition;
        }
    }
    
    // 1. 获取销售摘要统计
    $summary_sql = "
        SELECT 
            COUNT(DISTINCT o.order_id) as total_orders,
            COUNT(DISTINCT o.customer_id) as total_customers,
            COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_revenue,
            COALESCE(AVG(oi.quantity * oi.unit_price), 0) as avg_order_value,
            MIN(o.order_date) as first_order_date,
            MAX(o.order_date) as last_order_date
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        $where_condition
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
            'first_order_date' => $row['first_order_date'],
            'last_order_date' => $row['last_order_date']
        ];
    }
    
    // 2. 获取销售趋势数据（最近30天）
    $trend_sql = "
        SELECT 
            DATE(o.order_date) as date,
            DAYNAME(o.order_date) as day_name,
            COUNT(DISTINCT o.order_id) as order_count,
            COUNT(DISTINCT o.customer_id) as customer_count,
            COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_revenue,
            COALESCE(SUM(oi.quantity), 0) as total_quantity
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND o.status IN ('confirmed', 'shipped', 'delivered')
        $customer_condition
        GROUP BY DATE(o.order_date), DAYNAME(o.order_date)
        ORDER BY date ASC
    ";
    
    $trend_result = $conn->query($trend_sql);
    $sales_trend = [];
    
    if ($trend_result && $trend_result->num_rows > 0) {
        while($row = $trend_result->fetch_assoc()) {
            $sales_trend[] = [
                'date' => $row['date'],
                'day_name' => $row['day_name'],
                'order_count' => intval($row['order_count']),
                'customer_count' => intval($row['customer_count']),
                'total_revenue' => floatval($row['total_revenue']),
                'total_quantity' => intval($row['total_quantity'])
            ];
        }
    }
    
    // 3. 获取畅销书籍（Top 10）
    $top_books_sql = "
        SELECT 
            b.book_id,
            b.title,
            b.author,
            COALESCE(c.category_name, 'Uncategorized') as category_name,
            b.cover_image,
            COUNT(DISTINCT oi.order_id) as order_count,
            COALESCE(SUM(oi.quantity), 0) as total_quantity,
            COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_revenue,
            COALESCE(AVG(oi.unit_price), 0) as avg_price,
            b.stock_quantity,
            b.price as current_price
        FROM books b
        LEFT JOIN order_items oi ON b.book_id = oi.book_id
        LEFT JOIN orders o ON oi.order_id = o.order_id
        LEFT JOIN categories c ON b.category_id = c.category_id
        $where_condition
        AND o.status IN ('confirmed', 'shipped', 'delivered')
        GROUP BY b.book_id, b.title, b.author, c.category_name, b.cover_image, b.stock_quantity, b.price
        HAVING total_revenue > 0
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
                'cover_image' => $row['cover_image'],
                'order_count' => intval($row['order_count']),
                'total_quantity' => intval($row['total_quantity']),
                'total_revenue' => floatval($row['total_revenue']),
                'avg_price' => floatval($row['avg_price']),
                'stock_quantity' => intval($row['stock_quantity']),
                'current_price' => floatval($row['current_price'])
            ];
        }
    }
    
    // 4. 按类别获取收入
    $category_revenue_sql = "
        SELECT 
            COALESCE(c.category_name, 'Uncategorized') as category_name,
            COUNT(DISTINCT o.order_id) as order_count,
            COUNT(DISTINCT o.customer_id) as customer_count,
            COALESCE(SUM(oi.quantity), 0) as total_quantity,
            COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_revenue,
            COALESCE(AVG(oi.quantity * oi.unit_price), 0) as avg_revenue_per_order
        FROM categories c
        LEFT JOIN books b ON c.category_id = b.category_id
        LEFT JOIN order_items oi ON b.book_id = oi.book_id
        LEFT JOIN orders o ON oi.order_id = o.order_id
        $where_condition
        AND o.status IN ('confirmed', 'shipped', 'delivered')
        GROUP BY c.category_id, c.category_name
        HAVING total_revenue > 0
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
                'customer_count' => intval($row['customer_count']),
                'total_quantity' => intval($row['total_quantity']),
                'total_revenue' => floatval($row['total_revenue']),
                'avg_revenue_per_order' => floatval($row['avg_revenue_per_order'])
            ];
        }
    }
    
    // 5. 获取每日销售数据（按时间段）
    $daily_sales_sql = "
        SELECT 
            DATE(o.order_date) as date,
            DAYNAME(o.order_date) as day_name,
            DAYOFWEEK(o.order_date) as day_of_week,
            COALESCE(SUM(CASE WHEN HOUR(o.order_date) < 12 THEN oi.quantity * oi.unit_price ELSE 0 END), 0) as morning_sales,
            COALESCE(SUM(CASE WHEN HOUR(o.order_date) >= 12 AND HOUR(o.order_date) < 18 THEN oi.quantity * oi.unit_price ELSE 0 END), 0) as afternoon_sales,
            COALESCE(SUM(CASE WHEN HOUR(o.order_date) >= 18 THEN oi.quantity * oi.unit_price ELSE 0 END), 0) as evening_sales,
            COALESCE(SUM(oi.quantity * oi.unit_price), 0) as daily_total,
            COUNT(DISTINCT o.order_id) as daily_orders,
            COUNT(DISTINCT o.customer_id) as daily_customers
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        $where_condition
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
                'daily_orders' => intval($row['daily_orders']),
                'daily_customers' => intval($row['daily_customers'])
            ];
        }
    }
    
    // 6. 获取状态分布
    $status_distribution_sql = "
        SELECT 
            o.status,
            COUNT(DISTINCT o.order_id) as order_count,
            COUNT(DISTINCT o.customer_id) as customer_count,
            COALESCE(SUM(o.total_amount), 0) as total_amount
        FROM orders o
        $where_condition
        GROUP BY o.status
        ORDER BY order_count DESC
    ";
    
    $status_result = $conn->query($status_distribution_sql);
    $status_distribution = [];
    
    if ($status_result && $status_result->num_rows > 0) {
        while($row = $status_result->fetch_assoc()) {
            $status_distribution[] = [
                'status' => $row['status'],
                'order_count' => intval($row['order_count']),
                'customer_count' => intval($row['customer_count']),
                'total_amount' => floatval($row['total_amount'])
            ];
        }
    }
    
    // 7. 获取客户信息（如果选择了特定客户）
    $customer_info = [];
    if ($customer_id > 0) {
        $customer_sql = "
            SELECT 
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
            WHERE auto_id = $customer_id
        ";
        
        $customer_result = $conn->query($customer_sql);
        if ($customer_result && $customer_result->num_rows > 0) {
            $customer_info = $customer_result->fetch_assoc();
        }
    }
    
    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'sales_trend' => $sales_trend,
        'top_books' => $top_books,
        'category_revenue' => $category_revenue,
        'daily_sales' => $daily_sales,
        'status_distribution' => $status_distribution,
        'customer_info' => $customer_info,
        'period' => $period,
        'filters' => [
            'customer_id' => $customer_id,
            'start_date' => $start_date,
            'end_date' => $end_date
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

$conn->close();
?>