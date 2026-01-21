<?php
// delete_admin.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'bookstore';

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

// Start session for authentication
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Please login first'
    ]);
    $conn->close();
    exit();
}

// Get current user ID
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SESSION['admin_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['admin_id']) || empty($input['admin_id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Admin ID is required'
    ]);
    $conn->close();
    exit();
}

$admin_id = intval($input['admin_id']);

if ($admin_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid admin ID'
    ]);
    $conn->close();
    exit();
}

// 1. Check if current user exists and is superadmin
$check_current_sql = "SELECT auto_id, username, role FROM admin WHERE auto_id = ?";
$check_current_stmt = $conn->prepare($check_current_sql);
$check_current_stmt->bind_param("i", $current_user_id);
$check_current_stmt->execute();
$current_result = $check_current_stmt->get_result();

if ($current_result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Current user not found'
    ]);
    $check_current_stmt->close();
    $conn->close();
    exit();
}

$current_admin = $current_result->fetch_assoc();
$check_current_stmt->close();

// Check if current user is superadmin
if ($current_admin['role'] !== 'superadmin') {
    echo json_encode([
        'success' => false,
        'error' => 'Only superadmin can delete admins'
    ]);
    $conn->close();
    exit();
}

// 2. Check if admin to delete exists
$check_target_sql = "SELECT auto_id, username, role FROM admin WHERE auto_id = ?";
$check_target_stmt = $conn->prepare($check_target_sql);
$check_target_stmt->bind_param("i", $admin_id);
$check_target_stmt->execute();
$target_result = $check_target_stmt->get_result();

if ($target_result->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Admin not found'
    ]);
    $check_target_stmt->close();
    $conn->close();
    exit();
}

$target_admin = $target_result->fetch_assoc();
$check_target_stmt->close();

// 3. Prevent deleting yourself
if ($admin_id == $current_user_id) {
    echo json_encode([
        'success' => false,
        'error' => 'Cannot delete your own account'
    ]);
    $conn->close();
    exit();
}

// 4. Special handling for superadmin deletion
if ($target_admin['role'] === 'superadmin') {
    // Check if this is the last superadmin
    $count_superadmin_sql = "SELECT COUNT(*) as count FROM admin WHERE role = 'superadmin'";
    $count_result = $conn->query($count_superadmin_sql);
    $superadmin_count = $count_result->fetch_assoc()['count'];
    
    if ($superadmin_count <= 1) {
        echo json_encode([
            'success' => false,
            'error' => 'Cannot delete the last super admin account'
        ]);
        $conn->close();
        exit();
    }
    
    // Check for special confirmation
    if (!isset($input['confirm_superadmin']) || $input['confirm_superadmin'] !== true) {
        echo json_encode([
            'success' => false,
            'error' => 'This is a super admin account. Requires special confirmation.',
            'requires_confirmation' => true,
            'admin_name' => $target_admin['username']
        ]);
        $conn->close();
        exit();
    }
}

// 5. Delete the admin
$delete_sql = "DELETE FROM admin WHERE auto_id = ?";
$delete_stmt = $conn->prepare($delete_sql);
$delete_stmt->bind_param("i", $admin_id);

if ($delete_stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Admin "' . $target_admin['username'] . '" deleted successfully',
        'deleted_admin' => $target_admin['username']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Error deleting admin: ' . $delete_stmt->error
    ]);
}

$delete_stmt->close();
$conn->close();
?>