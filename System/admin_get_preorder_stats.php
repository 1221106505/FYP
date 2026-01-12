<?php
// admin_get_preorder_stats.php
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

try {
    // 获取预购订单统计
    $sql = "SELECT 
                status,
                COUNT(*) as count,
                SUM(total_amount) as total_amount,
                SUM(quantity) as total_quantity
            FROM pre_orders 
            GROUP BY status";
    
    $result = $conn->query($sql);
    
    $stats = [
        'pending' => 0,
        'confirmed' => 0,
        'shipped' => 0,
        'delivered' => 0,
        'cancelled' => 0,
        'total' => 0,
        'total_amount' => 0,
        'total_quantity' => 0
    ];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $status = $row['status'];
            if (isset($stats[$status])) {
                $stats[$status] = intval($row['count']);
            }
            $stats['total'] += intval($row['count']);
            $stats['total_amount'] += floatval($row['total_amount']);
            $stats['total_quantity'] += intval($row['total_quantity']);
        }
        
        // 获取今天的新预购订单
        $today_sql = "SELECT COUNT(*) as today_count FROM pre_orders 
                     WHERE DATE(order_date) = CURDATE()";
        $today_result = $conn->query($today_sql);
        if ($today_result) {
            $today_row = $today_result->fetch_assoc();
            $stats['today'] = intval($today_row['today_count']);
        }
        
        // 获取本周的新预购订单
        $week_sql = "SELECT COUNT(*) as week_count FROM pre_orders 
                    WHERE YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1)";
        $week_result = $conn->query($week_sql);
        if ($week_result) {
            $week_row = $week_result->fetch_assoc();
            $stats['this_week'] = intval($week_row['week_count']);
        }
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to get statistics'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>