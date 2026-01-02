<?php
header('Content-Type: application/json');

// 禁用错误输出
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 测试JSON输出
echo json_encode([
    'success' => true,
    'test' => 'API is working',
    'timestamp' => time()
]);
?>