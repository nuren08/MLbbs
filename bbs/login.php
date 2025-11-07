<?php
require_once __DIR__ . '/includes/config.php';

// 如果已登录，跳转到首页
if (isLoggedIn()) {
    redirect(BASE_PATH . '/index.php');
}

$error = '';
$success = '';

// 处理登录表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_type = $_POST['login_type'] ?? '';
    $identifier = escape($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $verification_code = $_POST['verification_code'] ?? '';
    
    try {
        if ($login_type === 'id_password') {
            // ID+密码登录
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND status = 1");
            $stmt->execute([$identifier]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_info'] = $user;
                
                // 更新最后登录时间和IP
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW(), ip_address = ? WHERE id = ?");
                $updateStmt->execute([$_SERVER['REMOTE_ADDR'], $user['id']]);
                
                redirect(BASE_PATH . '/index.php');
            } else {
                $error = '用户ID或密码错误';
            }
            
        } elseif ($login_type === 'email_password') {
            // 邮箱+密码登录
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 1");
            $stmt->execute([$identifier]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_info'] = $user;
                
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW(), ip_address = ? WHERE id = ?");
                $updateStmt->execute([$_SERVER['REMOTE_ADDR'], $user['id']]);
                
                redirect(BASE_PATH . '/index.php');
            } else {
                $error = '邮箱或密码错误';
            }
            
        } elseif ($login_type === 'email_code') {
            // 邮箱+验证码登录
            if (empty($verification_code)) {
                $error = '请输入验证码';
            } else {
                // 验证验证码
                $stmt = $pdo->prepare("SELECT * FROM verification_codes WHERE email = ? AND code = ? AND type = 'login' AND used = 0 AND expires_at > NOW()");
                $stmt->execute([$identifier, $verification_code]);
                $codeRecord = $stmt->fetch();
                
                if ($codeRecord) {
                    // 获取用户
                    $userStmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 1");
                    $userStmt->execute([$identifier]);
                    $user = $userStmt->fetch();
                    
                    if ($user) {
                        // 标记验证码为已使用
                        $updateCodeStmt = $pdo->prepare("UPDATE verification_codes SET used = 1 WHERE id = ?");
                        $updateCodeStmt->execute([$codeRecord['id']]);
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_info'] = $user;
                        
                        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW(), ip_address = ? WHERE id = ?");
                        $updateStmt->execute([$_SERVER['REMOTE_ADDR'], $user['id']]);
                        
                        redirect(BASE_PATH . '/index.php');
                    } else {
                        $error = '邮箱未注册';
                    }
                } else {
                    $error = '验证码错误或已过期';
                }
            }
        }
    } catch (PDOException $e) {
        error_log("登录错误: " . $e->getMessage());
        $error = '系统错误，请稍后再试';
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-sign-in-alt me-2"></i>用户登录</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <ul class="nav nav-tabs mb-4" id="loginTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#idPassword">
                            <i class="fas fa-id-card me-1"></i>ID+密码
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#emailPassword">
                            <i class="fas fa-envelope me-1"></i>邮箱+密码
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#emailCode">
                            <i class="fas fa-sms me-1"></i>验证码登录
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- ID+密码登录 -->
                    <div class="tab-pane fade show active" id="idPassword">
                        <form method="POST">
                            <input type="hidden" name="login_type" value="id_password">
                            <div class="mb-3">
                                <label for="id_identifier" class="form-label">用户ID</label>
                                <input type="text" class="form-control" id="id_identifier" name="identifier" 
                                       placeholder="请输入您的用户ID" required>
                            </div>
                            <div class="mb-3">
                                <label for="id_password" class="form-label">密码</label>
                                <input type="password" class="form-control" id="id_password" name="password" 
                                       placeholder="请输入密码" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>立即登录
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- 邮箱+密码登录 -->
                    <div class="tab-pane fade" id="emailPassword">
                        <form method="POST">
                            <input type="hidden" name="login_type" value="email_password">
                            <div class="mb-3">
                                <label for="email_identifier" class="form-label">邮箱地址</label>
                                <input type="email" class="form-control" id="email_identifier" name="identifier" 
                                       placeholder="请输入您的邮箱地址" required>
                            </div>
                            <div class="mb-3">
                                <label for="email_password" class="form-label">密码</label>
                                <input type="password" class="form-control" id="email_password" name="password" 
                                       placeholder="请输入密码" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>立即登录
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- 验证码登录 -->
                    <div class="tab-pane fade" id="emailCode">
                        <form method="POST" id="codeLoginForm">
                            <input type="hidden" name="login_type" value="email_code">
                            <div class="mb-3">
                                <label for="code_identifier" class="form-label">邮箱地址</label>
                                <div class="input-group">
                                    <input type="email" class="form-control" id="code_identifier" name="identifier" 
                                           placeholder="请输入您的邮箱地址" required>
                                    <button type="button" class="btn btn-outline-primary" id="sendCodeBtn" 
                                            onclick="sendVerificationCode('login')">
                                        <span id="codeText">获取验证码</span>
                                        <span id="countdown" style="display: none;"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="verification_code" class="form-label">验证码</label>
                                <input type="text" class="form-control" id="verification_code" name="verification_code" 
                                       placeholder="请输入收到的验证码" maxlength="6" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>立即登录
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a href="<?php echo BASE_PATH; ?>/forgot_password.php" class="text-decoration-none">
                        <i class="fas fa-key me-1"></i>忘记密码？
                    </a>
                    <span class="mx-2">|</span>
                    <a href="<?php echo BASE_PATH; ?>/register.php" class="text-decoration-none">
                        <i class="fas fa-user-plus me-1"></i>立即注册
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let countdownTimer;
let countdownSeconds = 300;

function sendVerificationCode(type) {
    const email = document.getElementById('code_identifier').value;
    const sendBtn = document.getElementById('sendCodeBtn');
    const codeText = document.getElementById('codeText');
    const countdown = document.getElementById('countdown');
    
    if (!email) {
        alert('请输入邮箱地址');
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

// 切换标签时重置表单
document.getElementById('loginTabs').addEventListener('show.bs.tab', function (e) {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => form.reset());
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>