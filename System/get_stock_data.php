<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'Bookstore';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => 'Connection failed: ' . $conn->connect_error
    ]);
    exit();
}

// Get all books with stock information, rating, and sales data
$sql = "SELECT b.*, c.category_name 
        FROM books b 
        LEFT JOIN categories c ON b.category_id = c.category_id 
        ORDER BY b.stock_quantity ASC, b.book_id";

$result = $conn->query($sql);

if ($result) {
    $books = [];
    $inStock = 0;
    $lowStock = 0;
    $outOfStock = 0;
    
    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
        
        // 计算库存状态
        if ($row['stock_quantity'] > 10) {
            $inStock++;
        } elseif ($row['stock_quantity'] >= 1 && $row['stock_quantity'] <= 10) {
            $lowStock++;
        } elseif ($row['stock_quantity'] == 0) {
            $outOfStock++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'books' => $books,
        'stats' => [
            'inStock' => $inStock,
            'lowStock' => $lowStock,
            'outOfStock' => $outOfStock
        ],
        'hasLowStockWarning' => $lowStock > 0,
        'hasOutOfStockWarning' => $outOfStock > 0
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching stock data: ' . $conn->error
    ]);
}

$conn->close();
?>