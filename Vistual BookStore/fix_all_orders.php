<?php
// fix_all_orders.php - 修复所有未正确更新的订单
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// 包含数据库配置文件
require_once '../include/database.php';

// 禁用错误输出
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

try {
    $conn->autocommit(false);
    
    $fixes = [];
    $total_fixed = 0;
    
    // 修复1：确保所有已付款的订单状态为confirmed
    $fix1_sql = "UPDATE orders o
                 JOIN payments p ON o.order_id = p.order_id
                 SET o.status = 'confirmed', 
                     o.updated_at = NOW()
                 WHERE p.payment_status = 'completed' 
                 AND o.status != 'confirmed'";
    
    $fix1_stmt = $conn->prepare($fix1_sql);
    if ($fix1_stmt->execute()) {
        $affected_rows = $conn->affected_rows;
        $fixes[] = [
            'fix' => 'pending_to_confirmed',
            'affected_rows' => $affected_rows
        ];
        $total_fixed += $affected_rows;
    }
    $fix1_stmt->close();
    
    // 修复2：确保订单项和订单关联正确
    $fix2_sql = "INSERT IGNORE INTO order_items (order_id, book_id, quantity, unit_price, subtotal)
                 SELECT o.order_id, c.book_id, c.quantity, b.price, (c.quantity * b.price)
                 FROM orders o
                 JOIN cart c ON o.customer_id = c.customer_id
                 JOIN books b ON c.book_id = b.book_id
                 LEFT JOIN order_items oi ON o.order_id = oi.order_id AND c.book_id = oi.book_id
                 WHERE oi.order_item_id IS NULL
                 AND o.status IN ('confirmed', 'shipped', 'delivered')";
    
    $fix2_stmt = $conn->prepare($fix2_sql);
    if ($fix2_stmt->execute()) {
        $affected_rows = $conn->affected_rows;
        $fixes[] = [
            'fix' => 'missing_order_items',
            'affected_rows' => $affected_rows
        ];
        $total_fixed += $affected_rows;
    }
    $fix2_stmt->close();
    
    // 修复3：更新库存（如果订单项存在但库存未更新）
    $fix3_sql = "UPDATE books b
                 JOIN (
                     SELECT oi.book_id, SUM(oi.quantity) as total_sold
                     FROM order_items oi
                     JOIN orders o ON oi.order_id = o.order_id
                     WHERE o.status IN ('confirmed', 'shipped', 'delivered')
                     GROUP BY oi.book_id
                 ) sales ON b.book_id = sales.book_id
                 SET b.stock_quantity = GREATEST(0, b.stock_quantity - sales.total_sold),
                     b.total_sales = b.total_sales + sales.total_sold,
                     b.updated_at = NOW()";
    
    $fix3_stmt = $conn->prepare($fix3_sql);
    if ($fix3_stmt->execute()) {
        $affected_rows = $conn->affected_rows;
        $fixes[] = [
            'fix' => 'update_inventory',
            'affected_rows' => $affected_rows
        ];
        $total_fixed += $affected_rows;
    }
    $fix3_stmt->close();
    
    // 修复4：清除已下单的购物车项目
    $fix4_sql = "DELETE c FROM cart c
                 JOIN orders o ON c.customer_id = o.customer_id
                 JOIN order_items oi ON o.order_id = oi.order_id AND c.book_id = oi.book_id
                 WHERE o.status IN ('confirmed', 'shipped', 'delivered')
                 AND c.book_id = oi.book_id";
    
    $fix4_stmt = $conn->prepare($fix4_sql);
    if ($fix4_stmt->execute()) {
        $affected_rows = $conn->affected_rows;
        $fixes[] = [
            'fix' => 'cleanup_cart',
            'affected_rows' => $affected_rows
        ];
        $total_fixed += $affected_rows;
    }
    $fix4_stmt->close();
    
    // 提交事务
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'All fixes applied successfully',
        'total_fixed' => $total_fixed,
        'fixes_applied' => $fixes
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'error' => 'Fix failed: ' . $e->getMessage()
    ]);
}

$conn->autocommit(true);
$conn->close();
?>