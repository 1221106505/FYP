<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 数据库配置
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'Bookstore';

// 创建连接
$conn = new mysqli($host, $username, $password, $database);

// 检查连接
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => 'Connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// 获取销量最好的4本书
$sql = "SELECT 
            b.book_id,
            b.title,
            b.author,
            b.price,
            b.stock_quantity,
            b.rating,
            b.total_sales,
            c.category_name,
            CONCAT('https://via.placeholder.com/80x100/4A90E2/FFFFFF?text=', SUBSTRING(REPLACE(b.title, ' ', '+'), 1, 10)) as cover_image
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.category_id
        ORDER BY b.total_sales DESC, b.rating DESC
        LIMIT 4";

$result = $conn->query($sql);

if ($result) {
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'books' => $books
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching bestsellers: ' . $conn->error
    ]);
}

$conn->close();
?>