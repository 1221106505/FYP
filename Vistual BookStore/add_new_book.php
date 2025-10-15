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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Check if it's an update or insert
if (!empty($input['id'])) {
    // Update existing book
    $sql = "UPDATE books SET 
            title = ?, 
            author = ?, 
            category_id = ?, 
            price = ?, 
            stock_quantity = ?, 
            isbn = ?, 
            description = ?, 
            publisher = ?, 
            publish_date = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE book_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssidsssssi", 
        $input['title'],
        $input['author'],
        $input['category_id'],
        $input['price'],
        $input['stock_quantity'],
        $input['isbn'],
        $input['description'],
        $input['publisher'],
        $input['publish_date'],
        $input['id']
    );
} else {
    // Insert new book
    $sql = "INSERT INTO books (title, author, category_id, price, stock_quantity, isbn, description, publisher, publish_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssidsssss", 
        $input['title'],
        $input['author'],
        $input['category_id'],
        $input['price'],
        $input['stock_quantity'],
        $input['isbn'],
        $input['description'],
        $input['publisher'],
        $input['publish_date']
    );
}

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => !empty($input['id']) ? 'Book updated successfully' : 'Book added successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Error saving book: ' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>