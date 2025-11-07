<?php
require_once 'includes/config.php';

// 获取帖子ID
$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20; // 每页回帖数量

// 获取帖子信息
$stmt = $pdo->prepare("
    SELECT p.*, 
           u.username, u.nickname, u.avatar, u.level, u.exp,
           c.name as category_name, sc.name as subcategory_name,
           c.rule_type
    FROM posts p
    LEFT JOIN users u ON p.author_id = u.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN categories sc ON p.subcategory_id = sc.id
    WHERE p.id = ? AND p.status = 'approved'
");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    die("帖子不存在或已被删除");
}

// 更新浏览量
$stmt = $pdo->prepare("UPDATE posts SET views = views + 1 WHERE id = ?");
$stmt->execute([$post_id]);

// 获取规则信息
$rules = $forum->getCategoryRules($post['category_id']);

// 获取附件
$attachments = [];
if ($post['rule_type'] !== 'promotion') {
    $stmt = $pdo->prepare("
        SELECT * FROM attachments 
        WHERE post_id = ? 
        ORDER BY created_at
    ");
    $stmt->execute([$post_id]);
    $attachments = $stmt->fetchAll();
}

// 获取回帖
$offset = ($page - 1) * $per_page;
$stmt = $pdo->prepare("
    SELECT SQL_CALC_FOUND_ROWS 
           r.*, u.username, u.nickname, u.avatar, u.level, u.exp
    FROM replies r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.post_id = ? AND r.status = 'approved'
    ORDER BY r.created_at ASC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $post_id, PDO::PARAM_INT);
$stmt->bindValue(2, $per_page, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$replies = $stmt->fetchAll();

$total_stmt = $pdo->query("SELECT FOUND_ROWS()");
$total_replies = $total_stmt->fetchColumn();
$total_pages = ceil($total_replies / $per_page);

// 检查收藏状态
$is_favorited = false;
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
    $is_favorited = isPostFavorited($currentUser['id'], $post_id);
}

// 处理回帖提交
$reply_error = '';
$reply_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply'])) {
    if (!isLoggedIn()) {
        $reply_error = '请先登录';
    } else {
        $content = trim($_POST['content'] ?? '');
        
        if (empty($content)) {
            $reply_error = '请输入回复内容';
        } else {
            // 检查用户权限
            $permission_check = $forum->checkUserPermission($currentUser['id'], 'reply');
            if (!$permission_check['success']) {
                $reply_error = $permission_check['message'];
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // 插入回帖
                    $stmt = $pdo->prepare("
                        INSERT INTO replies (post_id, user_id, content, ip_address)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $post_id,
                        $currentUser['id'],
                        $content,
                        $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    // 处理回帖附件（仅树洞规则）
                    $reply_attachments = [];
                    if ($post['rule_type'] === 'treehole' && isset($_FILES['reply_attachments'])) {
                        foreach ($_FILES['reply_attachments']['name'] as $key => $name) {
                            if ($_FILES['reply_attachments']['error'][$key] === UPLOAD_ERR_OK) {
                                $file_name = basename($name);
                                $file_tmp = $_FILES['reply_attachments']['tmp_name'][$key];
                                $file_size = $_FILES['reply_attachments']['size'][$key];
                                $file_type = $_FILES['reply_attachments']['type'][$key];
                                
                                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                $new_file_name = uniqid() . '.' . $file_ext;
                                $upload_path = UPLOAD_PATH . '/attachments/' . $new_file_name;
                                
                                if (move_uploaded_file($file_tmp, $upload_path)) {
                                    $reply_attachments[] = [
                                        'filename' => $file_name,
                                        'filepath' => $new_file_name,
                                        'filetype' => $file_type,
                                        'filesize' => $file_size
                                    ];
                                }
                            }
                        }
                        
                        // 保存回帖附件信息
                        if (!empty($reply_attachments)) {
                            $reply_id = $pdo->lastInsertId();
                            $stmt = $pdo->prepare("
                                INSERT INTO attachments (reply_id, user_id, filename, filepath, filetype, filesize)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            
                            foreach ($reply_attachments as $attachment) {
                                $stmt->execute([
                                    $reply_id,
                                    $currentUser['id'],
                                    $attachment['filename'],
                                    $attachment['filepath'],
                                    $attachment['filetype'],
                                    $attachment['filesize']
                                ]);
                            }
                        }
                    }
                    
                    // 奖励积分和经验
                    $user_level = getUserLevel($currentUser['exp']);
                    $level_rewards = getLevelRewards($user_level);
                    $points_earned = $level_rewards['reply'];
                    $exp_earned = $points_earned;
                    
                    $forum->updateUserPoints($currentUser['id'], $points_earned, $exp_earned);
                    
                    $pdo->commit();
                    
                    $reply_success = "回复成功！获得{$points_earned}积分和经验值";
                    
                    // 刷新页面
                    header("Location: " . BASE_PATH . "/post.php?id=" . $post_id . "&page=" . $total_pages . "#reply-" . $pdo->lastInsertId());
                    exit;
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $reply_error = '回复失败：' . $e->getMessage();
                }
            }
        }
    }
}

// 处理收藏操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['favorite_action'])) {
    if (!isLoggedIn()) {
        echo jsonResponse(['success' => false, 'message' => '请先登录']);
    }
    
    $action = $_POST['favorite_action'];
    $currentUser = getCurrentUser();
    
    if ($action === 'add') {
        $result = addToFavorites($currentUser['id'], $post_id);
        if ($result) {
            echo jsonResponse(['success' => true, 'action' => 'added']);
        } else {
            echo jsonResponse(['success' => false, 'message' => '收藏失败']);
        }
    } elseif ($action === 'remove') {
        $result = removeFromFavorites($currentUser['id'], $post_id);
        if ($result) {
            echo jsonResponse(['success' => true, 'action' => 'removed']);
        } else {
            echo jsonResponse(['success' => false, 'message' => '取消收藏失败']);
        }
    }
    exit;
}

