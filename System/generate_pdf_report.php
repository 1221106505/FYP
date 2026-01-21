<?php
// generate_pdf_report.php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');

// 检查权限
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$reportType = $_POST['report_type'] ?? 'all';
$period = $_POST['period'] ?? '30days';

// 获取销售数据
$data = fetchSalesData($conn, $period);

// 生成 HTML 报告
$htmlContent = generateHTMLReport($data, $reportType, $period);

// 提供下载或直接输出
if (isset($_POST['download'])) {
    // 生成唯一的文件名
    $filename = "sales_report_" . date('Y-m-d_H-i-s') . ".html";
    
    // 设置下载头
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($htmlContent));
    
    echo $htmlContent;
    exit;
} else {
    // 直接显示在浏览器中
    echo $htmlContent;
    exit;
}

function fetchSalesData($conn, $period) {
    // 根据周期设置日期范围
    $endDate = date('Y-m-d');
    
    switch($period) {
        case '7days':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            break;
        case '90days':
            $startDate = date('Y-m-d', strtotime('-90 days'));
            break;
        case 'year':
            $startDate = date('Y-m-d', strtotime('-1 year'));
            break;
        case '30days':
        default:
            $startDate = date('Y-m-d', strtotime('-30 days'));
            break;
    }
    
    $data = [
        'summary' => [],
        'sales_trend' => [],
        'top_books' => [],
        'category_revenue' => []
    ];
    
    // 获取统计摘要
    $query = "SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_revenue,
                COUNT(DISTINCT customer_id) as total_customers
              FROM `order`
              WHERE order_date BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    
    $data['summary'] = [
        'total_orders' => $summary['total_orders'] ?? 0,
        'total_revenue' => $summary['total_revenue'] ?? 0,
        'total_customers' => $summary['total_customers'] ?? 0,
        'avg_order_value' => ($summary['total_orders'] > 0) ? 
            ($summary['total_revenue'] / $summary['total_orders']) : 0
    ];
    
    // 获取销售趋势（按天）
    $query = "SELECT 
                DATE(order_date) as date,
                COUNT(*) as order_count,
                SUM(total_amount) as daily_revenue
              FROM `order`
              WHERE order_date BETWEEN ? AND ?
              GROUP BY DATE(order_date)
              ORDER BY date";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $data['sales_trend'][] = $row;
    }
    
    // 获取热销书籍
    $query = "SELECT 
                b.book_id,
                b.title,
                b.author,
                c.category_name,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.quantity * oi.unit_price) as total_revenue
              FROM order_items oi
              JOIN book b ON oi.book_id = b.book_id
              JOIN category c ON b.category_id = c.category_id
              JOIN `order` o ON oi.order_id = o.order_id
              WHERE o.order_date BETWEEN ? AND ?
              GROUP BY b.book_id
              ORDER BY total_revenue DESC
              LIMIT 10";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rank = 1;
    while ($row = $result->fetch_assoc()) {
        $data['top_books'][] = array_merge($row, ['rank' => $rank++]);
    }
    
    // 获取分类收入
    $query = "SELECT 
                c.category_name,
                COUNT(DISTINCT o.order_id) as order_count,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.quantity * oi.unit_price) as total_revenue
              FROM order_items oi
              JOIN book b ON oi.book_id = b.book_id
              JOIN category c ON b.category_id = c.category_id
              JOIN `order` o ON oi.order_id = o.order_id
              WHERE o.order_date BETWEEN ? AND ?
              GROUP BY c.category_id
              ORDER BY total_revenue DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $data['category_revenue'][] = $row;
    }
    
    return $data;
}

