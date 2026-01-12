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
    
    if (!isset($data['category_name']) || empty(trim($data['category_name']))) {
        echo json_encode(['success' => false, 'error' => 'Category name is required']);
        exit;
    }

    $category_id = intval($data['category_id']);
    $category_name = trim($data['category_name']);
    $description = isset($data['description']) ? trim($data['description']) : '';
    
    try {
        // Check if category exists
        $check_sql = "SELECT category_id FROM categories WHERE category_id = ?";
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
        
        // Check if new name already exists for other categories
        $name_check_sql = "SELECT category_id FROM categories WHERE category_name = ? AND category_id != ?";
        $name_check_stmt = $conn->prepare($name_check_sql);
        $name_check_stmt->bind_param("si", $category_name, $category_id);
        $name_check_stmt->execute();
        $name_check_result = $name_check_stmt->get_result();
        
        if ($name_check_result->num_rows > 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Category name already exists'
            ]);
            exit;
        }
        
        // Update category
        $sql = "UPDATE categories SET category_name = ?, description = ?, created_at = created_at WHERE category_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $category_name, $description, $category_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Category updated successfully',
                    'category_id' => $category_id,
                    'category_name' => $category_name
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'No changes made',
                    'category_id' => $category_id
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to update category: ' . $stmt->error
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