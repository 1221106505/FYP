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
if (!isset($input['book_id']) || empty($input['book_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Book ID is required'
    ]);
    exit();
}

if (!isset($input['stock_quantity']) || $input['stock_quantity'] < 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Valid stock quantity is required'
    ]);
    exit();
}

$book_id = intval($input['book_id']);
$stock_quantity = intval($input['stock_quantity']);

// Update book stock
$sql = "UPDATE books SET stock_quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE book_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $stock_quantity, $book_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Stock updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No changes made or book not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Error updating stock: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>