// 获取导航分类
$categories = $forum->getCategories();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($post['title']); ?> - ML论坛</title>
    <link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>/css/style.css">
    <link rel="icon" href="<?php echo ASSETS_PATH; ?>/images/favicon_ml.png" type="image/png">
    <style>
        .post-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .post-header {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .post-title {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .post-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        
        .post-content {
            line-height: 1.8;
            font-size: 1.1rem;
            margin-bottom: 20px;
        }
        
        .attachments-section {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .attachment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .attachment-item:last-child {
            border-bottom: none;
        }
        
        .download-btn {
            background: #e91e63;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .replies-section {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .reply-form {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .reply-item {
            border-bottom: 1px solid #e9ecef;
            padding: 20px 0;
        }
        
        .reply-item:last-child {
            border-bottom: none;
        }
        
        .reply-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .reply-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .reply-author {
            font-weight: bold;
            color: #333;
        }
        
        .reply-time {
            color: #666;
            font-size: 0.9rem;
            margin-left: auto;
        }
        
        .reply-content {
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 5px;
        }
        
        .pagination a {
            display: inline-block;
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        
        .pagination a.active {
            background: #e91e63;
            color: white;
            border-color: #e91e63;
        }
        
        /* 收藏按钮样式 */
        .favorite-btn {
            position: fixed;
            right: 30px;
            top: 200px;
            z-index: 1000;
            background: none;
            border: none;
            font-size: 2.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .favorite-btn .star {
            color: #ccc;
            transition: all 0.3s ease;
        }
        
        .favorite-btn:hover .star {
            color: #ffd700;
            transform: scale(1.2);
        }
        
        .favorite-btn.favorited .star {
            color: #ffd700;
        }
        
        .editor-toolbar {
            background: white;
            border: 1px solid #ddd;
            border-bottom: none;
            padding: 10px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .editor-btn {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .editor-btn:hover {
            background: #e9ecef;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <!-- 头部 -->
    <header class="header">
        <div class="container">
            <div class="logo-section">
                <img src="<?php echo ASSETS_PATH; ?>/images/logo_ml.png" alt="ML论坛LOGO" class="logo">
                <h1 class="rainbow-text">ML论坛</h1>
            </div>
            <div class="user-section">
                <?php if (isLoggedIn()): ?>
                    <?php $user = getCurrentUser(); ?>
                    <a href="<?php echo BASE_PATH; ?>/profile.php" class="user-link">
                        <?php echo escape($user['nickname'] ?: $user['username']); ?>
                    </a>
                    <a href="<?php echo BASE_PATH; ?>/logout.php" class="logout-btn">退出</a>
                <?php else: ?>
                    <a href="<?php echo BASE_PATH; ?>/login.php" class="login-btn">登录/注册</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- 导航 -->
    <nav class="main-nav">
        <div class="container">
            <ul class="nav-list">
                <?php foreach ($categories as $category_item): ?>
                    <?php if ($category_item['parent_id'] == 0): ?>
                        <li class="nav-item">
                            <a href="<?php echo $category_item['url'] ?: (BASE_PATH . '/category.php?id=' . $category_item['id']); ?>" 
                               class="nav-link">
                                <?php echo escape($category_item['name']); ?>
                            </a>
                            <?php if (!empty($category_item['children'])): ?>
                                <ul class="subnav">
                                    <?php foreach ($category_item['children'] as $child): ?>
                                        <li>
                                            <a href="<?php echo BASE_PATH; ?>/category.php?id=<?php echo $child['id']; ?>">
                                                <?php echo escape($child['name']); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </nav>

    <!-- 收藏按钮 -->
    <?php if (isLoggedIn()): ?>
    <button class="favorite-btn <?php echo $is_favorited ? 'favorited' : ''; ?>" 
            id="favoriteBtn" title="<?php echo $is_favorited ? '取消收藏' : '收藏帖子'; ?>">
        <span class="star">★</span>
    </button>
    <?php endif; ?>

    <div class="post-container">
        <!-- 帖子内容 -->
        <div class="post-header">
            <h1 class="post-title"><?php echo escape($post['title']); ?></h1>
            
            <div class="post-meta">
                <span>作者: <?php echo $post['is_anonymous'] ? '匿名用户' : escape($post['nickname'] ?: $post['username']); ?></span>
                <span>分类: <?php echo escape($post['category_name']); ?> &gt; <?php echo escape($post['subcategory_name']); ?></span>
                <span>浏览: <?php echo $post['views'] + 1; ?></span>
                <span>时间: <?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></span>
                <?php if ($post['ip_address']): ?>
                    <span>IP: <?php echo $post['ip_address']; ?></span>
                <?php endif; ?>
            </div>
            
            <div class="post-content">
                <?php echo nl2br(escape($post['content'])); ?>
            </div>
            
            <!-- 附件区域 -->
            <?php if (!empty($attachments)): ?>
            <div class="attachments-section">
                <h4>附件下载</h4>
                <?php foreach ($attachments as $attachment): ?>
                    <div class="attachment-item">
                        <div>
                            <strong><?php echo escape($attachment['filename']); ?></strong>
                            <span style="color: #666; margin-left: 10px;">
                                (<?php echo round($attachment['filesize'] / 1024, 2); ?> KB)
                            </span>
                        </div>
                        <button class="download-btn" 
                                onclick="downloadAttachment(<?php echo $attachment['id']; ?>)">
                            下载 (<?php echo $rules['type'] === 'general' ? $rules['download_points'] . '积分' : '免费'; ?>)
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- 回帖区域 -->
        <div class="replies-section">
            <h3>回帖 (<?php echo $total_replies; ?>)</h3>
            
            <!-- 回帖表单 -->
            <?php if (isLoggedIn()): ?>
            <div class="reply-form">
                <?php if ($reply_error): ?>
                    <div class="alert alert-error"><?php echo $reply_error; ?></div>
                <?php endif; ?>
                
                <?php if ($reply_success): ?>
                    <div class="alert alert-success"><?php echo $reply_success; ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="editor-toolbar">
                        <?php if ($post['rule_type'] === 'treehole' || $post['rule_type'] === 'general'): ?>
                            <button type="button" class="editor-btn" onclick="formatReplyText('bold')"><b>粗体</b></button>
                            <button type="button" class="editor-btn" onclick="formatReplyText('italic')"><i>斜体</i></button>
                            <button type="button" class="editor-btn" onclick="formatReplyText('underline')"><u>下划线</u></button>
                            <button type="button" class="editor-btn" onclick="insertReplyEmoji('😊')">😊 表情</button>
                            <button type="button" class="editor-btn" onclick="insertReplyLink()">🔗 链接</button>
                        <?php endif; ?>
                    </div>
                    
                    <textarea name="content" id="replyContent" class="form-control" 
                              placeholder="<?php echo $post['rule_type'] === 'promotion' ? '请输入回复内容（仅支持文字和图片）' : '请输入回复内容'; ?>" 
                              rows="5" required></textarea>
                    
                    <!-- 回帖附件上传（仅树洞规则） -->
                    <?php if ($post['rule_type'] === 'treehole'): ?>
                    <div style="margin-top: 10px;">
                        <input type="file" name="reply_attachments[]" multiple 
                               accept="image/*,.pdf,.doc,.docx,.zip,.rar">
                        <div style="font-size: 0.8rem; color: #666;">
                            支持上传附件（树洞规则下附件下载免费）
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 15px; text-align: right;">
                        <button type="submit" name="submit_reply" class="submit-btn">
                            发表回复
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="reply-form" style="text-align: center; padding: 40px;">
                <p>请 <a href="<?php echo BASE_PATH; ?>/login.php">登录</a> 后回复</p>
            </div>
            <?php endif; ?>

            <!-- 回帖列表 -->
            <div class="replies-list">
                <?php if (empty($replies)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        暂无回复，快来抢沙发吧！
                    </div>
                <?php else: ?>
                    <?php foreach ($replies as $reply): ?>
                        <div class="reply-item" id="reply-<?php echo $reply['id']; ?>">
                            <div class="reply-header">
                                <img src="<?php echo $reply['avatar'] ?: (ASSETS_PATH . '/images/tx_ml.png'); ?>" 
                                     alt="头像" class="reply-avatar">
                                <span class="reply-author">
                                    <?php echo escape($reply['nickname'] ?: $reply['username']); ?>
                                    <small style="color: #666;">LV<?php echo getUserLevel($reply['exp']); ?></small>
                                </span>
                                <span class="reply-time">
                                    <?php echo date('Y-m-d H:i', strtotime($reply['created_at'])); ?>
                                    <?php if ($reply['ip_address']): ?>
                                        • IP: <?php echo $reply['ip_address']; ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="reply-content">
                                <?php echo nl2br(escape($reply['content'])); ?>
                            </div>
                            
                            <!-- 回帖附件 -->
                            <?php
                            $reply_attachments_stmt = $pdo->prepare("
                                SELECT * FROM attachments 
                                WHERE reply_id = ? 
                                ORDER BY created_at
                            ");
                            $reply_attachments_stmt->execute([$reply['id']]);
                            $reply_attachments = $reply_attachments_stmt->fetchAll();
                            ?>
                            
                            <?php if (!empty($reply_attachments)): ?>
                                <div class="attachments-section" style="margin-top: 10px; padding: 10px;">
                                    <?php foreach ($reply_attachments as $attachment): ?>
                                        <div class="attachment-item">
                                            <div>
                                                <strong><?php echo escape($attachment['filename']); ?></strong>
                                                <span style="color: #666; margin-left: 10px;">
                                                    (<?php echo round($attachment['filesize'] / 1024, 2); ?> KB)
                                                </span>
                                            </div>
                                            <button class="download-btn" 
                                                    onclick="downloadAttachment(<?php echo $attachment['id']; ?>)">
                                                下载免费
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?id=<?php echo $post_id; ?>&page=<?php echo $i; ?>" 
                           class="<?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 底部广告 -->
    <?php if (getSystemSetting('ad_display', '1')): ?>
    <div class="ad-banner fixed-bottom">
        <div class="ad-container">
            <?php
            $stmt = $pdo->query("SELECT * FROM ads WHERE status = 1 ORDER BY sort_order LIMIT 3");
            $ads = $stmt->fetchAll();
            if (empty($ads)): ?>
                <div class="ad-item">广告位招租</div>
            <?php else: ?>
                <?php foreach ($ads as $ad): ?>
                    <a href="<?php echo escape($ad['url']); ?>" target="_blank" class="ad-item">
                        <img src="<?php echo escape($ad['image_url']); ?>" alt="广告">
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 签到按钮 -->
    <div id="signin-btn" class="floating-signin-btn">
        <button onclick="location.href='<?php echo BASE_PATH; ?>/signin.php'" class="signin-button">签到</button>
    </div>

    <script>
    // 收藏功能
    document.getElementById('favoriteBtn').addEventListener('click', function() {
        const isFavorited = this.classList.contains('favorited');
        const action = isFavorited ? 'remove' : 'add';
        
        fetch('<?php echo BASE_PATH; ?>/post.php?id=<?php echo $post_id; ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'favorite_action=' + action
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.action === 'added') {
                    this.classList.add('favorited');
                    this.title = '取消收藏';
                    showMessage('收藏成功！', 'success');
                } else {
                    this.classList.remove('favorited');
                    this.title = '收藏帖子';
                    showMessage('已取消收藏', 'info');
                }
            } else {
                showMessage(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('收藏操作失败:', error);
            showMessage('操作失败，请重试', 'error');
        });
    });
    
    function showMessage(message, type) {
        // 创建消息提示
        const messageDiv = document.createElement('div');
        messageDiv.textContent = message;
        messageDiv.style.cssText = `
            position: fixed;
            top: 100px;
            left: 50%;
            transform: translateX(-50%);
            padding: 10px 20px;
            border-radius: 4px;
            z-index: 10000;
            color: white;
            font-weight: bold;
        `;
        
        if (type === 'success') {
            messageDiv.style.background = '#28a745';
        } else if (type === 'error') {
            messageDiv.style.background = '#dc3545';
        } else {
            messageDiv.style.background = '#17a2b8';
        }
        
        document.body.appendChild(messageDiv);
        
        setTimeout(() => {
            document.body.removeChild(messageDiv);
        }, 3000);
    }
    
    // 下载附件
    function downloadAttachment(attachmentId) {
        window.open('<?php echo BASE_PATH; ?>/download.php?id=' + attachmentId);
    }
    
    // 回帖编辑器功能
    function formatReplyText(type) {
        const textarea = document.getElementById('replyContent');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);
        
        let formattedText = '';
        switch (type) {
            case 'bold':
                formattedText = `**${selectedText}**`;
                break;
            case 'italic':
                formattedText = `*${selectedText}*`;
                break;
            case 'underline':
                formattedText = `__${selectedText}__`;
                break;
        }
        
        textarea.value = textarea.value.substring(0, start) + formattedText + textarea.value.substring(end);
        textarea.focus();
        textarea.setSelectionRange(start + formattedText.length, start + formattedText.length);
    }
    
    function insertReplyEmoji(emoji) {
        const textarea = document.getElementById('replyContent');
        const start = textarea.selectionStart;
        textarea.value = textarea.value.substring(0, start) + emoji + textarea.value.substring(start);
        textarea.focus();
        textarea.setSelectionRange(start + emoji.length, start + emoji.length);
    }
    
    function insertReplyLink() {
        const url = prompt('请输入链接地址：');
        if (url) {
            const text = prompt('请输入链接显示文本（可选）：') || url;
            const textarea = document.getElementById('replyContent');
            const start = textarea.selectionStart;
            const link = `[${text}](${url})`;
            textarea.value = textarea.value.substring(0, start) + link + textarea.value.substring(start);
            textarea.focus();
            textarea.setSelectionRange(start + link.length, start + link.length);
        }
    }
    
    // 使签到按钮可拖动
    document.addEventListener('DOMContentLoaded', function() {
        const signinBtn = document.getElementById('signin-btn');
        let isDragging = false;
        let currentX, currentY, initialX, initialY, xOffset = 0, yOffset = 0;

        signinBtn.addEventListener('mousedown', dragStart);
        document.addEventListener('mouseup', dragEnd);
        document.addEventListener('mousemove', drag);

        function dragStart(e) {
            initialX = e.clientX - xOffset;
            initialY = e.clientY - yOffset;
            if (e.target === signinBtn || e.target.classList.contains('signin-button')) {
                isDragging = true;
            }
        }

        function dragEnd() {
            initialX = currentX;
            initialY = currentY;
            isDragging = false;
        }

        function drag(e) {
            if (isDragging) {
                e.preventDefault();
                currentX = e.clientX - initialX;
                currentY = e.clientY - initialY;
                xOffset = currentX;
                yOffset = currentY;
                setTranslate(currentX, currentY, signinBtn);
            }
        }

        function setTranslate(xPos, yPos, el) {
            el.style.transform = `translate3d(${xPos}px, ${yPos}px, 0)`;
        }
    });
    </script>
</body>
</html>