<?php
// check_email.php - 检查邮箱是否已注册
require_once '../include/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['exists' => false, 'message' => 'Invalid request']);
    exit();
}

$email = $_POST['email'] ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['exists' => false, 'message' => 'Invalid email']);
    exit();
}

// 检查邮箱是否在admin或customer表中
$stmt = $conn->prepare("
    SELECT 'admin' as user_type, auto_id, username FROM admin WHERE email = ?
    UNION
    SELECT 'customer' as user_type, auto_id, username FROM customer WHERE email = ?
");
$stmt->bind_param("ss", $email, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo json_encode([
        'exists' => true,
        'user_type' => $user['user_type'],
        'username' => $user['username'],
        'auto_id' => $user['auto_id']
    ]);
} else {
    echo json_encode(['exists' => false, 'message' => 'Email not registered']);
}

$stmt->close();
$conn->close();
?>