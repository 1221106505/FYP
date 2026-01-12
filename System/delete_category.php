<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

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

// Get POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get raw POST data
    $rawData = file_get_contents("php://input");
    
    // Try to decode as JSON first
    $data = json_decode($rawData, true);
    
    // If not JSON, try form data
    if ($data === null) {
        parse_str($rawData, $data);
    }
    
    // If still empty, use $_POST
    if (empty($data) && !empty($_POST)) {
        $data = $_POST;
    }
    
    // Validate required fields
    if (!isset($data['category_id']) || empty($data['category_id'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Category ID is required',
            'debug' => 'Received data: ' . print_r($data, true)
        ]);
        exit;
    }

    $category_id = intval($data['category_id']);
    
    try {
        // Check if category exists
        $check_sql = "SELECT category_name FROM categories WHERE category_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $category_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Category not found'
            ]);
            exit;
        }
        
        $category_row = $check_result->fetch_assoc();
        $category_name = $category_row['category_name'];
        
        // Check if this is a system category (like Uncategorized)
        if ($category_name === 'Uncategorized') {
            echo json_encode([
                'success' => false,
                'error' => 'Cannot delete system category: Uncategorized'
            ]);
            exit;
        }
        
        // Check how many books are in this category
        $book_check_sql = "SELECT COUNT(*) as book_count FROM books WHERE category_id = ?";
        $book_check_stmt = $conn->prepare($book_check_sql);
        $book_check_stmt->bind_param("i", $category_id);
        $book_check_stmt->execute();
        $book_result = $book_check_stmt->get_result();
        $book_row = $book_result->fetch_assoc();
        $book_count = $book_row['book_count'];
        
        // Get or create Uncategorized category
        $uncategorized_sql = "SELECT category_id FROM categories WHERE category_name = 'Uncategorized' LIMIT 1";
        $uncategorized_result = $conn->query($uncategorized_sql);
        
        if ($uncategorized_result->num_rows === 0) {
            // Create Uncategorized category
            $create_uncategorized = "INSERT INTO categories (category_name, description) VALUES ('Uncategorized', 'Default category for unassigned books')";
            if ($conn->query($create_uncategorized)) {
                $uncategorized_id = $conn->insert_id;
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to create Uncategorized category: ' . $conn->error
                ]);
                exit;
            }
        } else {
            $uncategorized_row = $uncategorized_result->fetch_assoc();
            $uncategorized_id = $uncategorized_row['category_id'];
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Move books to Uncategorized if there are any
            if ($book_count > 0) {
                $update_books_sql = "UPDATE books SET category_id = ? WHERE category_id = ?";
                $update_books_stmt = $conn->prepare($update_books_sql);
                $update_books_stmt->bind_param("ii", $uncategorized_id, $category_id);
                $update_books_stmt->execute();
                
                if ($update_books_stmt->affected_rows === -1) {
                    throw new Exception('Failed to update books: ' . $update_books_stmt->error);
                }
                $update_books_stmt->close();
            }
            
            // Delete the category
            $delete_sql = "DELETE FROM categories WHERE category_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $category_id);
            $delete_stmt->execute();
            
            if ($delete_stmt->affected_rows === 0) {
                throw new Exception('Failed to delete category');
            }
            
            // Commit transaction
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Category deleted successfully',
                'books_moved' => $book_count,
                'moved_to_category' => $uncategorized_id
            ]);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method. Use POST.'
    ]);
}

$conn->close();
?>