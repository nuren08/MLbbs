<?php
// 获取所有用户（用于选择收件人）
$all_users = $pdo->query("SELECT id, username, nickname, email FROM users WHERE status = 1 ORDER BY register_time DESC")->fetchAll();

// 处理发送消息
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message_type = escape($_POST['message_type']); // email 或 site_message
    $recipient_type = escape($_POST['recipient_type']); // all 或 specific
    $subject = escape($_POST['subject']);
    $content = escape($_POST['content']);
    
    $sent_count = 0;
    $failed_count = 0;
    
    try {
        // 确定收件人列表
        $recipients = [];
        
        if ($recipient_type === 'all') {
            // 所有用户
            $stmt = $pdo->query("SELECT id, email FROM users WHERE status = 1");
            $recipients = $stmt->fetchAll();
        } else {
            // 特定用户
            $specific_users = $_POST['specific_users'] ?? [];
            foreach ($specific_users as $user_id) {
                $user_id = intval($user_id);
                $stmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ? AND status = 1");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                if ($user) {
                    $recipients[] = $user;
                }
            }
        }
        
        // 发送消息
        foreach ($recipients as $recipient) {
            if ($message_type === 'email') {
                // 发送邮件
                $email_sent = sendAdminEmail($recipient['email'], $subject, $content);
                
                if ($email_sent) {
                    $sent_count++;
                    
                    // 记录邮件发送
                    $log = $pdo->prepare("INSERT INTO email_logs (to_email, subject, content, type, status) VALUES (?, ?, ?, 'admin_broadcast', 'sent')");
                    $log->execute([$recipient['email'], $subject, $content]);
                } else {
                    $failed_count++;
                    
                    // 记录发送失败
                    $log = $pdo->prepare("INSERT INTO email_logs (to_email, subject, content, type, status, error_message) VALUES (?, ?, ?, 'admin_broadcast', 'failed', '发送失败')");
                    $log->execute([$recipient['email'], $subject, $content]);
                }
            } else {
                // 发送站内信
                $stmt = $pdo->prepare("INSERT INTO site_messages (from_user_id, to_user_id, title, content, message_type) VALUES (?, ?, ?, ?, 'admin')");
                $stmt->execute([$_SESSION['user_id'], $recipient['id'], $subject, $content]);
                $sent_count++;
            }
        }
        
        $message = "消息发送完成！成功: {$sent_count} 条，失败: {$failed_count} 条";
        $message_type = $failed_count > 0 ? 'warning' : 'success';
        echo "<script>showMessage('{$message}', '{$message_type}');</script>";
        
    } catch (PDOException $e) {
        echo '<script>showMessage("发送失败: ' . $e->getMessage() . '", "error");</script>';
    }
}
?>

<div class="page-header">
    <h2>发送消息</h2>
    <p class="page-description">向用户发送邮件或站内信通知</p>
</div>

