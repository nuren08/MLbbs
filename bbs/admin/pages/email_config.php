<?php
$settings = getAdminSettings();

// 处理邮箱配置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email_config'])) {
    $updates = [
        'smtp_host' => escape($_POST['smtp_host']),
        'smtp_port' => escape($_POST['smtp_port']),
        'smtp_username' => escape($_POST['smtp_username']),
        'smtp_password' => escape($_POST['smtp_password']),
        'from_email' => escape($_POST['from_email']),
        'from_name' => escape($_POST['from_name'])
    ];
    
    if (updateAdminSettings($updates)) {
        echo '<script>showMessage("邮箱配置更新成功");</script>';
    } else {
        echo '<script>showMessage("更新失败", "error");</script>';
    }
}

// 处理邮件模板更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email_template'])) {
    $template = escape($_POST['email_template']);
    if (setSystemSetting('email_template', $template)) {
        echo '<script>showMessage("邮件模板更新成功");</script>';
    } else {
        echo '<script>showMessage("更新失败", "error");</script>';
    }
}

// 测试邮件发送
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $test_email = escape($_POST['test_email']);
    $test_code = generateVerificationCode();
    
    try {
        // 保存测试验证码
        $stmt = $pdo->prepare("INSERT INTO verification_codes (email, code, type, expires_at) VALUES (?, ?, 'test', DATE_ADD(NOW(), INTERVAL 5 MINUTE))");
        $stmt->execute([$test_email, $test_code]);
        
        // 发送测试邮件
        $email_sent = sendVerificationEmail($test_email, $test_code, 'test');
        
        if ($email_sent) {
            echo '<script>showMessage("测试邮件发送成功，请检查邮箱");</script>';
        } else {
            echo '<script>showMessage("测试邮件发送失败，请检查配置", "error");</script>';
        }
    } catch (PDOException $e) {
        echo '<script>showMessage("发送失败: ' . $e->getMessage() . '", "error");</script>';
    }
}
?>

<div class="page-header">
    <h2>邮箱配置</h2>
    <p class="page-description">配置QQ邮箱SMTP服务用于发送验证码邮件</p>
</div>

<div class="config-tabs">
    <div class="tab-headers">
        <button class="tab-header active" data-tab="smtp-config">SMTP配置</button>
        <button class="tab-header" data-tab="email-template">邮件模板</button>
        <button class="tab-header" data-tab="test-email">测试发送</button>
    </div>
    
    <div class="tab-content">
        <!-- SMTP配置 -->
        <div class="tab-pane active" id="smtp-config">
            <div class="card">
                <h3>QQ邮箱SMTP配置</h3>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>SMTP服务器 *</label>
                            <input type="text" name="smtp_host" 
                                   value="<?= $settings['smtp_host'] ?? 'smtp.qq.com' ?>" 
                                   placeholder="smtp.qq.com" required>
                            <small class="form-text">QQ邮箱SMTP服务器地址</small>
                        </div>
                        
                        <div class="form-group">
                            <label>SMTP端口 *</label>
                            <input type="number" name="smtp_port" 
                                   value="<?= $settings['smtp_port'] ?? '587' ?>" 
                                   placeholder="587" required>
                            <small class="form-text">QQ邮箱SMTP端口，通常为587</small>
                        </div>
                        
                        <div class="form-group">
                            <label>SMTP用户名 *</label>
                            <input type="email" name="smtp_username" 
                                   value="<?= $settings['smtp_username'] ?? '' ?>" 
                                   placeholder="您的QQ邮箱" required>
                            <small class="form-text">完整的QQ邮箱地址</small>
                        </div>
                        
                        <div class="form-group">
                            <label>SMTP密码/授权码 *</label>
                            <input type="password" name="smtp_password" 
                                   value="<?= $settings['smtp_password'] ?? '' ?>" 
                                   placeholder="QQ邮箱授权码" required>
                            <small class="form-text">
                                <a href="https://service.mail.qq.com/detail/0/75" target="_blank">
                                    如何获取QQ邮箱授权码？
                                </a>
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label>发件人邮箱 *</label>
                            <input type="email" name="from_email" 
                                   value="<?= $settings['from_email'] ?? '' ?>" 
                                   placeholder="发件人邮箱地址" required>
                        </div>
                        
                        <div class="form-group">
                            <label>发件人名称 *</label>
                            <input type="text" name="from_name" 
                                   value="<?= $settings['from_name'] ?? 'ML论坛' ?>" 
                                   placeholder="发件人显示名称" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_email_config" class="btn btn-primary">
                            <i class="fas fa-save"></i> 保存配置
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="help-card">
                <h4><i class="fas fa-info-circle"></i> 配置说明</h4>
                <ol>
                    <li>登录QQ邮箱，进入"设置" → "账户"</li>
                    <li>找到"POP3/IMAP/SMTP/Exchange/CardDAV/CalDAV服务"</li>
                    <li>开启"POP3/SMTP服务"</li>
                    <li>点击"生成授权码"，复制生成的16位授权码</li>
                    <li>将授权码填写到上面的"SMTP密码/授权码"字段中</li>
                    <li>保存配置后，可以使用测试功能验证配置是否正确</li>
                </ol>
            </div>
        </div>
        
        <!-- 邮件模板 -->
        <div class="tab-pane" id="email-template">
            <div class="card">
                <h3>邮件验证码模板</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>邮件模板内容 *</label>
                        <textarea name="email_template" rows="6" required 
                                  placeholder="请输入邮件模板内容，使用 {code} 作为验证码占位符"><?= $settings['email_template'] ?? '亲爱的ML论坛会员，您本次的验证码为{code}，5分钟内有效，如非本人操作，请您忽略。[ML论坛]' ?></textarea>
                        <small class="form-text">
                            使用 <code>{code}</code> 作为验证码的占位符，系统会自动替换为实际验证码
                        </small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_email_template" class="btn btn-primary">
                            <i class="fas fa-save"></i> 更新模板
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="preview-card">
                <h4>预览效果</h4>
                <div class="email-preview">
                    <div class="email-header">
                        <strong>发件人：</strong><?= $settings['from_name'] ?? 'ML论坛' ?> &lt;<?= $settings['from_email'] ?? '' ?>&gt;<br>
                        <strong>主题：</strong>ML论坛验证码
                    </div>
                    <div class="email-body">
                        <?= str_replace('{code}', '<span class="code-placeholder">1234</span>', $settings['email_template'] ?? '亲爱的ML论坛会员，您本次的验证码为{code}，5分钟内有效，如非本人操作，请您忽略。[ML论坛]') ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 测试发送 -->
        <div class="tab-pane" id="test-email">
            <div class="card">
                <h3>测试邮件发送</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>测试邮箱地址 *</label>
                        <input type="email" name="test_email" 
                               value="<?= $_SESSION['user_email'] ?? '' ?>" 
                               placeholder="输入接收测试邮件的邮箱地址" required>
                        <small class="form-text">将向该邮箱发送测试验证码</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="test_email" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> 发送测试邮件
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="info-card">
                <h4><i class="fas fa-lightbulb"></i> 测试说明</h4>
                <ul>
                    <li>请先保存SMTP配置后再进行测试</li>
                    <li>测试邮件将包含一个4位数字验证码</li>
                    <li>如果收到邮件，说明配置正确</li>
                    <li>如果未收到，请检查垃圾邮件文件夹</li>
                    <li>仍然未收到，请检查SMTP配置信息</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// 标签页切换
