<?php
// check_admin_session.php
function checkAdminSession() {
    session_start();
    
    if (!isset($_SESSION['admin_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    return $_SESSION['admin_id'];
}

function checkSuperAdmin($conn) {
    $adminId = checkAdminSession();
    
    $stmt = $conn->prepare("SELECT role FROM admin WHERE auto_id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    if (!$admin || $admin['role'] !== 'superadmin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied. Super Admin only.']);
        exit;
    }
    
    return $adminId;
}
?>