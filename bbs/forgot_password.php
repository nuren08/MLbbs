<?php
require_once __DIR__ . '/includes/config.php';

// 如果已登录，跳转到首页
if (isLoggedIn()) {
    redirect(BASE_PATH . '/index.php');
}

$error = '';
$success = '';
$step = $_GET['step'] ?? '1';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_step = $_POST['step'] ?? '1';
    
    try {
        if ($current_step === '1') {
            // 第一步：验证身份
            $email = escape($_POST['email'] ?? '');
            $verification_method = $_POST['verification_method'] ?? '';
            
            if (empty($email) || empty($verification_method)) {
                $error = '请填写邮箱并选择验证方式';
            } else {
                // 检查邮箱是否存在
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $error = '该邮箱未注册或账号已被禁用';
                } else {
                    $_SESSION['reset_password_email'] = $email;
                    $_SESSION['reset_password_method'] = $verification_method;
                    
                    if ($verification_method === 'email') {
                        $step = '2_email';
                    } else {
                        $step = '2_security';
                    }
                }
            }
            
        } elseif ($current_step === '2_email') {
            // 第二步：邮箱验证码验证
            $verification_code = $_POST['verification_code'] ?? '';
            $email = $_SESSION['reset_password_email'] ?? '';
            
            if (empty($verification_code)) {
                $error = '请输入验证码';
            } else {
                // 验证验证码
                $stmt = $pdo->prepare("SELECT * FROM verification_codes WHERE email = ? AND code = ? AND type = 'forgot' AND used = 0 AND expires_at > NOW()");
                $stmt->execute([$email, $verification_code]);
                $codeRecord = $stmt->fetch();
                
                if ($codeRecord) {
                    // 标记验证码为已使用
                    $updateStmt = $pdo->prepare("UPDATE verification_codes SET used = 1 WHERE id = ?");
                    $updateStmt->execute([$codeRecord['id']]);
                    
                    $_SESSION['reset_password_verified'] = true;
                    $step = '3_reset';
                } else {
                    $error = '验证码错误或已过期';
                }
            }
            
        } elseif ($current_step === '2_security') {
            // 第二步：密保问题验证
            $answer1 = escape($_POST['security_answer1'] ?? '');
            $answer2 = escape($_POST['security_answer2'] ?? '');
            $email = $_SESSION['reset_password_email'] ?? '';
            
            if (empty($answer1) || empty($answer2)) {
                $error = '请填写密保答案';
            } else {
                // 验证密保问题
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND security_question1 = ? AND security_question2 = ? AND status = 1");
                $stmt->execute([$email, $answer1, $answer2]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $_SESSION['reset_password_verified'] = true;
                    $step = '3_reset';
                } else {
                    $error = '密保答案错误';
                }
            }
            
        } elseif ($current_step === '3_reset') {
            // 第三步：重置密码
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $email = $_SESSION['reset_password_email'] ?? '';
            
            if (empty($new_password) || empty($confirm_password)) {
                $error = '请填写新密码和确认密码';
            } elseif (strlen($new_password) < 6) {
                $error = '密码长度至少6位';
            } elseif ($new_password !== $confirm_password) {
                $error = '两次输入的密码不一致';
            } elseif (!isset($_SESSION['reset_password_verified']) || !$_SESSION['reset_password_verified']) {
                $error = '验证未通过，请重新开始';
            } else {
                // 更新密码
                $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $updateStmt->execute([$hashedPassword, $email]);
                
                // 清除session
                unset($_SESSION['reset_password_email']);
                unset($_SESSION['reset_password_method']);
                unset($_SESSION['reset_password_verified']);
                
                $success = '密码重置成功，请使用新密码登录';
                $step = 'success';
            }
        }
    } catch (PDOException $e) {
        error_log("找回密码错误: " . $e->getMessage());
        $error = '系统错误，请稍后再试';
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0"><i class="fas fa-key me-2"></i>找回密码</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- 步骤指示器 -->
                <div class="steps mb-4">
                    <div class="d-flex justify-content-between">
                        <div class="step-item <?php echo in_array($step, ['1', '2_email', '2_security', '3_reset', 'success']) ? 'active' : ''; ?>">
                            <div class="step-number">1</div>
                            <div class="step-label">验证身份</div>
                        </div>
                        <div class="step-item <?php echo in_array($step, ['2_email', '2_security', '3_reset', 'success']) ? 'active' : ''; ?>">
                            <div class="step-number">2</div>
                            <div class="step-label">安全验证</div>
                        </div>
                        <div class="step-item <?php echo in_array($step, ['3_reset', 'success']) ? 'active' : ''; ?>">
                            <div class="step-number">3</div>
                            <div class="step-label">重置密码</div>
                        </div>
                    </div>
                </div>

                <!-- 第一步：验证身份 -->
                <?php if ($step === '1'): ?>
                <form method="POST">
                    <input type="hidden" name="step" value="1">
                    <div class="mb-3">
                        <label for="email" class="form-label">注册邮箱 <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="请输入您注册时使用的邮箱地址" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">验证方式 <span class="text-danger">*</span></label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="verification_method" id="method_email" value="email" checked>
                            <label class="form-check-label" for="method_email">
                                <i class="fas fa-envelope me-1"></i>邮箱验证码验证
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="verification_method" id="method_security" value="security">
                            <label class="form-check-label" for="method_security">
                                <i class="fas fa-shield-alt me-1"></i>密保问题验证
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="fas fa-arrow-right me-2"></i>下一步
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- 第二步：邮箱验证码验证 -->
                <?php if ($step === '2_email'): ?>
                <form method="POST">
                    <input type="hidden" name="step" value="2_email">
                    <div class="mb-3">
                        <label class="form-label">验证邮箱</label>
                        <p class="form-control-plaintext"><?php echo escape($_SESSION['reset_password_email'] ?? ''); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="verification_code" class="form-label">邮箱验证码 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="verification_code" name="verification_code" 
                                   placeholder="请输入收到的验证码" maxlength="6" required>
                            <button type="button" class="btn btn-outline-warning" id="sendCodeBtn" 
                                    onclick="sendVerificationCode('forgot')">
                                <span id="codeText">获取验证码</span>
                                <span id="countdown" style="display: none;"></span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="fas fa-arrow-right me-2"></i>下一步
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- 第二步：密保问题验证 -->
                <?php if ($step === '2_security'): ?>
                <form method="POST">
                    <input type="hidden" name="step" value="2_security">
                    <div class="mb-3">
                        <label class="form-label">验证邮箱</label>
                        <p class="form-control-plaintext"><?php echo escape($_SESSION['reset_password_email'] ?? ''); ?></p>
                    </div>
                    
                    <?php
                    // 获取用户的密保问题（只显示问题，不显示答案）
                    $email = $_SESSION['reset_password_email'] ?? '';
                    $security_questions = [];
                    if ($email) {
                        $stmt = $pdo->prepare("SELECT security_question1, security_question2 FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        $user = $stmt->fetch();
                        if ($user && $user['security_question1'] && $user['security_question2']) {
                            $security_questions = $user;
                        }
                    }
                    
                    if (empty($security_questions)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        您尚未设置密保问题，请选择邮箱验证码方式找回密码。
                    </div>
                    <div class="d-grid">
                        <a href="?step=1" class="btn btn-outline-warning">
                            <i class="fas fa-arrow-left me-2"></i>返回上一步
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="mb-3">
                        <label for="security_answer1" class="form-label">我的小学名称 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="security_answer1" name="security_answer1" 
                               placeholder="请输入您设置的小学名称" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="security_answer2" class="form-label">我的手机尾号 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="security_answer2" name="security_answer2" 
                               placeholder="请输入您设置的手机尾号" maxlength="4" required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="fas fa-arrow-right me-2"></i>下一步
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
                <?php endif; ?>

                <!-- 第三步：重置密码 -->
                <?php if ($step === '3_reset'): ?>
                <form method="POST">
                    <input type="hidden" name="step" value="3_reset">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">新密码 <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" 
                               placeholder="请输入新密码（至少6位）" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">确认新密码 <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               placeholder="请再次输入新密码" required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning btn-lg">
                            <i class="fas fa-save me-2"></i>重置密码
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- 成功页面 -->
                <?php if ($step === 'success'): ?>
                <div class="text-center py-4">
                    <div class="mb-4" style="font-size: 4rem; color: #28a745;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h4 class="text-success mb-3">密码重置成功！</h4>
                    <p class="text-muted mb-4">您可以使用新密码登录您的账号了。</p>
                    <div class="d-grid gap-2">
                        <a href="<?php echo BASE_PATH; ?>/login.php" class="btn btn-success btn-lg">
                            <i class="fas fa-sign-in-alt me-2"></i>立即登录
                        </a>
                        <a href="<?php echo BASE_PATH; ?>/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-2"></i>返回首页
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <div class="text-center mt-3">
                    <a href="<?php echo BASE_PATH; ?>/login.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left me-1"></i>返回登录
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.steps {
    position: relative;
}

.steps::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 0;
    right: 0;
    height: 2px;
    background: #dee2e6;
    z-index: 1;
}

.step-item {
    text-align: center;
    position: relative;
    z-index: 2;
    flex: 1;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #dee2e6;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
    font-weight: bold;
    border: 3px solid white;
}

.step-item.active .step-number {
    background: #ffc107;
    color: white;
}

.step-label {
    font-size: 0.875rem;
    color: #6c757d;
}

.step-item.active .step-label {
    color: #ffc107;
    font-weight: bold;
}
</style>

<script>
let countdownTimer;
let countdownSeconds = 300;

function sendVerificationCode(type) {
    const email = '<?php echo escape($_SESSION['reset_password_email'] ?? ''); ?>';
    const sendBtn = document.getElementById('sendCodeBtn');
    const codeText = document.getElementById('codeText');
    const countdown = document.getElementById('countdown');
    
    if (!email) {
        alert('邮箱地址无效，请返回上一步重新输入');
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

// 密码确认验证
document.addEventListener('DOMContentLoaded', function() {
    const confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>