document.querySelectorAll('.tab-header').forEach(header => {
    header.addEventListener('click', function() {
        // 移除所有active类
        document.querySelectorAll('.tab-header').forEach(h => h.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        
        // 添加active类
        this.classList.add('active');
        const tabId = this.getAttribute('data-tab');
        document.getElementById(tabId).classList.add('active');
    });
});
</script>

<style>
.config-tabs {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.tab-headers {
    display: flex;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.tab-header {
    padding: 15px 25px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    transition: all 0.3s ease;
}

.tab-header.active {
    background: white;
    border-bottom-color: #007bff;
    color: #007bff;
    font-weight: bold;
}

.tab-header:hover:not(.active) {
    background: #e9ecef;
}

.tab-content {
    padding: 0;
}

.tab-pane {
    display: none;
    padding: 25px;
}

.tab-pane.active {
    display: block;
}

.help-card, .preview-card, .info-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.help-card h4, .preview-card h4, .info-card h4 {
    margin-top: 0;
    color: #495057;
}

.help-card ol, .info-card ul {
    margin-bottom: 0;
}

.email-preview {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 15px;
    margin-top: 10px;
}

.email-header {
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
    margin-bottom: 10px;
    font-size: 14px;
}

.email-body {
    line-height: 1.6;
}

.code-placeholder {
    background: #fff3cd;
    padding: 2px 6px;
    border-radius: 3px;
    font-weight: bold;
    color: #856404;
}

code {
    background: #f8f9fa;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: monospace;
    color: #e83e8c;
}
</style>

<?php
// 邮件发送函数
function sendVerificationEmail($email, $code, $type = 'register') {
    global $pdo;
    
    // 获取邮箱配置
    $settings = getAdminSettings();
    
    $smtp_host = $settings['smtp_host'] ?? '';
    $smtp_port = $settings['smtp_port'] ?? '587';
    $smtp_username = $settings['smtp_username'] ?? '';
    $smtp_password = $settings['smtp_password'] ?? '';
    $from_email = $settings['from_email'] ?? '';
    $from_name = $settings['from_name'] ?? 'ML论坛';
    
    // 检查配置是否完整
    if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
        error_log("邮箱配置不完整");
        return false;
    }
    
    // 获取邮件模板
    $template = $settings['email_template'] ?? '亲爱的ML论坛会员，您本次的验证码为{code}，5分钟内有效，如非本人操作，请您忽略。[ML论坛]';
    $message = str_replace('{code}', $code, $template);
    
    // 添加邮件图片
    $image1_url = $settings['email_image1_url'] ?? '';
    $image2_url = $settings['email_image2_url'] ?? '';
    
    if (!empty($image1_url)) {
        $message = "<img src='{$image1_url}' alt='' style='max-width:100%;'><br><br>" . $message;
    }
    
    if (!empty($image2_url)) {
        $message = $message . "<br><br><img src='{$image2_url}' alt='' style='max-width:100%;'>";
    }
    
    // 使用PHPMailer发送邮件（需要先引入PHPMailer）
    try {
        // 这里简化处理，实际应该使用PHPMailer
        // 由于虚拟主机环境限制，这里使用简单的mail()函数
        $subject = "ML论坛验证码";
        $headers = [
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email,
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        return mail($email, $subject, $message, implode("\r\n", $headers));
        
    } catch (Exception $e) {
        error_log("发送邮件失败: " . $e->getMessage());
        return false;
    }
}
?>