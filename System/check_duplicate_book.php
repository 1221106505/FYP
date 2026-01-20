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


if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

$title = $_GET['title'] ?? '';
$book_id = $_GET['book_id'] ?? 0;

if (empty($title)) {
    echo json_encode(['success' => false, 'error' => 'No title provided']);
    exit;
}

// 检查是否有重复书名
$sql = "SELECT book_id, title FROM books WHERE title = ?";
if ($book_id) {
    $sql .= " AND book_id != ?";
}

$stmt = $conn->prepare($sql);
if ($book_id) {
    $stmt->bind_param("si", $title, $book_id);
} else {
    $stmt->bind_param("s", $title);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'duplicate' => true,
        'book_id' => $row['book_id'],
        'title' => $row['title']
    ]);
} else {
    echo json_encode([
        'success' => true,
        'duplicate' => false
    ]);
}

$stmt->close();
$conn->close();
?>