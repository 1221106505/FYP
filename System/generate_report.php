<?php
// enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'Bookstore';

$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// Set charset
$conn->set_charset("utf8mb4");

// Function to get POST or GET data
function getRequest($key, $default = '') {
    if (isset($_POST[$key])) {
        return $_POST[$key];
    } elseif (isset($_GET[$key])) {
        return $_GET[$key];
    }
    return $default;
}

try {
    // Get request parameters
    $reportType = getRequest('report_type', 'full_report');
    $period = getRequest('period', '30days');
    $customerId = getRequest('customer_id', 'all');
    $format = getRequest('format', 'json');
    $startDate = getRequest('start_date');
    $endDate = getRequest('end_date');
    
    // Get report data
    $reportData = generateReportData($reportType, $period, $customerId, $startDate, $endDate);
    
    // For PDF format, we return HTML for printing
    echo json_encode([
        'success' => true,
        'report_data' => $reportData,
        'html_content' => generateReportHTML($reportData, $reportType, $period, $customerId),
        'message' => 'Report generated successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

function generateReportData($type, $period, $customerId, $startDate = null, $endDate = null) {
    global $conn;
    
    // Calculate date range
    $dateRange = calculateDateRange($period, $startDate, $endDate);
    $startDate = $dateRange['start'];
    $endDate = $dateRange['end'];
    
    // Get summary metrics
    $summary = getSummaryMetrics($startDate, $endDate, $customerId);
    
    // Get sales trend data
    $salesTrend = getSalesTrend($startDate, $endDate, $customerId);
    
    // Get top products
    $topProducts = getTopProducts($startDate, $endDate, $customerId);
    
    // Get category performance
    $categoryPerformance = getCategoryPerformance($startDate, $endDate, $customerId);
    
    // Get customer analysis (if customer is specified)
    $customerAnalysis = [];
    if ($customerId !== 'all') {
        $customerAnalysis = getCustomerAnalysis($customerId, $startDate, $endDate);
    }
    
    // 修改：只有当查看所有顾客时才获取顾客列表
    $customerList = [];
    if ($customerId === 'all') {
        $customerList = getCustomerList($startDate, $endDate);
    }
    
    $reportData = [
        'report_id' => 'SR-' . date('YmdHis') . '-' . rand(1000, 9999),
        'period' => $period,
        'date_range' => [
            'start' => $startDate,
            'end' => $endDate
        ],
        'generated_at' => date('Y-m-d H:i:s'),
        'summary' => $summary,
        'sales_trend' => $salesTrend,
        'top_products' => $topProducts,
        'category_performance' => $categoryPerformance,
        'customer_analysis' => $customerAnalysis,
        'customer_list' => $customerList,
        'total_days' => calculateDaysBetween($startDate, $endDate)
    ];
    
    // Add customer info if specific customer
    if ($customerId !== 'all') {
        $reportData['customer_info'] = getCustomerInfo($customerId);
    }
    
    return $reportData;
}

function calculateDateRange($period, $startDate = null, $endDate = null) {
    $today = date('Y-m-d');
    
    if ($period === 'custom' && $startDate && $endDate) {
        return [
            'start' => $startDate,
            'end' => $endDate
        ];
    }
    
    switch ($period) {
        case '7days':
            $start = date('Y-m-d', strtotime('-7 days'));
            break;
        case '30days':
            $start = date('Y-m-d', strtotime('-30 days'));
            break;
        case '90days':
            $start = date('Y-m-d', strtotime('-90 days'));
            break;
        case 'year':
            $start = date('Y-m-d', strtotime('-1 year'));
            break;
        default:
            $start = date('Y-m-d', strtotime('-30 days'));
    }
    
    return [
        'start' => $start,
        'end' => $today
    ];
}

function calculateDaysBetween($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    return $end->diff($start)->days + 1;
}

function getSummaryMetrics($startDate, $endDate, $customerId) {
    global $conn;
    
    // Prepare customer filter
    $customerFilter = "";
    $params = [$startDate, $endDate];
    $types = "ss"; // two strings for dates
    
    if ($customerId !== 'all') {
        $customerFilter = " AND o.customer_id = ?";
        $params[] = $customerId;
        $types .= "i"; // add integer for customer_id
    }
    
    // Total revenue and orders
    $sql = "SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(o.total_amount), 0) as total_revenue,
                COALESCE(AVG(o.total_amount), 0) as average_order_value,
                COUNT(DISTINCT o.customer_id) as total_customers
            FROM orders o 
            WHERE o.order_date BETWEEN ? AND ? 
            AND o.status NOT IN ('cancelled')
            $customerFilter";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc() ?: [];
    
    // Total products sold
    $sql = "SELECT 
                COALESCE(SUM(oi.quantity), 0) as total_products_sold
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE o.order_date BETWEEN ? AND ?
            AND o.status NOT IN ('cancelled')
            $customerFilter";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $productsData = $result->fetch_assoc() ?: [];
        $summary['total_products_sold'] = $productsData['total_products_sold'] ?? 0;
    }
    
    // 修改：只有当查看所有顾客时才计算新顾客数量
    $summary['new_customers'] = 0;
    if ($customerId === 'all') {
        $sql = "SELECT COUNT(*) as new_customers 
                FROM customer 
                WHERE created_at BETWEEN ? AND ?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ss', $startDate, $endDate);
            $stmt->execute();
            $result = $stmt->get_result();
            $newCustomers = $result->fetch_assoc() ?: [];
            $summary['new_customers'] = $newCustomers['new_customers'] ?? 0;
        }
    }
    
    // Calculate daily average
    $days = calculateDaysBetween($startDate, $endDate);
    $summary['daily_average_revenue'] = ($summary['total_revenue'] ?? 0) ? ($summary['total_revenue'] / $days) : 0;
    $summary['daily_average_orders'] = ($summary['total_orders'] ?? 0) ? ($summary['total_orders'] / $days) : 0;
    
    // Get best day
    $bestDay = getBestDay($startDate, $endDate, $customerId);
    $summary['best_day'] = $bestDay['best_date'] ?? 'N/A';
    $summary['best_day_revenue'] = $bestDay['revenue'] ?? 0;
    
    // Ensure all fields have default values
    $defaults = [
        'total_orders' => 0,
        'total_revenue' => 0,
        'average_order_value' => 0,
        'total_customers' => 0,
        'total_products_sold' => 0,
        'new_customers' => 0,
        'daily_average_revenue' => 0,
        'daily_average_orders' => 0
    ];
    
    foreach ($defaults as $key => $default) {
        $summary[$key] = $summary[$key] ?? $default;
    }
    
    return $summary;
}

function getBestDay($startDate, $endDate, $customerId) {
    global $conn;
    
    $customerFilter = "";
    $params = [$startDate, $endDate];
    $types = "ss";
    
    if ($customerId !== 'all') {
        $customerFilter = " AND customer_id = ?";
        $params[] = $customerId;
        $types .= "i";
    }
    
    $sql = "SELECT 
                DATE(order_date) as best_date,
                COALESCE(SUM(total_amount), 0) as revenue
            FROM orders 
            WHERE order_date BETWEEN ? AND ?
            AND status NOT IN ('cancelled')
            $customerFilter
            GROUP BY DATE(order_date)
            ORDER BY revenue DESC
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['best_date' => 'N/A', 'revenue' => 0];
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc() ?: ['best_date' => 'N/A', 'revenue' => 0];
}

function getSalesTrend($startDate, $endDate, $customerId) {
    global $conn;
    
    $customerFilter = "";
    $params = [$startDate, $endDate];
    $types = "ss";
    
    if ($customerId !== 'all') {
        $customerFilter = " AND o.customer_id = ?";
        $params[] = $customerId;
        $types .= "i";
    }
    
    $sql = "SELECT 
                DATE(o.order_date) as date,
                COUNT(DISTINCT o.order_id) as order_count,
                COALESCE(SUM(o.total_amount), 0) as daily_revenue,
                COALESCE(SUM(oi.quantity), 0) as items_sold,
                COUNT(DISTINCT o.customer_id) as unique_customers
            FROM orders o
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            WHERE o.order_date BETWEEN ? AND ?
            AND o.status NOT IN ('cancelled')
            $customerFilter
            GROUP BY DATE(o.order_date)
            ORDER BY date";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $trendData = [];
    while ($row = $result->fetch_assoc()) {
        $trendData[] = $row;
    }
    
    return $trendData;
}

function getTopProducts($startDate, $endDate, $customerId, $limit = 10) {
    global $conn;
    
    $customerFilter = "";
    $params = [$startDate, $endDate];
    $types = "ss";
    
    if ($customerId !== 'all') {
        $customerFilter = " AND o.customer_id = ?";
        $params[] = $customerId;
        $types .= "i";
    }
    
    // Check if books table has title field
    $hasTitleField = true; // Assuming it exists
    
    $sql = "SELECT 
                b.book_id,
                COALESCE(b.title, CONCAT('Book ', b.book_id)) as title,
                COALESCE(b.author, 'Unknown') as author,
                COALESCE(c.category_name, 'Uncategorized') as category_name,
                COALESCE(b.price, 0) as price,
                COALESCE(SUM(oi.quantity), 0) as total_quantity,
                COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_revenue,
                COUNT(DISTINCT o.order_id) as order_count,
                COALESCE(b.stock_quantity, 0) as stock_quantity
            FROM order_items oi
            JOIN books b ON oi.book_id = b.book_id
            JOIN orders o ON oi.order_id = o.order_id
            LEFT JOIN categories c ON b.category_id = c.category_id
            WHERE o.order_date BETWEEN ? AND ?
            AND o.status NOT IN ('cancelled')
            $customerFilter
            GROUP BY b.book_id
            ORDER BY total_revenue DESC
            LIMIT ?";
    
    $params[] = $limit;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    
    return $products;
}

function getCategoryPerformance($startDate, $endDate, $customerId) {
    global $conn;
    
    $customerFilter = "";
    $params = [$startDate, $endDate];
    $types = "ss";
    
    if ($customerId !== 'all') {
        $customerFilter = " AND o.customer_id = ?";
        $params[] = $customerId;
        $types .= "i";
    }
    
    $sql = "SELECT 
                COALESCE(c.category_id, 0) as category_id,
                COALESCE(c.category_name, 'Uncategorized') as category_name,
                COUNT(DISTINCT o.order_id) as order_count,
                COALESCE(SUM(oi.quantity), 0) as total_quantity,
                COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_revenue,
                COUNT(DISTINCT b.book_id) as unique_books
            FROM order_items oi
            JOIN books b ON oi.book_id = b.book_id
            JOIN orders o ON oi.order_id = o.order_id
            LEFT JOIN categories c ON b.category_id = c.category_id
            WHERE o.order_date BETWEEN ? AND ?
            AND o.status NOT IN ('cancelled')
            $customerFilter
            GROUP BY COALESCE(c.category_id, 0), COALESCE(c.category_name, 'Uncategorized')
            ORDER BY total_revenue DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    return $categories;
}

function getCustomerAnalysis($customerId, $startDate, $endDate) {
    global $conn;
    
    // 根据您的customer表结构，使用auto_id作为主键
    $sql = "SELECT 
                c.auto_id as customer_id,
                c.username,
                c.email,
                c.first_name,
                c.last_name,
                CONCAT(c.first_name, ' ', c.last_name) as full_name,
                c.phone,
                c.address,
                COUNT(o.order_id) as total_orders,
                COALESCE(SUM(o.total_amount), 0) as total_spent,
                MIN(o.order_date) as first_order_date,
                MAX(o.order_date) as last_order_date,
                COALESCE(AVG(o.total_amount), 0) as avg_order_value,
                DATEDIFF(COALESCE(MAX(o.order_date), NOW()), COALESCE(MIN(o.order_date), NOW())) as customer_lifetime_days
            FROM customer c
            LEFT JOIN orders o ON c.auto_id = o.customer_id
                AND o.order_date BETWEEN ? AND ?
                AND o.status NOT IN ('cancelled')
            WHERE c.auto_id = ?
            GROUP BY c.auto_id";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('ssi', $startDate, $endDate, $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc() ?: [];
}

function getCustomerList($startDate, $endDate, $limit = 50) {
    global $conn;
    
    $sql = "SELECT 
                c.auto_id as customer_id,
                c.username,
                c.email,
                CONCAT(c.first_name, ' ', c.last_name) as name,
                COUNT(o.order_id) as order_count,
                COALESCE(SUM(o.total_amount), 0) as total_spent,
                MAX(o.order_date) as last_order_date
            FROM customer c
            LEFT JOIN orders o ON c.auto_id = o.customer_id
                AND o.order_date BETWEEN ? AND ?
                AND o.status NOT IN ('cancelled')
            WHERE o.order_id IS NOT NULL
            GROUP BY c.auto_id
            ORDER BY total_spent DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('ssi', $startDate, $endDate, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    
    return $customers;
}

function getCustomerInfo($customerId) {
    global $conn;
    
    $sql = "SELECT 
                auto_id as customer_id,
                username,
                email,
                first_name,
                last_name,
                CONCAT(first_name, ' ', last_name) as full_name,
                phone,
                address,
                city,
                state,
                zip_code,
                country,
                created_at,
                updated_at
            FROM customer 
            WHERE auto_id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc() ?: [];
}

function generateReportHTML($reportData, $reportType, $period, $customerId) {
    $dateRange = $reportData['date_range'];
    $summary = $reportData['summary'];
    $salesTrend = $reportData['sales_trend'];
    $topProducts = $reportData['top_products'];
    $categories = $reportData['category_performance'];
    
    $customerName = '';
    if ($customerId !== 'all' && isset($reportData['customer_info'])) {
        $customerName = $reportData['customer_info']['full_name'] ?? 
                       $reportData['customer_info']['username'] ?? 
                       'Customer #' . $customerId;
    }
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <title>Sales Report - Virtual BookStore</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
            .no-print { margin-bottom: 20px; }
            button { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
            button:hover { background: #2980b9; }
            .header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px solid #2c3e50; }
            h1 { color: #2c3e50; margin-bottom: 10px; }
            .report-info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin: 20px 0; }
            .summary-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; border-top: 4px solid #3498db; }
            .summary-value { font-size: 24px; font-weight: bold; color: #2c3e50; margin-bottom: 5px; }
            .summary-label { color: #666; font-size: 14px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #f5f5f5; border: 1px solid #ddd; padding: 12px; text-align: left; font-weight: bold; color: #2c3e50; }
            td { border: 1px solid #ddd; padding: 10px; }
            .total-row { background: #e8f4f8; font-weight: bold; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .section { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
            .section:last-child { border-bottom: none; }
            @media print {
                .no-print { display: none !important; }
                body { font-size: 12px; margin: 10px; }
                .summary-card { break-inside: avoid; }
                table { break-inside: avoid; }
                button { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="no-print">
            <button onclick="window.print()">🖨️ Print / Save as PDF</button>
            <button onclick="window.close()">Close Window</button>
        </div>
        
        <div class="header">
            <h1>📊 Virtual BookStore Sales Report</h1>
            <div class="report-info">
                <p><strong>Report ID:</strong> ' . $reportData['report_id'] . '</p>
                <p><strong>Period:</strong> ' . ucfirst(str_replace("days", " days", $period)) . 
                   ' (' . date('M d, Y', strtotime($dateRange['start'])) . ' - ' . date('M d, Y', strtotime($dateRange['end'])) . ')</p>
                <p><strong>Generated:</strong> ' . date('F j, Y \a\t g:i A', strtotime($reportData['generated_at'])) . '</p>';
    
    if ($customerName) {
        $html .= '<p><strong>Customer:</strong> ' . htmlspecialchars($customerName) . '</p>';
    }
    
    $html .= '</div>
        </div>
        
        <div class="section">
            <h2>📋 Executive Summary</h2>
            <div class="summary-grid">';
    
    // 根据是否查看特定顾客调整显示的统计卡片
    if ($customerId === 'all') {
        $html .= '<div class="summary-card">
                    <div class="summary-value">RM ' . number_format($summary['total_revenue'] ?? 0, 2) . '</div>
                    <div class="summary-label">Total Revenue</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value">' . ($summary['total_orders'] ?? 0) . '</div>
                    <div class="summary-label">Total Orders</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value">' . ($summary['total_customers'] ?? 0) . '</div>
                    <div class="summary-label">Customers</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value">RM ' . number_format($summary['average_order_value'] ?? 0, 2) . '</div>
                    <div class="summary-label">Avg Order Value</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value">' . ($summary['total_products_sold'] ?? 0) . '</div>
                    <div class="summary-label">Products Sold</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value">' . ($summary['new_customers'] ?? 0) . '</div>
                    <div class="summary-label">New Customers</div>
                </div>';
    } else {
        // 特定顾客的统计卡片
        $html .= '<div class="summary-card">
                    <div class="summary-value">RM ' . number_format($summary['total_revenue'] ?? 0, 2) . '</div>
                    <div class="summary-label">Total Revenue</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value">' . ($summary['total_orders'] ?? 0) . '</div>
                    <div class="summary-label">Total Orders</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value">RM ' . number_format($summary['average_order_value'] ?? 0, 2) . '</div>
                    <div class="summary-label">Avg Order Value</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value">' . ($summary['total_products_sold'] ?? 0) . '</div>
                    <div class="summary-label">Products Sold</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value">' . ($reportData['customer_analysis']['customer_lifetime_days'] ?? 0) . '</div>
                    <div class="summary-label">Customer Lifetime (days)</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value">' . (isset($reportData['customer_analysis']['first_order_date']) ? date('M d, Y', strtotime($reportData['customer_analysis']['first_order_date'])) : 'N/A') . '</div>
                    <div class="summary-label">First Order Date</div>
                </div>';
    }
    
    $html .= '</div>
            
            <h3>Key Metrics</h3>
            <table>
                <tr>
                    <td>Daily Average Revenue</td>
                    <td class="text-right">RM ' . number_format($summary['daily_average_revenue'] ?? 0, 2) . '</td>
                </tr>
                <tr>
                    <td>Daily Average Orders</td>
                    <td class="text-right">' . number_format($summary['daily_average_orders'] ?? 0, 1) . '</td>
                </tr>';
    
    if ($customerId === 'all') {
        $html .= '<tr>
                    <td>Best Performing Day</td>
                    <td class="text-right">' . ($summary['best_day'] ?? 'N/A') . ' (RM ' . number_format($summary['best_day_revenue'] ?? 0, 2) . ')</td>
                </tr>';
    }
    
    $html .= '<tr>
                    <td>Days in Period</td>
                    <td class="text-right">' . ($reportData['total_days'] ?? 0) . ' days</td>
                </tr>
            </table>
        </div>';
    
    // Sales Trend Table - 只有当有销售趋势数据时才显示
    if (!empty($salesTrend)) {
        $html .= '
        <div class="section">
            <h2>📈 Sales Trend (' . count($salesTrend) . ' days)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-right">Orders</th>
                        <th class="text-right">Revenue (RM)</th>
                        <th class="text-right">Items Sold</th>';
        
        // 只有当查看所有顾客时才显示顾客列
        if ($customerId === 'all') {
            $html .= '<th class="text-right">Customers</th>';
        }
        
        $html .= '</tr>
                </thead>
                <tbody>';
        
        $totalOrders = 0;
        $totalRevenue = 0;
        $totalItems = 0;
        $totalCustomers = 0;
        
        foreach ($salesTrend as $day) {
            $totalOrders += $day['order_count'];
            $totalRevenue += $day['daily_revenue'];
            $totalItems += $day['items_sold'];
            $totalCustomers += $day['unique_customers'];
            
            $html .= '
                <tr>
                    <td>' . date('M d, Y', strtotime($day['date'])) . '</td>
                    <td class="text-right">' . $day['order_count'] . '</td>
                    <td class="text-right">' . number_format($day['daily_revenue'], 2) . '</td>
                    <td class="text-right">' . $day['items_sold'] . '</td>';
            
            if ($customerId === 'all') {
                $html .= '<td class="text-right">' . $day['unique_customers'] . '</td>';
            }
            
            $html .= '</tr>';
        }
        
        $html .= '
                <tr class="total-row">
                    <td><strong>TOTAL</strong></td>
                    <td class="text-right"><strong>' . $totalOrders . '</strong></td>
                    <td class="text-right"><strong>RM ' . number_format($totalRevenue, 2) . '</strong></td>
                    <td class="text-right"><strong>' . $totalItems . '</strong></td>';
        
        if ($customerId === 'all') {
            $html .= '<td class="text-right"><strong>' . $totalCustomers . '</strong></td>';
        }
        
        $html .= '</tr>
            </tbody>
        </table>
        </div>';
    }
    
    // Top Products Table - 只有当有产品销售数据时才显示
    if (!empty($topProducts)) {
        $html .= '
        <div class="section">
            <h2>🔥 ' . ($customerId === 'all' ? 'Top Selling Books' : 'Books Purchased') . ' (Top ' . count($topProducts) . ')</h2>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Book Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Revenue (RM)</th>
                        <th class="text-right">Orders</th>
                    </tr>
                </thead>
                <tbody>';
        
        $counter = 1;
        $totalProductRevenue = 0;
        $totalProductQuantity = 0;
        
        foreach ($topProducts as $product) {
            $totalProductRevenue += $product['total_revenue'];
            $totalProductQuantity += $product['total_quantity'];
            
            $html .= '
                <tr>
                    <td>' . $counter . '</td>
                    <td>' . htmlspecialchars(substr($product['title'], 0, 40)) . (strlen($product['title']) > 40 ? '...' : '') . '</td>
                    <td>' . htmlspecialchars(substr($product['author'], 0, 20)) . (strlen($product['author']) > 20 ? '...' : '') . '</td>
                    <td>' . htmlspecialchars($product['category_name'] ?? 'Uncategorized') . '</td>
                    <td class="text-right">' . $product['total_quantity'] . '</td>
                    <td class="text-right">' . number_format($product['total_revenue'], 2) . '</td>
                    <td class="text-right">' . $product['order_count'] . '</td>
                </tr>';
            $counter++;
        }
        
        $html .= '
                <tr class="total-row">
                    <td colspan="4"><strong>TOTAL</strong></td>
                    <td class="text-right"><strong>' . $totalProductQuantity . '</strong></td>
                    <td class="text-right"><strong>RM ' . number_format($totalProductRevenue, 2) . '</strong></td>
                    <td class="text-right"><strong>' . count($topProducts) . '</strong></td>
                </tr>
            </tbody>
        </table>
        </div>';
    }
    
    // Category Performance Table - 只有当有分类数据时才显示
    if (!empty($categories)) {
        $html .= '
        <div class="section">
            <h2>🏷️ ' . ($customerId === 'all' ? 'Revenue by Category' : 'Categories Purchased') . '</h2>
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="text-right">Orders</th>
                        <th class="text-right">Quantity</th>
                        <th class="text-right">Revenue (RM)</th>';
        
        if ($customerId === 'all') {
            $html .= '<th class="text-right">% of Total</th>';
        }
        
        $html .= '<th class="text-right">Unique Books</th>
                    </tr>
                </thead>
                <tbody>';
        
        $totalCategoryRevenue = array_sum(array_column($categories, 'total_revenue'));
        $totalCategoryOrders = array_sum(array_column($categories, 'order_count'));
        $totalCategoryQuantity = array_sum(array_column($categories, 'total_quantity'));
        $totalUniqueBooks = array_sum(array_column($categories, 'unique_books'));
        
        foreach ($categories as $category) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($category['category_name']) . '</td>
                    <td class="text-right">' . $category['order_count'] . '</td>
                    <td class="text-right">' . $category['total_quantity'] . '</td>
                    <td class="text-right">' . number_format($category['total_revenue'], 2) . '</td>';
            
            if ($customerId === 'all') {
                $percentage = $totalCategoryRevenue > 0 ? ($category['total_revenue'] / $totalCategoryRevenue * 100) : 0;
                $html .= '<td class="text-right">' . number_format($percentage, 1) . '%</td>';
            }
            
            $html .= '<td class="text-right">' . $category['unique_books'] . '</td>
                </tr>';
        }
        
        $html .= '
                <tr class="total-row">
                    <td><strong>TOTAL</strong></td>
                    <td class="text-right"><strong>' . $totalCategoryOrders . '</strong></td>
                    <td class="text-right"><strong>' . $totalCategoryQuantity . '</strong></td>
                    <td class="text-right"><strong>RM ' . number_format($totalCategoryRevenue, 2) . '</strong></td>';
        
        if ($customerId === 'all') {
            $html .= '<td class="text-right"><strong>100%</strong></td>';
        }
        
        $html .= '<td class="text-right"><strong>' . $totalUniqueBooks . '</strong></td>
                </tr>
            </tbody>
        </table>
        </div>';
    }
    
    // Customer List Table - 只有当查看所有顾客时才显示
    if ($customerId === 'all' && !empty($reportData['customer_list'])) {
        $html .= '
        <div class="section">
            <h2>👥 Top Customers</h2>
            <table>
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th class="text-right">Orders</th>
                        <th class="text-right">Total Spent (RM)</th>
                        <th>Last Order</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($reportData['customer_list'] as $customer) {
            $html .= '
                <tr>
                    <td>' . $customer['customer_id'] . '</td>
                    <td>' . htmlspecialchars($customer['name']) . '</td>
                    <td>' . htmlspecialchars($customer['email']) . '</td>
                    <td class="text-right">' . $customer['order_count'] . '</td>
                    <td class="text-right">' . number_format($customer['total_spent'], 2) . '</td>
                    <td>' . ($customer['last_order_date'] ? date('M d, Y', strtotime($customer['last_order_date'])) : 'N/A') . '</td>
                </tr>';
        }
        
        $html .= '
            </tbody>
        </table>
        </div>';
    }
    
    // Customer Information (if specific customer)
    if ($customerId !== 'all' && !empty($reportData['customer_analysis'])) {
        $customer = $reportData['customer_analysis'];
        
        $html .= '
        <div class="section">
            <h2>👥 Customer Analysis</h2>
            
            <h3>Customer Information</h3>
            <table>
                <tr>
                    <td>Customer ID</td>
                    <td>' . ($customer['customer_id'] ?? '') . '</td>
                </tr>
                <tr>
                    <td>Username</td>
                    <td>' . htmlspecialchars($customer['username'] ?? '') . '</td>
                </tr>';
        
        if (isset($customer['full_name']) && $customer['full_name']) {
            $html .= '<tr>
                    <td>Full Name</td>
                    <td>' . htmlspecialchars($customer['full_name']) . '</td>
                </tr>';
        }
        
        $html .= '<tr>
                    <td>Email</td>
                    <td>' . htmlspecialchars($customer['email'] ?? '') . '</td>
                </tr>';
        
        if (isset($customer['phone']) && $customer['phone']) {
            $html .= '<tr>
                    <td>Phone</td>
                    <td>' . htmlspecialchars($customer['phone']) . '</td>
                </tr>';
        }
        
        if (isset($customer['address']) && $customer['address']) {
            $html .= '<tr>
                    <td>Address</td>
                    <td>' . htmlspecialchars($customer['address']) . '</td>
                </tr>';
        }
        
        if (isset($customer['first_order_date']) && $customer['first_order_date']) {
            $html .= '<tr>
                    <td>First Order</td>
                    <td>' . date('M d, Y', strtotime($customer['first_order_date'])) . '</td>
                </tr>';
        }
        
        if (isset($customer['last_order_date']) && $customer['last_order_date']) {
            $html .= '<tr>
                    <td>Last Order</td>
                    <td>' . date('M d, Y', strtotime($customer['last_order_date'])) . '</td>
                </tr>';
        }
        
        $html .= '</table>
            
            <h3>Purchase Summary</h3>
            <table>
                <tr>
                    <td>Total Orders</td>
                    <td class="text-right">' . ($customer['total_orders'] ?? 0) . '</td>
                </tr>
                <tr>
                    <td>Total Spent</td>
                    <td class="text-right">RM ' . number_format($customer['total_spent'] ?? 0, 2) . '</td>
                </tr>
                <tr>
                    <td>Average Order Value</td>
                    <td class="text-right">RM ' . number_format($customer['avg_order_value'] ?? 0, 2) . '</td>
                </tr>
                <tr>
                    <td>Customer Lifetime</td>
                    <td class="text-right">' . ($customer['customer_lifetime_days'] ?? 0) . ' days</td>
                </tr>
            </table>
        </div>';
    }
    
    // Footer
    $html .= '
        <div style="margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666;">
            <p><strong>Virtual BookStore Management System</strong></p>
            <p>Report ID: ' . $reportData['report_id'] . ' | Generated: ' . $reportData['generated_at'] . '</p>';
    
    if ($customerId !== 'all') {
        $html .= '<p><strong>Customer Specific Report</strong> - Data filtered for customer ID: ' . $customerId . '</p>';
    }
    
    $html .= '<p>Confidential - For Internal Use Only</p>
        </div>
        
        <script>
            // Auto-print after 1 second
            setTimeout(function() {
                window.print();
            }, 1000);
        </script>
    </body>
    </html>';
    
    return $html;
}
?>