function generateHTMLReport($data, $reportType, $period) {
    $periodText = getPeriodText($period);
    $currentDate = date('F j, Y, g:i a');
    $reportId = 'SR-' . date('YmdHis') . '-' . rand(1000, 9999);
    
    $html = "<!DOCTYPE html>
    <html>
    <head>
        <title>Sales Report - Virtual BookStore</title>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; color: #333; }
            .header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #2c3e50; padding-bottom: 20px; }
            .title { font-size: 28px; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
            .subtitle { font-size: 18px; color: #666; margin-bottom: 15px; }
            .metadata { font-size: 12px; color: #999; }
            .section { margin: 30px 0; page-break-inside: avoid; }
            .section-title { background: #2c3e50; color: white; padding: 12px 15px; font-size: 18px; margin-bottom: 20px; border-radius: 4px; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th { background: #f5f5f5; border: 1px solid #ddd; padding: 12px; text-align: left; font-weight: bold; }
            td { border: 1px solid #ddd; padding: 10px; }
            .stat-box { display: inline-block; background: #f8f9fa; border: 1px solid #ddd; border-radius: 6px; padding: 20px; margin: 10px; min-width: 180px; text-align: center; }
            .stat-value { font-size: 28px; font-weight: bold; color: #2c3e50; }
            .stat-label { font-size: 14px; color: #666; margin-top: 8px; }
            .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #999; text-align: center; }
            @media print {
                body { margin: 20px; }
                .no-print { display: none; }
                .section { page-break-inside: avoid; }
                .header { border-bottom: 2px solid #000; }
            }
        </style>
    </head>
    <body>
        <div class='header'>
            <div class='title'>Virtual BookStore - Sales Report</div>
            <div class='subtitle'>" . getReportTypeText($reportType) . " - $periodText</div>
            <div class='metadata'>
                Generated on: $currentDate | Report ID: $reportId<br>
                Confidential - For Internal Use Only
            </div>
        </div>";
    
    switch($reportType) {
        case 'summary':
            $html .= generateSummarySection($data['summary']);
            break;
        case 'sales_trend':
            $html .= generateSalesTrendSection($data['sales_trend'], $periodText);
            break;
        case 'top_books':
            $html .= generateTopBooksSection($data['top_books']);
            break;
        case 'category':
            $html .= generateCategorySection($data['category_revenue']);
            break;
        case 'all':
        default:
            $html .= generateSummarySection($data['summary']);
            $html .= generateSalesTrendSection($data['sales_trend'], $periodText);
            $html .= generateTopBooksSection($data['top_books']);
            $html .= generateCategorySection($data['category_revenue']);
            break;
    }
    
    $html .= "
        <div class='footer'>
            <p>Virtual BookStore Management System | www.virtualbookstore.com</p>
            <p>This report is generated automatically. For questions, contact: admin@virtualbookstore.com</p>
            <p>Page generated at: $currentDate</p>
        </div>
        
        <div class='no-print' style='margin-top: 40px; text-align: center;'>
            <button onclick='window.print()' style='padding: 10px 20px; background: #2c3e50; color: white; border: none; border-radius: 4px; cursor: pointer;'>
                🖨️ Print Report
            </button>
            <button onclick='window.close()' style='padding: 10px 20px; background: #666; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;'>
                ❌ Close
            </button>
        </div>
        
        <script>
            window.onload = function() {
                // 自动打印（可选）
                // window.print();
            };
        </script>
    </body>
    </html>";
    
    return $html;
}

function generateSummarySection($summary) {
    $html = "
    <div class='section'>
        <div class='section-title'>📊 Executive Summary</div>
        <div style='text-align: center; margin: 25px 0;'>
            <div class='stat-box'>
                <div class='stat-value'>" . number_format($summary['total_orders']) . "</div>
                <div class='stat-label'>Total Orders</div>
            </div>
            <div class='stat-box'>
                <div class='stat-value'>RM " . number_format($summary['total_revenue'], 2) . "</div>
                <div class='stat-label'>Total Revenue</div>
            </div>
            <div class='stat-box'>
                <div class='stat-value'>" . number_format($summary['total_customers']) . "</div>
                <div class='stat-label'>Customers</div>
            </div>
            <div class='stat-box'>
                <div class='stat-value'>RM " . number_format($summary['avg_order_value'], 2) . "</div>
                <div class='stat-label'>Avg Order Value</div>
            </div>
        </div>
        
        <div style='margin-top: 30px;'>
            <h3 style='color: #2c3e50;'>Performance Overview</h3>
            <p>This report summarizes the sales performance for the selected period. Key metrics include:</p>
            <table>
                <tr>
                    <td style='width: 40%; font-weight: bold;'>Total Revenue:</td>
                    <td>RM " . number_format($summary['total_revenue'], 2) . "</td>
                </tr>
                <tr>
                    <td style='font-weight: bold;'>Order Volume:</td>
                    <td>" . number_format($summary['total_orders']) . " orders</td>
                </tr>
                <tr>
                    <td style='font-weight: bold;'>Customer Base:</td>
                    <td>" . number_format($summary['total_customers']) . " customers</td>
                </tr>
                <tr>
                    <td style='font-weight: bold;'>Average Order Value:</td>
                    <td>RM " . number_format($summary['avg_order_value'], 2) . "</td>
                </tr>
                <tr>
                    <td style='font-weight: bold;'>Average Revenue per Customer:</td>
                    <td>RM " . ($summary['total_customers'] > 0 ? 
                        number_format($summary['total_revenue'] / $summary['total_customers'], 2) : 0) . "</td>
                </tr>
            </table>
        </div>
    </div>";
    
    return $html;
}

function generateSalesTrendSection($trendData, $periodText) {
    $html = "
    <div class='section'>
        <div class='section-title'>📈 Sales Trend Analysis</div>
        <p><strong>Period:</strong> $periodText</p>";
    
    if (empty($trendData)) {
        $html .= "<p style='color: #666; font-style: italic;'>No sales data available for this period.</p>";
    } else {
        $totalRevenue = 0;
        $totalOrders = 0;
        
        $tableRows = '';
        foreach ($trendData as $day) {
            $totalRevenue += $day['daily_revenue'];
            $totalOrders += $day['order_count'];
            $tableRows .= "
            <tr>
                <td>" . date('M j, Y', strtotime($day['date'])) . "</td>
                <td>RM " . number_format($day['daily_revenue'], 2) . "</td>
                <td>" . $day['order_count'] . "</td>
                <td>RM " . number_format($day['order_count'] > 0 ? 
                    ($day['daily_revenue'] / $day['order_count']) : 0, 2) . "</td>
            </tr>";
        }
        
        $html .= "
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Revenue (RM)</th>
                    <th>Orders</th>
                    <th>Avg Revenue per Order</th>
                </tr>
            </thead>
            <tbody>
                $tableRows
                <tr style='font-weight: bold; background: #f5f5f5;'>
                    <td>TOTAL</td>
                    <td>RM " . number_format($totalRevenue, 2) . "</td>
                    <td>$totalOrders</td>
                    <td>RM " . number_format($totalOrders > 0 ? 
                        ($totalRevenue / $totalOrders) : 0, 2) . "</td>
                </tr>
            </tbody>
        </table>
        
        <div style='margin-top: 25px;'>
            <h4>Trend Analysis:</h4>
            <ul>
                <li><strong>Average daily revenue:</strong> RM " . 
                    number_format(count($trendData) > 0 ? ($totalRevenue / count($trendData)) : 0, 2) . "</li>
                <li><strong>Average daily orders:</strong> " . 
                    number_format(count($trendData) > 0 ? ($totalOrders / count($trendData)) : 0, 1) . "</li>
                <li><strong>Best performing day:</strong> " . 
                    (count($trendData) > 0 ? date('M j, Y', strtotime(max($trendData)['date'])) : 'N/A') . " 
                    (RM " . (count($trendData) > 0 ? number_format(max(array_column($trendData, 'daily_revenue')), 2) : 0) . ")</li>
            </ul>
        </div>";
    }
    
    $html .= "</div>";
    return $html;
}

function getPeriodText($period) {
    $texts = [
        '7days' => 'Last 7 Days',
        '30days' => 'Last 30 Days',
        '90days' => 'Last 90 Days',
        'year' => 'Last Year'
    ];
    return $texts[$period] ?? $period;
}

function getReportTypeText($type) {
    $texts = [
        'all' => 'Full Report',
        'summary' => 'Executive Summary',
        'sales_trend' => 'Sales Trend Analysis',
        'top_books' => 'Top Selling Books',
        'category' => 'Category Revenue Analysis'
    ];
    return $texts[$type] ?? $type;
}

// 类似地添加 generateTopBooksSection() 和 generateCategorySection() 函数
?>