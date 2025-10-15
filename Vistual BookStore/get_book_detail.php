<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'Bookstore'; // 更新为 Bookstore

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

// Get book_id from request
$book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;

if ($book_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid book ID'
    ]);
    exit();
}

// Get book details
$sql = "SELECT b.*, c.category_name 
        FROM books b 
        LEFT JOIN categories c ON b.category_id = c.category_id 
        WHERE b.book_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $book = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'book' => $book
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Book not found'
    ]);
}

$stmt->close();
$conn->close();
?>