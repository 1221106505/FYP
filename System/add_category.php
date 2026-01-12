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
    if (!isset($data['category_name']) || empty(trim($data['category_name']))) {
        echo json_encode([
            'success' => false,
            'error' => 'Category name is required',
            'debug' => 'Received data: ' . print_r($data, true)
        ]);
        exit;
    }

    $category_name = trim($data['category_name']);
    $description = isset($data['description']) ? trim($data['description']) : '';
    
    try {
        // Check if category already exists
        $check_sql = "SELECT COUNT(*) as count FROM categories WHERE category_name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $category_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_row = $check_result->fetch_assoc();
        
        if ($check_row['count'] > 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Category already exists'
            ]);
            exit;
        }
        
        // Insert new category
        $sql = "INSERT INTO categories (category_name, description) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $category_name, $description);
        
        if ($stmt->execute()) {
            $category_id = $conn->insert_id;
            echo json_encode([
                'success' => true,
                'message' => 'Category added successfully',
                'category_id' => $category_id,
                'category_name' => $category_name
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to add category: ' . $stmt->error
            ]);
        }
        
        $stmt->close();
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