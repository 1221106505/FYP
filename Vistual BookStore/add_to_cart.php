<?php
session_start();
require_once '../include/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo json_encode([
        'success' => false,
        'error' => 'Please login to add items to cart'
    ]);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$book_id = isset($data['book_id']) ? intval($data['book_id']) : 0;
$quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;
$is_pre_order = isset($data['is_pre_order']) ? boolval($data['is_pre_order']) : false;

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

// Check if book exists
$sql = "SELECT b.*, cat.category_name 
        FROM books b 
        LEFT JOIN categories cat ON b.category_id = cat.category_id
        WHERE b.book_id = ?";
$stmt = $conn->prepare($sql);
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

// Determine if this is a pre-order
$is_book_pre_order = $book['pre_order_available'] == 1;
$is_out_of_stock = $book['stock_quantity'] <= 0;

// ================================================
// 新逻辑：简化预购条件
// ================================================
// 允许预购的情况：
// 1. 用户主动选择预购 (is_pre_order = true)
// 2. 书本是预设预购商品 (pre_order_available = 1)
// 3. 书本缺货 (stock_quantity <= 0)

$should_be_pre_order = $is_pre_order || $is_book_pre_order || $is_out_of_stock;

// 特殊情况：如果书本有库存且不是预设预购商品，但用户选择预购，也允许
if ($should_be_pre_order) {
    // This is a pre-order item
    $expected_date = $book['expected_date'] ?: date('Y-m-d', strtotime('+30 days'));
    $pre_order_status = 'pending';
} else {
    // This is a regular in-stock item
    if ($quantity > $book['stock_quantity']) {
        echo json_encode([
            'success' => false,
            'error' => 'Not enough stock available. Only ' . $book['stock_quantity'] . ' items left.'
        ]);
        exit();
    }
    $expected_date = NULL;
    $pre_order_status = NULL;
}

// Check if item already exists in cart
$sql = "SELECT cart_id, quantity, is_pre_order FROM cart WHERE customer_id = ? AND book_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $customer_id, $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing cart item
    $cart_item = $result->fetch_assoc();
    $new_quantity = $cart_item['quantity'] + $quantity;
    
    // Check if item type matches
    $cart_is_pre_order = $cart_item['is_pre_order'] == 1;
    
    if ($cart_is_pre_order != $should_be_pre_order) {
        echo json_encode([
            'success' => false,
            'error' => 'Cannot mix pre-order and regular items. Please remove existing item first.'
        ]);
        exit();
    }
    
    $sql = "UPDATE cart SET quantity = ? WHERE cart_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $new_quantity, $cart_item['cart_id']);
} else {
    // Add new cart item
    if ($should_be_pre_order) {
        $sql = "INSERT INTO cart (customer_id, book_id, quantity, is_pre_order, pre_order_status, expected_date) 
                VALUES (?, ?, ?, TRUE, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiisss", $customer_id, $book_id, $quantity, $pre_order_status, $expected_date);
    } else {
        $sql = "INSERT INTO cart (customer_id, book_id, quantity) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $customer_id, $book_id, $quantity);
    }
}

if ($stmt->execute()) {
    // 根据不同情况返回不同消息
    $message = '';
    if ($is_out_of_stock) {
        $message = 'Out of stock item added to pre-orders';
    } else if ($is_book_pre_order) {
        $message = 'Pre-order item added to cart';
    } else if ($is_pre_order) {
        $message = 'Item added to pre-orders';
    } else {
        $message = 'Item added to cart';
    }
    
    $response = [
        'success' => true,
        'message' => $message,
        'is_pre_order' => $should_be_pre_order
    ];
    
    // 如果是预购，添加额外信息
    if ($should_be_pre_order && $expected_date) {
        $response['expected_date'] = $expected_date;
    }
    
    echo json_encode($response);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to add item to cart: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?>