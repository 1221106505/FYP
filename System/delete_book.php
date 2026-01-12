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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['id']) || empty($input['id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Book ID is required'
    ]);
    exit();
}

$book_id = intval($input['id']);

if ($book_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid book ID'
    ]);
    exit();
}

// First, check if the book exists
$check_sql = "SELECT book_id, title FROM books WHERE book_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $book_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Book not found'
    ]);
    $check_stmt->close();
    $conn->close();
    exit();
}

$book = $check_result->fetch_assoc();
$check_stmt->close();

// Delete the book
$delete_sql = "DELETE FROM books WHERE book_id = ?";
$delete_stmt = $conn->prepare($delete_sql);
$delete_stmt->bind_param("i", $book_id);

if ($delete_stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Book "' . $book['title'] . '" deleted successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Error deleting book: ' . $delete_stmt->error
    ]);
}

$delete_stmt->close();
$conn->close();
?>