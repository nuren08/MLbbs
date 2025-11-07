<?php
require_once __DIR__ . '/includes/config.php';

// 检查登录状态
if (!isLoggedIn()) {
    redirect(BASE_PATH . '/login.php');
}

$currentUser = getCurrentUser();
$error = '';
$success = '';

// 获取对话列表
try {
    // 获取所有与当前用户有私信往来的用户
    $conversationsStmt = $pdo->prepare("
        SELECT 
            u.id as user_id,
            u.user_id as user_number,
            u.nickname,
            u.avatar,
            u.online_status,
            m.content as last_message,
            m.created_at as last_message_time,
            m.is_read,
            m.from_user_id = ? as is_sent_by_me
        FROM users u
        INNER JOIN (
            SELECT 
                CASE WHEN from_user_id = ? THEN to_user_id ELSE from_user_id END as other_user_id,
                MAX(created_at) as max_time
            FROM messages 
            WHERE from_user_id = ? OR to_user_id = ?
            GROUP BY other_user_id
        ) conv ON u.id = conv.other_user_id
        INNER JOIN messages m ON m.created_at = conv.max_time
        WHERE u.status = 1
        ORDER BY m.created_at DESC
    ");
    $conversationsStmt->execute([$currentUser['id'], $currentUser['id'], $currentUser['id'], $currentUser['id']]);
    $conversations = $conversationsStmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("获取对话列表错误: " . $e->getMessage());
    $conversations = [];
}

// 获取特定对话的消息
$selected_user_id = $_GET['user_id'] ?? 0;
$messages = [];
$selectedUser = null;

if ($selected_user_id) {
    try {
        // 获取对方用户信息
        $userStmt = $pdo->prepare("SELECT id, user_id, nickname, avatar, allow_follow FROM users WHERE id = ? AND status = 1");
        $userStmt->execute([$selected_user_id]);
        $selectedUser = $userStmt->fetch();
        
        if ($selectedUser) {
            // 检查是否被拉黑
            $blockStmt = $pdo->prepare("SELECT id FROM user_blocks WHERE user_id = ? AND blocked_user_id = ?");
            $blockStmt->execute([$selectedUser['id'], $currentUser['id']]);
            $isBlockedByUser = $blockStmt->fetch();
            
            if ($isBlockedByUser) {
                $error = '您已被该用户拉黑，无法发送消息';
            }
            
            // 获取消息记录
            $messagesStmt = $pdo->prepare("
                SELECT m.*, 
                       u_from.nickname as from_nickname,
                       u_from.avatar as from_avatar,
                       u_to.nickname as to_nickname,
                       u_to.avatar as to_avatar
                FROM messages m
                LEFT JOIN users u_from ON m.from_user_id = u_from.id
                LEFT JOIN users u_to ON m.to_user_id = u_to.id
                WHERE (m.from_user_id = ? AND m.to_user_id = ?) 
                   OR (m.from_user_id = ? AND m.to_user_id = ?)
                ORDER BY m.created_at ASC
            ");
            $messagesStmt->execute([
                $currentUser['id'], $selected_user_id,
                $selected_user_id, $currentUser['id']
            ]);
            $messages = $messagesStmt->fetchAll();
            
            // 标记消息为已读
            $updateStmt = $pdo->prepare("
                UPDATE messages 
                SET is_read = 1, read_at = NOW() 
                WHERE to_user_id = ? AND from_user_id = ? AND is_read = 0
            ");
            $updateStmt->execute([$currentUser['id'], $selected_user_id]);
        }
        
    } catch (PDOException $e) {
        error_log("获取消息错误: " . $e->getMessage());
        $error = '加载消息失败';
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="row">
    <!-- 左侧对话列表 -->
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-comments me-2"></i>私信</h5>
                <span class="badge bg-light text-primary"><?php echo count($conversations); ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($conversations)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-comment-slash fa-3x mb-3"></i>
                    <p>暂无私信对话</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($conversations as $conv): ?>
                    <a href="?user_id=<?php echo $conv['user_id']; ?>" 
                       class="list-group-item list-group-item-action <?php echo $selected_user_id == $conv['user_id'] ? 'active' : ''; ?>">
                        <div class="d-flex align-items-center">
                            <img src="<?php echo $conv['avatar'] ?: (ASSETS_PATH . '/images/tx_ml.png'); ?>" 
                                 alt="头像" class="rounded-circle me-3" width="40" height="40">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h6 class="mb-1"><?php echo escape($conv['nickname']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo date('m-d H:i', strtotime($conv['last_message_time'])); ?>
                                    </small>
                                </div>
                                <p class="mb-1 text-truncate" style="max-width: 200px;">
                                    <?php if ($conv['is_sent_by_me']): ?>
                                    <span class="text-muted">我：</span>
                                    <?php endif; ?>
                                    <?php echo escape($conv['last_message']); ?>
                                </p>
                                <?php if (!$conv['is_read'] && !$conv['is_sent_by_me']): ?>
                                <span class="badge bg-danger rounded-pill">新</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- 右侧消息区域 -->
    <div class="col-md-8">
        <div class="card shadow-sm h-100">
            <?php if ($selectedUser): ?>
            <!-- 消息头部 -->
            <div class="card-header bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <img src="<?php echo $selectedUser['avatar'] ?: (ASSETS_PATH . '/images/tx_ml.png'); ?>" 
                             alt="头像" class="rounded-circle me-3" width="40" height="40">
                        <div>
                            <h6 class="mb-0"><?php echo escape($selectedUser['nickname']); ?></h6>
                            <small class="text-muted">ID: <?php echo $selectedUser['user_id']; ?></small>
                        </div>
                    </div>
                    <div>
                        <a href="<?php echo BASE_PATH; ?>/user_profile.php?id=<?php echo $selectedUser['user_id']; ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-user me-1"></i>查看资料
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- 消息内容 -->
            <div class="card-body message-container" style="height: 400px; overflow-y: auto;">
                <?php if (empty($messages)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-comment-medical fa-2x mb-3"></i>
                    <p>开始和 <?php echo escape($selectedUser['nickname']); ?> 的对话</p>
                </div>
                <?php else: ?>
                <div class="message-list">
                    <?php foreach ($messages as $message): ?>
                    <div class="message-item mb-3 <?php echo $message['from_user_id'] == $currentUser['id'] ? 'text-end' : ''; ?>">
                        <div class="d-flex <?php echo $message['from_user_id'] == $currentUser['id'] ? 'justify-content-end' : ''; ?>">
                            <?php if ($message['from_user_id'] != $currentUser['id']): ?>
                            <img src="<?php echo $message['from_avatar'] ?: (ASSETS_PATH . '/images/tx_ml.png'); ?>" 
                                 alt="头像" class="rounded-circle me-2" width="32" height="32">
                            <?php endif; ?>
                            
                            <div class="message-bubble <?php echo $message['from_user_id'] == $currentUser['id'] ? 'bg-primary text-white' : 'bg-light'; ?> 
                                                      rounded p-3" style="max-width: 70%;">
                                <div class="message-content">
                                    <?php echo nl2br(escape($message['content'])); ?>
                                </div>
                                <div class="message-time small mt-1 <?php echo $message['from_user_id'] == $currentUser['id'] ? 'text-white-50' : 'text-muted'; ?>">
                                    <?php echo date('H:i', strtotime($message['created_at'])); ?>
                                    <?php if ($message['from_user_id'] == $currentUser['id'] && $message['is_read']): ?>
                                    <i class="fas fa-check ms-1" title="已读"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($message['from_user_id'] == $currentUser['id']): ?>
                            <img src="<?php echo $currentUser['avatar'] ?: (ASSETS_PATH . '/images/tx_ml.png'); ?>" 
                                 alt="我的头像" class="rounded-circle ms-2" width="32" height="32">
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 消息输入框 -->
            <div class="card-footer">
                <?php if (isset($isBlockedByUser) && $isBlockedByUser): ?>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-ban me-2"></i>您已被该用户拉黑，无法发送消息
                </div>
                <?php else: ?>
                <form method="POST" action="<?php echo BASE_PATH; ?>/send_message.php" id="messageForm">
                    <input type="hidden" name="to_user_id" value="<?php echo $selectedUser['id']; ?>">
                    <div class="input-group">
                        <textarea class="form-control" name="message" id="messageInput" 
                                  placeholder="输入消息内容..." rows="2" required></textarea>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            私信内容若对方15天未接收，将从服务器删除，已接收的消息将立即从服务器删除，仅保存在用户设备本地。
                        </small>
                    </div>
                </form>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <!-- 未选择对话时的提示 -->
            <div class="card-body d-flex align-items-center justify-content-center" style="height: 500px;">
                <div class="text-center text-muted">
                    <i class="fas fa-comments fa-4x mb-3"></i>
                    <h5>选择对话开始聊天</h5>
                    <p>从左侧选择一位用户查看消息记录</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.message-bubble {
    position: relative;
    word-wrap: break-word;
}

.message-bubble.bg-primary:after {
    content: '';
    position: absolute;
    top: 10px;
    right: -8px;
    width: 0;
    height: 0;
    border: 8px solid transparent;
    border-left-color: #007bff;
}

.message-bubble.bg-light:after {
    content: '';
    position: absolute;
    top: 10px;
    left: -8px;
    width: 0;
    height: 0;
    border: 8px solid transparent;
    border-right-color: #f8f9fa;
}

.message-container {
    scroll-behavior: smooth;
}

.message-list {
    min-height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
}
</style>

<script>
// 自动滚动到消息底部
function scrollToBottom() {
    const container = document.querySelector('.message-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// 页面加载完成后滚动到底部
document.addEventListener('DOMContentLoaded', function() {
    scrollToBottom();
    
    // 如果有选中的对话，设置自动刷新
    <?php if ($selected_user_id): ?>
    setInterval(loadNewMessages, 5000); // 每5秒检查新消息
    <?php endif; ?>
});

// 加载新消息
function loadNewMessages() {
    const lastMessageTime = document.querySelector('.message-time:last-child')?.textContent.trim();
    
    fetch('<?php echo BASE_PATH; ?>/ajax/get_new_messages.php?user_id=<?php echo $selected_user_id; ?>&last_time=' + encodeURIComponent(lastMessageTime))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages.length > 0) {
                // 添加新消息到页面
                data.messages.forEach(message => {
                    addMessageToPage(message);
                });
                scrollToBottom();
            }
        })
        .catch(error => console.error('加载新消息失败:', error));
}

// 添加消息到页面
function addMessageToPage(message) {
    const messageList = document.querySelector('.message-list');
    const isMyMessage = message.from_user_id == <?php echo $currentUser['id']; ?>;
    
    const messageHtml = `
        <div class="message-item mb-3 ${isMyMessage ? 'text-end' : ''}">
            <div class="d-flex ${isMyMessage ? 'justify-content-end' : ''}">
                ${!isMyMessage ? `
                <img src="${message.from_avatar || '<?php echo ASSETS_PATH; ?>/images/tx_ml.png'}" 
                     alt="头像" class="rounded-circle me-2" width="32" height="32">
                ` : ''}
                
                <div class="message-bubble ${isMyMessage ? 'bg-primary text-white' : 'bg-light'} rounded p-3" style="max-width: 70%;">
                    <div class="message-content">
                        ${escapeHtml(message.content)}
                    </div>
                    <div class="message-time small mt-1 ${isMyMessage ? 'text-white-50' : 'text-muted'}">
                        ${message.time}
                        ${isMyMessage && message.is_read ? '<i class="fas fa-check ms-1" title="已读"></i>' : ''}
                    </div>
                </div>
                
                ${isMyMessage ? `
                <img src="<?php echo $currentUser['avatar'] ?: (ASSETS_PATH . '/images/tx_ml.png'); ?>" 
                     alt="我的头像" class="rounded-circle ms-2" width="32" height="32">
                ` : ''}
            </div>
        </div>
    `;
    
    messageList.insertAdjacentHTML('beforeend', messageHtml);
}

// HTML转义
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 消息表单提交
document.getElementById('messageForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch(this.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 清空输入框
            document.getElementById('messageInput').value = '';
            
            // 如果是第一次发送消息，刷新页面
            if (data.is_new_conversation) {
                location.reload();
            } else {
                // 添加消息到页面
                addMessageToPage(data.message);
                scrollToBottom();
            }
        } else {
            alert(data.message || '发送失败');
        }
    })
    .catch(error => {
        console.error('发送消息失败:', error);
        alert('发送失败，请稍后重试');
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>