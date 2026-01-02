<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../include/database.php';

try {
    // 获取日期范围参数
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    
    // 总体统计
    $overall_sql = "SELECT 
                        COUNT(*) as total_payments,
                        SUM(amount) as total_amount,
                        AVG(amount) as average_amount,
                        MIN(amount) as min_amount,
                        MAX(amount) as max_amount,
                        COUNT(DISTINCT customer_id) as unique_customers
                    FROM payments 
                    WHERE DATE(created_at) BETWEEN ? AND ?";
    
    $overall_stmt = $conn->prepare($overall_sql);
    $overall_stmt->bind_param("ss", $start_date, $end_date);
    $overall_stmt->execute();
    $overall_result = $overall_stmt->get_result();
    $overall_stats = $overall_result->fetch_assoc();
    
    // 按状态统计
    $status_sql = "SELECT 
                        payment_status,
                        COUNT(*) as count,
                        SUM(amount) as total_amount
                    FROM payments 
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY payment_status";
    
    $status_stmt = $conn->prepare($status_sql);
    $status_stmt->bind_param("ss", $start_date, $end_date);
    $status_stmt->execute();
    $status_result = $status_stmt->get_result();
    
    $status_stats = [];
    while ($row = $status_result->fetch_assoc()) {
        $status_stats[$row['payment_status']] = $row;
    }
    
    // 按支付方式统计
    $method_sql = "SELECT 
                        payment_method,
                        COUNT(*) as count,
                        SUM(amount) as total_amount
                    FROM payments 
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY payment_method";
    
    $method_stmt = $conn->prepare($method_sql);
    $method_stmt->bind_param("ss", $start_date, $end_date);
    $method_stmt->execute();
    $method_result = $method_stmt->get_result();
    
    $method_stats = [];
    while ($row = $method_result->fetch_assoc()) {
        $method_stats[$row['payment_method']] = $row;
    }
    
    // 每日趋势
    $daily_sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as payment_count,
                    SUM(amount) as daily_total
                  FROM payments 
                  WHERE DATE(created_at) BETWEEN ? AND ?
                  GROUP BY DATE(created_at)
                  ORDER BY date";
    
    $daily_stmt = $conn->prepare($daily_sql);
    $daily_stmt->bind_param("ss", $start_date, $end_date);
    $daily_stmt->execute();
    $daily_result = $daily_stmt->get_result();
    
    $daily_trends = [];
    while ($row = $daily_result->fetch_assoc()) {
        $daily_trends[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'period' => [
            'start_date' => $start_date,
            'end_date' => $end_date
        ],
        'overall_stats' => [
            'total_payments' => $overall_stats['total_payments'] ?? 0,
            'total_amount' => floatval($overall_stats['total_amount'] ?? 0),
            'average_amount' => floatval($overall_stats['average_amount'] ?? 0),
            'min_amount' => floatval($overall_stats['min_amount'] ?? 0),
            'max_amount' => floatval($overall_stats['max_amount'] ?? 0),
            'unique_customers' => $overall_stats['unique_customers'] ?? 0
        ],
        'status_stats' => $status_stats,
        'method_stats' => $method_stats,
        'daily_trends' => $daily_trends
    ]);
    
    $overall_stmt->close();
    $status_stmt->close();
    $method_stmt->close();
    $daily_stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>