<?php
// /api/cart/_common.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// 添加CORS头
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 注意：根据您的路径结构，可能需要调整这个路径
require_once __DIR__ . '/../../api/database.php'; // 指向您的database.php文件

function json_out(array $data): void {
    // 确保之前没有输出
    if (ob_get_level()) ob_clean();
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function get_customer_id(): int {
    // 检查多种可能的session key
    $cid = $_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
    
    // 调试：输出session信息
    if (!$cid) {
        error_log("Session data: " . json_encode($_SESSION));
        error_log("Session ID: " . session_id());
    }
    
    // 开发环境下允许通过URL参数传递customer_id
    if (!$cid) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isLocal = ($host === 'localhost' || str_starts_with($host, '127.0.0.1'));
        if ($isLocal && isset($_GET['customer_id'])) {
            $cid = (int)$_GET['customer_id'];
            error_log("Using customer_id from URL: " . $cid);
        }
    }
    
    if (!$cid) {
        json_out(['success' => false, 'error' => 'Not logged in. Please login first.', 'session' => $_SESSION]);
    }
    
    return (int)$cid;
}

function read_json(): array {
    $raw = file_get_contents('php://input');
    if (!$raw || trim($raw) === '') {
        return [];
    }
    
    $data = json_decode($raw, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        error_log("Raw input: " . $raw);
        return [];
    }
    
    return is_array($data) ? $data : [];
}