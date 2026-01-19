<?php
// Register.php - 完整版
require_once '../include/database.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$email = $_POST['email'] ?? '';

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

// 验证邮箱格式
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: Login.html?register_error=Invalid email format");
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

// 检查用户名是否存在（在当前表中）
$stmt = $conn->prepare("SELECT * FROM $table WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    header("Location: Login.html?register_error=Username already exists");
    exit();
}

// 检查邮箱是否已在任何表中注册过（跨表检查）
$stmt = $conn->prepare("
    SELECT 'admin' as user_type FROM admin WHERE email = ?
    UNION
    SELECT 'customer' as user_type FROM customer WHERE email = ?
");
$stmt->bind_param("ss", $email, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    header("Location: Login.html?register_error=Email already registered");
    exit();
}

// 注册用户
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$verification_code = generateVerificationCode();
$verification_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

$stmt = $conn->prepare("INSERT INTO $table (username, password_hash, email, verification_code, verification_code_expiry) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $username, $password_hash, $email, $verification_code, $verification_expiry);

if ($stmt->execute()) {
    // 发送验证邮件（模拟）
    sendVerificationEmail($email, $verification_code);
    
    header("Location: Login.html?register_success=Registration successful. Please check your email for verification code.");
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

// 生成验证码函数
function generateVerificationCode() {
    return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// 发送验证邮件函数（模拟）
function sendVerificationEmail($email, $code) {
    // 在实际应用中，这里应该发送真正的邮件
    // 这里我们只是记录到日志中
    error_log("Verification email sent to $email with code: $code");
    
    // 模拟邮件发送
    $subject = "Email Verification Code";
    $message = "Your verification code is: $code\n";
    $message .= "This code will expire in 1 hour.\n";
    $message .= "If you didn't request this, please ignore this email.";
    
    // 在实际应用中，使用以下代码：
    // mail($email, $subject, $message);
    
    return true;
}
?>