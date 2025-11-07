<?php
require_once __DIR__ . '/includes/config.php';

// 如果已登录，跳转到首页
if (isLoggedIn()) {
    redirect(BASE_PATH . '/index.php');
}

$error = '';
$success = '';

// 处理注册表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = escape($_POST['username'] ?? '');
    $email = escape($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $verification_code = $_POST['verification_code'] ?? '';
    $agree_terms = isset($_POST['agree_terms']);
    
    try {
        // 验证表单
        if (empty($username) || empty($email) || empty($password) || empty($verification_code)) {
            $error = '请填写所有必填字段';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '邮箱格式不正确';
        } elseif (strlen($password) < 6) {
            $error = '密码长度至少6位';
        } elseif ($password !== $confirm_password) {
            $error = '两次输入的密码不一致';
        } elseif (!$agree_terms) {
            $error = '请同意用户协议';
        } else {
            // 验证验证码
            $stmt = $pdo->prepare("SELECT * FROM verification_codes WHERE email = ? AND code = ? AND type = 'register' AND used = 0 AND expires_at > NOW()");
            $stmt->execute([$email, $verification_code]);
            $codeRecord = $stmt->fetch();
            
            if (!$codeRecord) {
                $error = '验证码错误或已过期';
            } else {
                // 检查用户名和邮箱是否已存在
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                $checkStmt->execute([$username, $email]);
                $exists = $checkStmt->fetchColumn();
                
                if ($exists > 0) {
                    $error = '用户名或邮箱已被注册';
                } else {
                    // 获取下一个用户ID
                    $idStmt = $pdo->query("SELECT COALESCE(MAX(user_id), 999) + 1 as next_id FROM users");
                    $nextId = $idStmt->fetch()['next_id'];
                    
                    // 创建用户
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $insertStmt = $pdo->prepare("INSERT INTO users (user_id, username, email, password, nickname, register_time, ip_address) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
                    $insertStmt->execute([$nextId, $username, $email, $hashedPassword, $username, $_SERVER['REMOTE_ADDR']]);
                    
                    $userId = $pdo->lastInsertId();
                    
                    // 标记验证码为已使用
                    $updateStmt = $pdo->prepare("UPDATE verification_codes SET used = 1 WHERE id = ?");
                    $updateStmt->execute([$codeRecord['id']]);
                    
                    // 获取用户排名
                    $rankStmt = $pdo->prepare("SELECT COUNT(*) as user_rank FROM users WHERE id <= ?");
                    $rankStmt->execute([$userId]);
                    $userRank = $rankStmt->fetch()['user_rank'];
                    
                    // 存储用户排名到session，用于注册成功页面显示
                    $_SESSION['new_user_rank'] = $userRank;
                    $_SESSION['new_user_email'] = $email;
                    
                    redirect(BASE_PATH . '/register_success.php');
                }
            }
        }
    } catch (PDOException $e) {
        error_log("注册错误: " . $e->getMessage());
        $error = '系统错误，请稍后再试';
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0"><i class="fas fa-user-plus me-2"></i>用户注册</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" id="registerForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="username" class="form-label">用户名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       placeholder="请输入用户名（用于登录）" value="<?php echo $_POST['username'] ?? ''; ?>" required>
                                <div class="form-text">用户名注册后不可修改</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">邮箱地址 <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="请输入有效的邮箱地址" value="<?php echo $_POST['email'] ?? ''; ?>" required>
                                    <button type="button" class="btn btn-outline-primary" id="sendCodeBtn" 
                                            onclick="sendVerificationCode('register')">
                                        <span id="codeText">获取验证码</span>
                                        <span id="countdown" style="display: none;"></span>
                                    </button>
                                </div>
                                <div class="form-text">验证码将发送到此邮箱</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">密码 <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="请输入密码（至少6位）" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">确认密码 <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="请再次输入密码" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="verification_code" class="form-label">邮箱验证码 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="verification_code" name="verification_code" 
                               placeholder="请输入收到的验证码" maxlength="6" required>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="agree_terms" name="agree_terms" required>
                            <label class="form-check-label" for="agree_terms">
                                我已阅读并同意 <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">《用户协议》</a> 和 <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">《隐私政策》</a>
                            </label>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-user-plus me-2"></i>立即注册
                        </button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <span>已有账号？</span>
                    <a href="<?php echo BASE_PATH; ?>/login.php" class="text-decoration-none">立即登录</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 用户协议模态框 -->
<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">用户协议</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>一、总则</h6>
                <p>欢迎您注册ML论坛！请您仔细阅读以下条款，如果您不同意本协议的任何内容，您应当立即停止注册程序。</p>
                
                <h6>二、用户权利与义务</h6>
                <p>1. 用户有权拥有自己在ML论坛的用户名及密码，并有权使用自己的用户名及密码随时登录ML论坛。</p>
                <p>2. 用户不得以任何形式擅自转让或授权他人使用自己在ML论坛的用户帐号。</p>
                
                <h6>三、内容规范</h6>
                <p>用户在使用ML论坛时，不得发布任何违反国家法律法规的内容。</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
            </div>
        </div>
    </div>
</div>

<!-- 隐私政策模态框 -->
<div class="modal fade" id="privacyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">隐私政策</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>一、信息收集</h6>
                <p>我们收集您主动提供的个人信息，包括用户名、邮箱地址等。</p>
                
                <h6>二、信息使用</h6>
                <p>我们使用收集的信息来提供、维护和改进我们的服务。</p>
                
                <h6>三、信息保护</h6>
                <p>我们采取合理的措施来保护您的个人信息安全。</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
            </div>
        </div>
    </div>
</div>

<script>
let countdownTimer;
let countdownSeconds = 300;

function sendVerificationCode(type) {
    const email = document.getElementById('email').value;
    const sendBtn = document.getElementById('sendCodeBtn');
    const codeText = document.getElementById('codeText');
    const countdown = document.getElementById('countdown');
    
    if (!email) {
        alert('请输入邮箱地址');
        return;
    }
    
    if (!validateEmail(email)) {
        alert('请输入有效的邮箱地址');
        return;
    }
    
    // 禁用按钮
    sendBtn.disabled = true;
    
    // 发送AJAX请求
    fetch('<?php echo BASE_PATH; ?>/ajax/send_verification_code.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `email=${encodeURIComponent(email)}&type=${type}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 显示提示信息
            showFlashMessage('验证码邮件已下发，5分钟内有效，如未收到，请到垃圾邮件里面查收一下');
            
            // 开始倒计时
            startCountdown();
        } else {
            alert(data.message || '发送失败，请稍后重试');
            sendBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('发送失败，请稍后重试');
        sendBtn.disabled = false;
    });
}

function startCountdown() {
    const sendBtn = document.getElementById('sendCodeBtn');
    const codeText = document.getElementById('codeText');
    const countdown = document.getElementById('countdown');
    
    codeText.style.display = 'none';
    countdown.style.display = 'inline';
    
    countdownTimer = setInterval(() => {
        const minutes = Math.floor(countdownSeconds / 60);
        const seconds = countdownSeconds % 60;
        countdown.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        if (countdownSeconds <= 0) {
            clearInterval(countdownTimer);
            sendBtn.disabled = false;
            codeText.style.display = 'inline';
            countdown.style.display = 'none';
            countdownSeconds = 300;
        } else {
            countdownSeconds--;
        }
    }, 1000);
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function showFlashMessage(message) {
    // 创建闪现消息元素
    const flashDiv = document.createElement('div');
    flashDiv.className = 'alert alert-info alert-dismissible fade show position-fixed';
    flashDiv.style.cssText = 'top: 20px; right: 20px; z-index: 1050; min-width: 300px;';
    flashDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(flashDiv);
    
    // 3秒后自动消失
    setTimeout(() => {
        if (flashDiv.parentNode) {
            flashDiv.parentNode.removeChild(flashDiv);
        }
    }, 3000);
}

// 密码确认验证
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && password !== confirmPassword) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>