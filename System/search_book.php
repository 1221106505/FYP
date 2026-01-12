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

// Get search term
$search = isset($_GET['search']) ? $_GET['search'] : '';

if (empty($search)) {
    echo json_encode([
        'success' => false,
        'error' => 'Search term is required'
    ]);
    exit();
}

// Search books by title, author, or ISBN
$sql = "SELECT b.*, c.category_name 
        FROM books b 
        LEFT JOIN categories c ON b.category_id = c.category_id 
        WHERE b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?
        ORDER BY b.book_id";

$searchTerm = "%" . $search . "%";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

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
        'error' => 'Error searching books: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?>