<?php
// ForgotPassword.php - 完整修复版
require_once '../include/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => ''];

try {
    switch ($action) {
        case 'send_code':
            $response = handleSendCode($conn);
            break;
        case 'verify_code':
            $response = handleVerifyCode($conn);
            break;
        case 'reset_password':
            $response = handleResetPassword($conn);
            break;
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($response);

// 发送验证码处理函数 - 修复版
function handleSendCode($conn) {
    $email = $_POST['email'] ?? '';
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address'];
    }
    
    // 在admin和customer表中查找邮箱是否存在
    $stmt = $conn->prepare("
        SELECT 'admin' as user_type, auto_id FROM admin WHERE email = ?
        UNION
        SELECT 'customer' as user_type, auto_id FROM customer WHERE email = ?
    ");
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Email not found in our system'];
    }
    
    $user = $result->fetch_assoc();
    $table = $user['user_type']; // 'admin' 或 'customer'
    $auto_id = $user['auto_id'];
    
    // 生成验证码
    $code = generateVerificationCode();
    $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    // 更新对应表的验证码
    if ($table === 'admin') {
        $stmt = $conn->prepare("UPDATE admin SET verification_code = ?, verification_code_expiry = ? WHERE auto_id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE customer SET verification_code = ?, verification_code_expiry = ? WHERE auto_id = ?");
    }
    $stmt->bind_param("ssi", $code, $expiry, $auto_id);
    
    if ($stmt->execute()) {
        // 发送验证邮件（模拟）
        sendVerificationEmail($email, $code);
        return [
            'success' => true, 
            'message' => 'Verification code sent to your email',
            'email' => $email,
            'code' => $code // 将验证码返回给前端用于测试
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to send verification code'];
    }
}

// 验证验证码处理函数
function handleVerifyCode($conn) {
    $email = $_POST['email'] ?? '';
    $code = $_POST['code'] ?? '';
    
    if (empty($email) || empty($code)) {
        return ['success' => false, 'message' => 'Email and code are required'];
    }
    
    // 在admin表中查找
    $stmt = $conn->prepare("SELECT auto_id, verification_code, verification_code_expiry FROM admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $table = 'admin';
    } else {
        // 在customer表中查找
        $stmt = $conn->prepare("SELECT auto_id, verification_code, verification_code_expiry FROM customer WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $table = 'customer';
        } else {
            return ['success' => false, 'message' => 'Invalid email'];
        }
    }
    
    // 检查验证码是否过期
    $current_time = date('Y-m-d H:i:s');
    if (empty($user['verification_code']) || $user['verification_code_expiry'] < $current_time) {
        return ['success' => false, 'message' => 'Verification code has expired or not found'];
    }
    
    // 检查验证码是否匹配
    if ($user['verification_code'] === $code) {
        // 验证成功，生成一个临时令牌用于重置密码
        $token = bin2hex(random_bytes(32));
        $token_expiry = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        
        // 存储令牌
        $stmt = $conn->prepare("UPDATE $table SET reset_token = ?, reset_token_expiry = ?, verification_code = NULL, verification_code_expiry = NULL WHERE auto_id = ?");
        $stmt->bind_param("ssi", $token, $token_expiry, $user['auto_id']);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Code verified successfully', 'token' => $token];
        } else {
            return ['success' => false, 'message' => 'Failed to generate reset token'];
        }
    } else {
        return ['success' => false, 'message' => 'Invalid verification code'];
    }
}

// 重置密码处理函数
function handleResetPassword($conn) {
    $email = $_POST['email'] ?? '';
    $token = $_POST['token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    if (empty($email) || empty($token) || empty($new_password)) {
        return ['success' => false, 'message' => 'All fields are required'];
    }
    
    // 验证密码强度
    $passwordStrength = checkPasswordStrength($new_password);
    if (!$passwordStrength['valid']) {
        return ['success' => false, 'message' => $passwordStrength['message']];
    }
    
    // 在admin表中查找
    $stmt = $conn->prepare("SELECT auto_id, reset_token, reset_token_expiry FROM admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $table = 'admin';
    } else {
        // 在customer表中查找
        $stmt = $conn->prepare("SELECT auto_id, reset_token, reset_token_expiry FROM customer WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $table = 'customer';
        } else {
            return ['success' => false, 'message' => 'Invalid email'];
        }
    }
    
    // 检查令牌是否过期
    $current_time = date('Y-m-d H:i:s');
    if (empty($user['reset_token']) || $user['reset_token_expiry'] < $current_time) {
        return ['success' => false, 'message' => 'Reset token has expired or is invalid'];
    }
    
    // 检查令牌是否匹配
    if ($user['reset_token'] !== $token) {
        return ['success' => false, 'message' => 'Invalid reset token'];
    }
    
    // 更新密码
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE $table SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE auto_id = ?");
    $stmt->bind_param("si", $password_hash, $user['auto_id']);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Password reset successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to reset password'];
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
    error_log("Password reset email sent to $email with code: $code");
    
    // 模拟邮件发送
    $subject = "Password Reset Verification Code";
    $message = "Your password reset verification code is: $code\n";
    $message .= "This code will expire in 15 minutes.\n";
    $message .= "If you didn't request this, please ignore this email.";
    
    // 在实际应用中，使用以下代码：
    // mail($email, $subject, $message);
    
    return true;
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