<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/EmailSender.php';
require_once __DIR__ . '/../includes/VerificationManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => '无效的请求方法']);
}

$email = $_POST['email'] ?? '';
$type = $_POST['type'] ?? 'register'; // register, login, forgot, delete_account, change_email

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['success' => false, 'message' => '无效的邮箱地址']);
}

try {
    // 初始化验证码管理器
    $verificationManager = new VerificationManager($pdo);
    
    // 检查邮箱是否被滥用
    if ($verificationManager->isEmailAbused($email)) {
        jsonResponse(['success' => false, 'message' => '验证码发送过于频繁，请24小时后再试']);
    }
    
    // 检查邮箱是否已注册（对于注册类型）
    if ($type === 'register') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            jsonResponse(['success' => false, 'message' => '该邮箱已被注册']);
        }
    } else {
        // 对于其他类型，检查邮箱是否已注册
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND status = 1");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() === 0) {
            jsonResponse(['success' => false, 'message' => '该邮箱未注册']);
        }
    }
    
    // 创建验证码
    $codeResult = $verificationManager->createCode($email, $type, 5); // 5分钟过期
    
    if (!$codeResult['success']) {
        jsonResponse($codeResult);
    }
    
    // 发送邮件
    $emailSender = new EmailSender();
    $emailSent = $emailSender->sendVerificationCode($email, $codeResult['code'], $type);
    
    if ($emailSent) {
        jsonResponse(['success' => true, 'message' => '验证码发送成功']);
    } else {
        jsonResponse(['success' => false, 'message' => '邮件发送失败，请稍后重试']);
    }
    
} catch (PDOException $e) {
    error_log("发送验证码错误: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => '系统错误，请稍后再试']);
}

// 保留原有的辅助函数
function sendVerificationEmail($email, $code, $type) {
    // 获取邮件模板
    $template = getSystemSetting('email_template', '亲爱的ML论坛会员，您本次的验证码为{code}，5分钟内有效，如非本人操作，请您忽略。[ML论坛]');
    $subject = 'ML论坛 - 验证码';
    
    // 根据类型设置主题
    switch ($type) {
        case 'register':
            $subject = 'ML论坛 - 注册验证码';
            break;
        case 'login':
            $subject = 'ML论坛 - 登录验证码';
            break;
        case 'forgot':
            $subject = 'ML论坛 - 找回密码验证码';
            break;
        case 'delete_account':
            $subject = 'ML论坛 - 账号注销验证码';
            break;
        case 'change_email':
            $subject = 'ML论坛 - 更换邮箱验证码';
            break;
    }
    
    // 替换模板中的验证码
    $message = str_replace('{code}', $code, $template);
    
    // 添加图片（如果配置了）
    $image1_url = getSystemSetting('email_image1_url', '');
    $image2_url = getSystemSetting('email_image2_url', '');
    
    if ($image1_url) {
        $message = '<img src="' . $image1_url . '" alt="" style="max-width: 100%; margin-bottom: 20px;"><br>' . $message;
    }
    
    if ($image2_url) {
        $message .= '<br><br><img src="' . $image2_url . '" alt="" style="max-width: 100%; margin-top: 20px;">';
    }
    
    // 发送邮件
    return sendEmail($email, $subject, $message);
}

function sendEmail($to, $subject, $message) {
    // 获取邮件配置
    $smtp_host = getSystemSetting('smtp_host', '');
    $smtp_port = getSystemSetting('smtp_port', '587');
    $smtp_username = getSystemSetting('smtp_username', '');
    $smtp_password = getSystemSetting('smtp_password', '');
    $from_email = getSystemSetting('from_email', $smtp_username);
    $from_name = getSystemSetting('from_name', 'ML论坛');
    
    // 如果没有配置SMTP，使用PHP mail函数
    if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
        $headers = "From: $from_name <$from_email>\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        return mail($to, $subject, $message, $headers);
    }
    
    // 使用SMTP发送邮件（需要安装PHPMailer或其他邮件库）
    // 这里简化处理，实际使用时应该使用专业的邮件发送库
    $headers = "From: $from_name <$from_email>\r\n";
    $headers .= "Reply-To: $from_email\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
    // 使用mail函数作为备选
    return mail($to, $subject, $message, $headers);
}

function generateVerificationCode($length = 6) {
    $characters = '0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// 清理过期验证码的函数
function cleanExpiredVerificationCodes() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("清理过期验证码失败: " . $e->getMessage());
        return 0;
    }
}

// 检查验证码发送频率的函数
function checkVerificationFrequency($email, $type) {
    global $pdo;
    try {
        // 检查1分钟内是否已发送过
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM verification_codes 
            WHERE email = ? AND type = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute([$email, $type]);
        $recentCount = $stmt->fetch()['count'];
        
        if ($recentCount > 0) {
            return ['success' => false, 'message' => '验证码发送过于频繁，请稍后再试'];
        }
        
        // 检查24小时内发送总数
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM verification_codes 
            WHERE email = ? AND type = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$email, $type]);
        $dailyCount = $stmt->fetch()['count'];
        
        if ($dailyCount > 10) {
            return ['success' => false, 'message' => '今日验证码发送次数已达上限，请24小时后再试'];
        }
        
        return ['success' => true];
        
    } catch (PDOException $e) {
        error_log("检查验证码频率失败: " . $e->getMessage());
        return ['success' => false, 'message' => '系统错误，请稍后再试'];
    }
}
?>