<?php
// update_orders.php - 自动更新订单状态
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// 包含数据库配置文件
require_once '../include/database.php';

// 禁用错误输出到浏览器
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// 获取POST参数（可选）
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$force_update = isset($data['force']) ? boolval($data['force']) : false;

try {
    // 开始事务
    $conn->autocommit(false);
    
    $updated_orders = [];
    
    // 1. 找出所有状态为 pending 的订单
    $pending_sql = "SELECT o.order_id, o.customer_id, o.total_amount, 
                           p.payment_id, p.payment_status
                    FROM orders o
                    LEFT JOIN payments p ON o.order_id = p.order_id
                    WHERE o.status = 'pending' 
                    AND p.payment_status = 'completed'";
    
    $pending_stmt = $conn->prepare($pending_sql);
    $pending_stmt->execute();
    $pending_result = $pending_stmt->get_result();
    
    while ($order = $pending_result->fetch_assoc()) {
        // 更新订单状态为 confirmed
        $update_sql = "UPDATE orders SET status = 'confirmed', updated_at = NOW() WHERE order_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $order['order_id']);
        
        if ($update_stmt->execute()) {
            $updated_orders[] = [
                'order_id' => $order['order_id'],
                'old_status' => 'pending',
                'new_status' => 'confirmed',
                'payment_status' => $order['payment_status']
            ];
        }
        $update_stmt->close();
    }
    $pending_stmt->close();
    
    // 2. 更新已确认但未发货的订单（可选）
    if ($force_update) {
        // 找出已确认超过24小时的订单，更新为shipped
        $confirmed_sql = "SELECT order_id 
                          FROM orders 
                          WHERE status = 'confirmed' 
                          AND order_date < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $confirmed_stmt = $conn->prepare($confirmed_sql);
        $confirmed_stmt->execute();
        $confirmed_result = $confirmed_stmt->get_result();
        
        while ($order = $confirmed_result->fetch_assoc()) {
            $update_sql = "UPDATE orders SET status = 'shipped', updated_at = NOW() WHERE order_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $order['order_id']);
            
            if ($update_stmt->execute()) {
                $updated_orders[] = [
                    'order_id' => $order['order_id'],
                    'old_status' => 'confirmed',
                    'new_status' => 'shipped'
                ];
            }
            $update_stmt->close();
        }
        $confirmed_stmt->close();
    }
    
    // 提交事务
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Orders updated successfully',
        'updated_count' => count($updated_orders),
        'updated_orders' => $updated_orders
    ]);
    
} catch (Exception $e) {
    // 回滚事务
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'error' => 'Update failed: ' . $e->getMessage()
    ]);
}

// 恢复自动提交
$conn->autocommit(true);
$conn->close();
?>