<?php
// get_user_data.php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'user_data' => null,
    'message' => 'Not logged in'
];

if (!isset($_SESSION['user_id'])) {
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

try {
    // 根据用户类型查询不同的表
    if ($user_type === 'admin') {
        $query = "SELECT 
                    a.auto_id as id,
                    a.username,
                    a.email,
                    NULL as first_name,
                    NULL as last_name,
                    NULL as phone,
                    NULL as address,
                    NULL as city,
                    NULL as state,
                    NULL as zip_code,
                    NULL as country,
                    NULL as date_of_birth,
                    NULL as gender,
                    a.role as user_role,
                    'admin' as user_type,
                    DATE_FORMAT(a.created_at, '%M %e, %Y') as member_since
                  FROM admin a
                  WHERE a.auto_id = ?";
    } else {
        $query = "SELECT 
                    auto_id as id,
                    username,
                    email,
                    first_name,
                    last_name,
                    phone,
                    address,
                    city,
                    state,
                    zip_code,
                    country,
                    date_of_birth,
                    gender,
                    'customer' as user_role,
                    'customer' as user_type,
                    DATE_FORMAT(created_at, '%M %e, %Y') as member_since
                  FROM customer 
                  WHERE auto_id = ?";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        
        // 处理可能为NULL的值
        $fields_to_check = ['first_name', 'last_name', 'email', 'phone', 'address', 
                           'city', 'state', 'zip_code', 'country', 'date_of_birth', 'gender'];
        
        foreach ($fields_to_check as $field) {
            if (empty($user_data[$field])) {
                $user_data[$field] = 'Not specified';
            }
        }
        
        // 格式化日期
        if ($user_data['date_of_birth'] !== 'Not specified' && $user_data['date_of_birth']) {
            $date = new DateTime($user_data['date_of_birth']);
            $user_data['date_of_birth'] = $date->format('Y-m-d');
        }
        
        $response['success'] = true;
        $response['user_data'] = $user_data;
        $response['message'] = 'User data retrieved successfully';
    } else {
        $response['message'] = 'User not found';
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>