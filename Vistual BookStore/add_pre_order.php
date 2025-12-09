<?php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');

// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0); // 不显示错误，记录到日志

// 检查用户登录
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo json_encode([
        'success' => false,
        'error' => 'Please login to pre-order items'
    ]);
    exit();
}

// 获取POST数据
$data = json_decode(file_get_contents('php://input'), true);
$book_id = isset($data['book_id']) ? intval($data['book_id']) : 0;
$quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;

if ($book_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid book ID'
    ]);
    exit();
}

if ($quantity <= 0) {
    $quantity = 1;
}

$customer_id = $_SESSION['user_id'];

// 检查书籍是否存在
$sql = "SELECT b.*, cat.category_name 
        FROM books b 
        LEFT JOIN categories cat ON b.category_id = cat.category_id
        WHERE b.book_id = ?";
        
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $conn->error
    ]);
    exit();
}

$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Book not found'
    ]);
    exit();
}

$book = $result->fetch_assoc();
$stmt->close();

// ================================================
// 关键修改：只允许缺货书籍预购
// ================================================
if ($book['stock_quantity'] > 0) {
    echo json_encode([
        'success' => false,
        'error' => 'This book is in stock. Please use "Add to Cart" instead.'
    ]);
    exit();
}

// 计算总金额
$price = $book['pre_order_price'] ?: $book['price'];
$total_amount = $price * $quantity;

// 插入预购记录
$sql = "INSERT INTO pre_orders (customer_id, book_id, quantity, total_amount, expected_delivery_date, status) 
        VALUES (?, ?, ?, ?, ?, 'pending')";
        
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to prepare statement: ' . $conn->error
    ]);
    exit();
}

// 设置预计送达日期
if (!empty($book['expected_date'])) {
    $expected_date = $book['expected_date'];
} else {
    // 缺货书籍设置30天后
    $expected_date = date('Y-m-d', strtotime('+30 days'));
}

$stmt->bind_param("iiids", $customer_id, $book_id, $quantity, $total_amount, $expected_date);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Pre-order placed successfully!',
        'expected_date' => $expected_date,
        'total_amount' => number_format($total_amount, 2),
        'book_title' => $book['title']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to place pre-order: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?>