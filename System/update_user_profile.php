<?php
// update_user_profile.php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Unauthorized access'
];

if (!isset($_SESSION['user_id'])) {
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// 获取原始POST数据
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    $response['message'] = 'Invalid data format';
    echo json_encode($response);
    exit();
}

try {
    // 如果是更改密码的请求
    if (isset($data['change_password']) && $data['change_password'] === true) {
        if (!isset($data['old_password']) || !isset($data['new_password'])) {
            throw new Exception('Password change requires old and new password');
        }
        
        // 验证旧密码
        if ($user_type === 'admin') {
            $check_query = "SELECT password_hash FROM admin WHERE auto_id = ?";
        } else {
            $check_query = "SELECT password_hash FROM customer WHERE auto_id = ?";
        }
        
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $user = $check_result->fetch_assoc();
            
            if (!password_verify($data['old_password'], $user['password_hash'])) {
                throw new Exception('Old password is incorrect');
            }
            
            // 更新密码
            $new_password_hash = password_hash($data['new_password'], PASSWORD_DEFAULT);
            $update_password_query = ($user_type === 'admin') 
                ? "UPDATE admin SET password_hash = ?, updated_at = NOW() WHERE auto_id = ?"
                : "UPDATE customer SET password_hash = ?, updated_at = NOW() WHERE auto_id = ?";
            
            $update_stmt = $conn->prepare($update_password_query);
            $update_stmt->bind_param("si", $new_password_hash, $user_id);
            
            if ($update_stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Password updated successfully';
            } else {
                throw new Exception('Failed to update password');
            }
            
            $update_stmt->close();
        } else {
            throw new Exception('User not found');
        }
        $check_stmt->close();
        
    } else {
        // 更新个人资料
        $conn->begin_transaction();
        
        if ($user_type === 'admin') {
            // 管理员可以更新的字段
            $allowed_fields = ['first_name', 'last_name', 'email'];
            $set_clause = [];
            $params = [];
            $types = '';
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $set_clause[] = "$field = ?";
                    
                    // 处理空值
                    if ($data[$field] === null || $data[$field] === '') {
                        $params[] = null;
                    } else {
                        $params[] = $data[$field];
                    }
                    
                    $types .= "s";
                }
            }
            
            if (empty($set_clause)) {
                throw new Exception('No valid fields to update');
            }
            
            $params[] = $user_id;
            $types .= "i";
            
            $query = "UPDATE admin SET " . implode(', ', $set_clause) . ", updated_at = NOW() WHERE auto_id = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param($types, ...$params);
            
        } else {
            // 客户可以更新的字段
            $allowed_fields = [
                'first_name', 'last_name', 'email', 'phone', 
                'address', 'city', 'state', 'zip_code', 'country',
                'date_of_birth', 'gender'
            ];
            
            $set_clause = [];
            $params = [];
            $types = '';
            
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $set_clause[] = "$field = ?";
                    
                    // 处理特殊字段
                    if ($field === 'date_of_birth') {
                        if ($data[$field] === null || $data[$field] === '') {
                            $params[] = null;
                        } else {
                            $params[] = date('Y-m-d', strtotime($data[$field]));
                        }
                    } else if ($field === 'gender') {
                        if ($data[$field] === null || $data[$field] === '') {
                            $params[] = null;
                        } else {
                            $params[] = $data[$field];
                        }
                    } else {
                        // 处理空值
                        if ($data[$field] === null || $data[$field] === '') {
                            $params[] = null;
                        } else {
                            $params[] = $data[$field];
                        }
                    }
                    
                    $types .= "s";
                }
            }
            
            if (empty($set_clause)) {
                throw new Exception('No valid fields to update');
            }
            
            $params[] = $user_id;
            $types .= "i";
            
            $query = "UPDATE customer SET " . implode(', ', $set_clause) . ", updated_at = NOW() WHERE auto_id = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            $stmt->bind_param($types, ...$params);
        }
        
        if ($stmt->execute()) {
            $conn->commit();
            $response['success'] = true;
            $response['message'] = 'Profile updated successfully';
        } else {
            throw new Exception('Failed to execute update: ' . $stmt->error);
        }
        
        $stmt->close();
    }
    
} catch (Exception $e) {
    if (isset($conn) && $conn) {
        $conn->rollback();
    }
    $response['message'] = $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>