<div class="card">
    <h3>发送新消息</h3>
    <form method="POST" id="sendMessageForm">
        <div class="form-grid">
            <div class="form-group">
                <label>消息类型 *</label>
                <select name="message_type" id="message_type" required>
                    <option value="site_message">站内信</option>
                    <option value="email">邮件</option>
                </select>
                <small class="form-text" id="message_type_help">
                    站内信：用户登录后在站内查看
                </small>
            </div>
            
            <div class="form-group">
                <label>收件人类型 *</label>
                <select name="recipient_type" id="recipient_type" required>
                    <option value="all">所有用户</option>
                    <option value="specific">特定用户</option>
                </select>
            </div>
            
            <div class="form-group full-width" id="specific_users_group" style="display: none;">
                <label>选择用户</label>
                <select name="specific_users[]" multiple size="8" style="width: 100%;">
                    <?php foreach ($all_users as $user): ?>
                        <option value="<?= $user['id'] ?>">
                            <?= $user['nickname'] ? $user['nickname'] . ' (' . $user['username'] . ')' : $user['username'] ?>
                            - <?= $user['email'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text">按住 Ctrl 键可选择多个用户</small>
            </div>
            
            <div class="form-group full-width">
                <label>主题 *</label>
                <input type="text" name="subject" required maxlength="255" placeholder="请输入消息主题">
            </div>
            
            <div class="form-group full-width">
                <label>内容 *</label>
                <textarea name="content" rows="10" required placeholder="请输入消息内容"></textarea>
                <small class="form-text">支持HTML格式</small>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="previewMessage()">
                <i class="fas fa-eye"></i> 预览
            </button>
            <button type="submit" name="send_message" class="btn btn-primary" onclick="return confirmSend()">
                <i class="fas fa-paper-plane"></i> 发送消息
            </button>
        </div>
    </form>
</div>

<!-- 消息预览模态框 -->
<div id="previewModal" class="modal">
    <div class="modal-content large">
        <div class="modal-header">
            <h3>消息预览</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="previewContent">
                <!-- 预览内容将在这里显示 -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('previewModal')">关闭</button>
        </div>
    </div>
</div>

<!-- 发送统计 -->
<div class="card">
    <h3>发送统计</h3>
    <div class="stats-grid">
        <?php
        // 获取发送统计
        $email_stats = $pdo->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM email_logs WHERE type = 'admin_broadcast'")->fetch();
        
        $message_stats = $pdo->query("SELECT COUNT(*) as total FROM site_messages WHERE message_type = 'admin'")->fetchColumn();
        ?>
        
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="stat-info">
                <h3><?= $email_stats['total'] ?></h3>
                <p>邮件发送总数</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?= $email_stats['sent'] ?></h3>
                <p>邮件发送成功</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?= $email_stats['failed'] ?></h3>
                <p>邮件发送失败</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-comments"></i>
            </div>
            <div class="stat-info">
                <h3><?= $message_stats ?></h3>
                <p>站内信发送总数</p>
            </div>
        </div>
    </div>
</div>

<!-- 最近发送记录 -->
<div class="card">
    <h3>最近发送记录</h3>
    <div class="tabs">
        <button class="tab-button active" data-tab="email_logs">邮件记录</button>
        <button class="tab-button" data-tab="message_logs">站内信记录</button>
    </div>
    
    <div class="tab-content">
        <div class="tab-pane active" id="email_logs">
            <?php
            $email_logs = $pdo->query("SELECT * FROM email_logs WHERE type = 'admin_broadcast' ORDER BY created_at DESC LIMIT 10")->fetchAll();
            ?>
            
            <?php if (empty($email_logs)): ?>
                <div class="no-data">暂无邮件发送记录</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>收件人</th>
                                <th>主题</th>
                                <th>状态</th>
                                <th>发送时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($email_logs as $log): ?>
                                <tr>
                                    <td><?= $log['to_email'] ?></td>
                                    <td title="<?= $log['subject'] ?>">
                                        <?= mb_substr($log['subject'], 0, 30) ?>
                                        <?= mb_strlen($log['subject']) > 30 ? '...' : '' ?>
                                    </td>
                                    <td>
                                        <?php if ($log['status'] == 'sent'): ?>
                                            <span class="status status-active">成功</span>
                                        <?php else: ?>
                                            <span class="status status-inactive">失败</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('Y-m-d H:i', strtotime($log['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="tab-pane" id="message_logs">
            <?php
            $message_logs = $pdo->query("SELECT sm.*, u.username, u.nickname 
                                        FROM site_messages sm 
                                        LEFT JOIN users u ON sm.to_user_id = u.id 
                                        WHERE sm.message_type = 'admin' 
                                        ORDER BY sm.created_at DESC LIMIT 10")->fetchAll();
            ?>
            
            <?php if (empty($message_logs)): ?>
                <div class="no-data">暂无站内信发送记录</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>收件人</th>
                                <th>标题</th>
                                <th>阅读状态</th>
                                <th>发送时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($message_logs as $log): ?>
                                <tr>
                                    <td><?= $log['nickname'] ?? $log['username'] ?></td>
                                    <td title="<?= $log['title'] ?>">
                                        <?= mb_substr($log['title'], 0, 30) ?>
                                        <?= mb_strlen($log['title']) > 30 ? '...' : '' ?>
                                    </td>
                                    <td>
                                        <?php if ($log['is_read']): ?>
                                            <span class="status status-active">已读</span>
                                        <?php else: ?>
                                            <span class="status status-inactive">未读</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('Y-m-d H:i', strtotime($log['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// 消息类型切换
document.getElementById('message_type').addEventListener('change', function() {
    const helpText = document.getElementById('message_type_help');
    if (this.value === 'email') {
        helpText.textContent = '邮件：直接发送到用户注册邮箱';
    } else {
        helpText.textContent = '站内信：用户登录后在站内查看';
    }
});

// 收件人类型切换
document.getElementById('recipient_type').addEventListener('change', function() {
    const specificUsersGroup = document.getElementById('specific_users_group');
    if (this.value === 'specific') {
        specificUsersGroup.style.display = 'block';
    } else {
        specificUsersGroup.style.display = 'none';
    }
});

// 标签页切换
document.querySelectorAll('.tab-button').forEach(button => {
    button.addEventListener('click', function() {
        // 移除所有active类
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
        
        // 添加active类
        this.classList.add('active');
        const tabId = this.getAttribute('data-tab');
        document.getElementById(tabId).classList.add('active');
    });
});

function previewMessage() {
    const form = document.getElementById('sendMessageForm');
    const subject = form.querySelector('input[name="subject"]').value;
    const content = form.querySelector('textarea[name="content"]').value;
    const messageType = form.querySelector('select[name="message_type"]').value;
    
    if (!subject || !content) {
        alert('请先填写主题和内容');
        return;
    }
    
    let previewHtml = '';
    
    if (messageType === 'email') {
        previewHtml = `
            <div class="email-preview">
                <div class="email-header">
                    <strong>发件人：</strong>ML论坛 &lt;noreply@mlbbs.com&gt;<br>
                    <strong>主题：</strong>${subject}
                </div>
                <div class="email-body">
                    ${content}
                </div>
            </div>
        `;
    } else {
        previewHtml = `
            <div class="message-preview">
                <div class="message-header">
                    <h4>${subject}</h4>
                    <div class="message-meta">
                        <span>发件人：系统管理员</span>
                        <span>时间：${new Date().toLocaleString()}</span>
                    </div>
                </div>
                <div class="message-content">
                    ${content}
                </div>
            </div>
        `;
    }
    
    document.getElementById('previewContent').innerHTML = previewHtml;
    openModal('previewModal');
}

function confirmSend() {
    const recipientType = document.getElementById('recipient_type').value;
    const messageType = document.getElementById('message_type').value;
    
    let message = '';
    if (recipientType === 'all') {
        message = `确定要向所有用户发送${messageType === 'email' ? '邮件' : '站内信'}吗？`;
    } else {
        const selectedUsers = document.querySelector('select[name="specific_users[]"]').selectedOptions;
        if (selectedUsers.length === 0) {
            alert('请至少选择一个用户');
            return false;
        }
        message = `确定要向选中的 ${selectedUsers.length} 个用户发送${messageType === 'email' ? '邮件' : '站内信'}吗？`;
    }
    
    return confirm(message);
}

function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// 关闭模态框
document.querySelectorAll('.modal-close').forEach(button => {
    button.addEventListener('click', function() {
        this.closest('.modal').style.display = 'none';
    });
});

// 点击模态框外部关闭
window.addEventListener('click', function(event) {
    document.querySelectorAll('.modal').forEach(modal => {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<style>
.full-width {
    grid-column: 1 / -1;
}

.tabs {
    display: flex;
    border-bottom: 1px solid #dee2e6;
    margin-bottom: 20px;
}

.tab-button {
    background: none;
    border: none;
    padding: 10px 20px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.3s ease;
}

.tab-button.active {
    border-bottom-color: #007bff;
    color: #007bff;
    font-weight: bold;
}

.tab-button:hover:not(.active) {
    background: #f8f9fa;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

.email-preview, .message-preview {
    border: 1px solid #dee2e6;
    border-radius: 5px;
    overflow: hidden;
}

.email-header, .message-header {
    background: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid #dee2e6;
}

.message-header h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.message-meta {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: #6c757d;
}

.email-body, .message-content {
    padding: 20px;
    line-height: 1.6;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .tabs {
        flex-direction: column;
    }
    
    .tab-button {
        text-align: left;
        border-bottom: 1px solid #dee2e6;
        border-left: 2px solid transparent;
    }
    
    .tab-button.active {
        border-left-color: #007bff;
        border-bottom-color: #dee2e6;
    }
}
</style>

<?php
// 发送管理员邮件函数
function sendAdminEmail($to_email, $subject, $content) {
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
    
    // 添加邮件图片
    $image1_url = $settings['email_image1_url'] ?? '';
    $image2_url = $settings['email_image2_url'] ?? '';
    
    $full_content = '';
    
    if (!empty($image1_url)) {
        $full_content .= "<img src='{$image1_url}' alt='' style='max-width:100%;'><br><br>";
    }
    
    $full_content .= $content;
    
    if (!empty($image2_url)) {
        $full_content .= "<br><br><img src='{$image2_url}' alt='' style='max-width:100%;'>";
    }
    
    // 使用简单的mail()函数发送邮件
    $headers = [
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    return mail($to_email, $subject, $full_content, implode("\r\n", $headers));
}
?>