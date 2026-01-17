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

// 封面生成函数 - 确保一定有封面
function getBookCover($book) {
    // 如果数据库已经有封面，直接使用
    if (!empty($book['cover_image'])) {
        return $book['cover_image'];
    }
    
    // 如果有ISBN，使用Open Library
    if (!empty($book['isbn'])) {
        return "https://covers.openlibrary.org/b/isbn/{$book['isbn']}-M.jpg";
    }
    
    // 生成占位图，使用书名
    $title = urlencode(substr($book['title'], 0, 25));
    $author = !empty($book['author']) ? urlencode(substr($book['author'], 0, 15)) : '';
    
    if ($author) {
        return "https://placehold.co/300x400/4a7b9d/ffffff/png?text={$title}%0Aby+{$author}";
    } else {
        return "https://placehold.co/300x400/4a7b9d/ffffff/png?text={$title}";
    }
}

// Get all books with category information
$sql = "SELECT b.*, c.category_name 
        FROM books b 
        LEFT JOIN categories c ON b.category_id = c.category_id 
        ORDER BY b.book_id";

$result = $conn->query($sql);

if ($result) {
    $books = [];
    while ($row = $result->fetch_assoc()) {
        // 强制生成封面
        $row['cover_image'] = getBookCover($row);
        
        // 确保其他字段都有值
        $row['price'] = floatval($row['price']);
        $row['stock_quantity'] = intval($row['stock_quantity']);
        $row['rating'] = floatval($row['rating']);
        
        $books[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'books' => $books,
        'count' => count($books)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching books: ' . $conn->error
    ]);
}

$conn->close();
?>