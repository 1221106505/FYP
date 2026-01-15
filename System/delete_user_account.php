<?php
// delete_user_account.php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Unauthorized access'
];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// 接收确认数据
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['confirm']) || $data['confirm'] !== true) {
    $response['message'] = 'Confirmation required';
    echo json_encode($response);
    exit();
}

if (!isset($data['password'])) {
    $response['message'] = 'Password required for deletion';
    echo json_encode($response);
    exit();
}

try {
    // 验证密码
    if ($user_type === 'admin') {
        $check_query = "SELECT password_hash FROM admin WHERE auto_id = ?";
    } else {
        $check_query = "SELECT password_hash FROM customer WHERE auto_id = ?";
    }
    
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception('User not found');
    }
    
    $user = $check_result->fetch_assoc();
    
    if (!password_verify($data['password'], $user['password_hash'])) {
        throw new Exception('Incorrect password');
    }
    
    // 开始事务
    $conn->begin_transaction();
    
    if ($user_type === 'admin') {
        // 删除管理员
        // 注意：管理员删除可能需要额外检查，这里简单实现
        if ($user_id <= 2) { // 保护前两个管理员账户
            throw new Exception('Cannot delete this admin account');
        }
        
        $delete_query = "DELETE FROM admin WHERE auto_id = ?";
    } else {
        // 删除客户及相关数据
        // 先删除购物车
        $delete_cart_query = "DELETE FROM cart WHERE customer_id = ?";
        $stmt1 = $conn->prepare($delete_cart_query);
        $stmt1->bind_param("i", $user_id);
        $stmt1->execute();
        $stmt1->close();
        
        // 删除预购订单
        $delete_pre_orders_query = "DELETE FROM pre_orders WHERE customer_id = ?";
        $stmt2 = $conn->prepare($delete_pre_orders_query);
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();
        $stmt2->close();
        
        // 最后删除客户
        $delete_query = "DELETE FROM customer WHERE auto_id = ?";
    }
    
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("i", $user_id);
    
    if ($delete_stmt->execute()) {
        $conn->commit();
        
        // 清除session
        session_destroy();
        
        $response['success'] = true;
        $response['message'] = 'Account deleted successfully';
    } else {
        throw new Exception('Failed to delete account');
    }
    
    $delete_stmt->close();
    
} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>