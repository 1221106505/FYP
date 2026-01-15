<?php
// Register.php
require_once '../include/database.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<h1>Register Page</h1>";
    echo '<a href="Login.html">Go to Login Page</a>';
    exit();
}

// 验证用户名格式
if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    header("Location: Login.html?register_error=Invalid username format");
    exit();
}

// 验证密码强度
$passwordStrength = checkPasswordStrength($password);
if (!$passwordStrength['valid']) {
    header("Location: Login.html?register_error=" . urlencode($passwordStrength['message']));
    exit();
}

$user_type = (strpos($username, 'admin_') === 0) ? 'admin' : 'customer';
$table = ($user_type === 'admin') ? 'admin' : 'customer';

// 检查用户名是否存在
$stmt = $conn->prepare("SELECT * FROM $table WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    header("Location: Login.html?register_error=Username already exists");
    exit();
}

// 注册用户
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO $table (username, password_hash) VALUES (?, ?)");
$stmt->bind_param("ss", $username, $password_hash);

if ($stmt->execute()) {
    header("Location: Login.html?register_success=Registration successful. Please login.");
    exit();
} else {
    header("Location: Login.html?register_error=Registration failed");
    exit();
}

// 密码强度检查函数
function checkPasswordStrength($password) {
    $minLength = 8;
    
    $hasUppercase = preg_match('/[A-Z]/', $password);
    $hasLowercase = preg_match('/[a-z]/', $password);
    $hasNumber = preg_match('/[0-9]/', $password);
    $hasSpecial = preg_match('/[@#$%&!]/', $password);
    
    $strength = 0;
    if (strlen($password) >= $minLength) $strength++;
    if ($hasUppercase) $strength++;
    if ($hasLowercase) $strength++;
    if ($hasNumber) $strength++;
    if ($hasSpecial) $strength++;
    
    $messages = [];
    if (strlen($password) < $minLength) {
        $messages[] = "Password must be at least $minLength characters";
    }
    if (!$hasUppercase) {
        $messages[] = "Password must contain at least one uppercase letter";
    }
    if (!$hasLowercase) {
        $messages[] = "Password must contain at least one lowercase letter";
    }
    if (!$hasNumber) {
        $messages[] = "Password must contain at least one number";
    }
    if (!$hasSpecial) {
        $messages[] = "Password must contain at least one special character (@, #, $, %, &, !)";
    }
    
    if ($strength >= 3) {
        $strengthLevels = ['Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
        $level = $strengthLevels[min($strength, 4) - 1];
        return [
            'valid' => true,
            'strength' => $strength,
            'level' => $level,
            'message' => "Password strength: $level"
        ];
    } else {
        return [
            'valid' => false,
            'strength' => $strength,
            'level' => 'Too Weak',
            'message' => implode('. ', $messages)
        ];
    }
}
?>