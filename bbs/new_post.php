<?php
require_once 'includes/config.php';

// 检查登录状态
if (!isLoggedIn()) {
    redirect(BASE_PATH . '/login.php');
}

$currentUser = getCurrentUser();
$error = '';
$success = '';

// 获取分类ID
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$subcategory_id = isset($_GET['subcategory']) ? intval($_GET['subcategory']) : 0;

// 获取分类信息
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$category_id]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    die("分类不存在");
}

// 获取规则信息
$rules = $forum->getCategoryRules($category_id);

// 检查用户权限
$permission_check = $forum->checkUserPermission($currentUser['id'], 'post');
if (!$permission_check['success']) {
    $error = $permission_check['message'];
}

// 获取子分类（去重处理）
$stmt = $pdo->prepare("SELECT DISTINCT name, id FROM categories WHERE parent_id = ? AND status = 1 ORDER BY sort_order");
$stmt->execute([$category_id]);
$subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 处理发帖请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_post'])) {
    if ($error) {
        // 已有错误，不处理
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $selected_subcategory = intval($_POST['subcategory'] ?? 0);
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        
        // 验证数据
        if (empty($title)) {
            $error = '请输入帖子标题';
        } elseif (empty($content)) {
            $error = '请输入帖子内容';
        } elseif (empty($selected_subcategory)) {
            $error = '请选择帖子分类';
        } else {
            try {
                $pdo->beginTransaction();
                
                // 根据规则类型处理积分
                $points_cost = 0;
                $points_earned = 0;
                $exp_earned = 0;
                
                switch ($rules['type']) {
                    case 'general':
                        $user_level = getUserLevel($currentUser['exp']);
                        $level_rewards = getLevelRewards($user_level);
                        $points_earned = $level_rewards['post'];
                        $exp_earned = $points_earned;
                        break;
                        
                    case 'treehole':
                        $points_cost = $rules['post_points'];
                        if ($currentUser['points'] < $points_cost) {
                            throw new Exception("积分不足，需要{$points_cost}积分");
                        }
                        $expiry_date = date('Y-m-d H:i:s', strtotime("+{$rules['expiry_days']} days"));
                        break;
                        
                    case 'promotion':
                        $points_cost = $rules['post_points'];
                        if ($currentUser['points'] < $points_cost) {
                            throw new Exception("积分不足，需要{$points_cost}积分");
                        }
                        $expiry_date = date('Y-m-d H:i:s', strtotime("+{$rules['expiry_days']} days"));
                        break;
                }
                
                // 扣除积分
                if ($points_cost > 0) {
                    $stmt = $pdo->prepare("UPDATE users SET points = points - ? WHERE id = ? AND points >= ?");
                    $stmt->execute([$points_cost, $currentUser['id'], $points_cost]);
                    if ($stmt->rowCount() === 0) {
                        throw new Exception("积分扣除失败");
                    }
                }
                
                // 插入帖子
                $stmt = $pdo->prepare("
                    INSERT INTO posts (title, content, author_id, category_id, subcategory_id, rule_type, is_anonymous, points_required, expiry_date, ip_address)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $title,
                    $content,
                    $currentUser['id'],
                    $category_id,
                    $selected_subcategory,
                    $rules['type'],
                    $is_anonymous,
                    $points_cost,
                    $expiry_date ?? null,
                    $_SERVER['REMOTE_ADDR']
                ]);
                
                $post_id = $pdo->lastInsertId();
                
                // 处理附件上传
                if (isset($_FILES['attachments']) && $rules['type'] !== 'promotion') {
                    $attachments = [];
                    foreach ($_FILES['attachments']['name'] as $key => $name) {
                        if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_name = basename($name);
                            $file_tmp = $_FILES['attachments']['tmp_name'][$key];
                            $file_size = $_FILES['attachments']['size'][$key];
                            $file_type = $_FILES['attachments']['type'][$key];
                            
                            // 生成唯一文件名
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            $new_file_name = uniqid() . '.' . $file_ext;
                            $upload_path = UPLOAD_PATH . '/attachments/' . $new_file_name;
                            
                            if (move_uploaded_file($file_tmp, $upload_path)) {
                                $attachments[] = [
                                    'filename' => $file_name,
                                    'filepath' => $new_file_name,
                                    'filetype' => $file_type,
                                    'filesize' => $file_size
                                ];
                            }
                        }
                    }
                    
                    // 保存附件信息
                    if (!empty($attachments)) {
                        $stmt = $pdo->prepare("
                            INSERT INTO attachments (post_id, user_id, filename, filepath, filetype, filesize)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        foreach ($attachments as $attachment) {
                            $stmt->execute([
                                $post_id,
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
                if ($points_earned > 0) {
                    $forum->updateUserPoints($currentUser['id'], $points_earned, $exp_earned);
                }
                
                $pdo->commit();
                
                $success = "帖子发布成功！" . ($points_earned > 0 ? "获得{$points_earned}积分和经验值" : "");
                if ($points_cost > 0) {
                    $success .= "，扣除{$points_cost}积分";
                }
                
                // 跳转到帖子页面
                header("Location: " . BASE_PATH . "/post.php?id=" . $post_id . "&success=1");
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}

// 获取导航分类
$categories = $forum->getCategories();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发布帖子 - <?php echo escape($category['name']); ?> - ML论坛</title>
    <link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>/css/style.css">
    <link rel="icon" href="<?php echo ASSETS_PATH; ?>/images/favicon_ml.png" type="image/png">
    <style>
        .new-post-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .post-form {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: bold;
            margin-bottom: 8px;
            display: block;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: #e91e63;
            outline: none;
        }
        
        textarea.form-control {
            min-height: 300px;
            resize: vertical;
        }
        
        .form-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .file-upload {
            border: 2px dashed #ddd;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .file-upload:hover {
            border-color: #e91e63;
        }
        
        .submit-btn {
            background: #e91e63;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .submit-btn:hover {
            background: #d81b60;
        }
        
        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .rules-info {
            background: #f8f9fa;
            border-left: 4px solid #e91e63;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 0 4px 4px 0;
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
        
        .editor-toolbar {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-bottom: none;
            padding: 10px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .editor-btn {
            background: white;
            border: 1px solid #ddd;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .editor-btn:hover {
            background: #e9ecef;
        }
        
        .char-count {
            text-align: right;
            color: #666;
            font-size: 12px;
            margin-top: 5px;
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

    <div class="new-post-container">
        <div class="post-form">
            <h2>发布帖子 - <?php echo escape($category['name']); ?></h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- 规则信息 -->
            <div class="rules-info">
                <h4>板块规则</h4>
                <?php if ($rules): ?>
                    <p><?php echo $rules['description']; ?></p>
                    <?php if ($rules['type'] === 'treehole'): ?>
                        <p>• 发帖需要扣除 <?php echo $rules['post_points']; ?> 积分</p>
                        <p>• 帖子有效期为 <?php echo $rules['expiry_days']; ?> 天</p>
                    <?php elseif ($rules['type'] === 'promotion'): ?>
                        <p>• 发帖需要扣除 <?php echo $rules['post_points']; ?> 积分</p>
                        <p>• 帖子有效期为 <?php echo $rules['expiry_days']; ?> 天</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <!-- 标题 -->
                <div class="form-group">
                    <label class="form-label">帖子标题</label>
                    <input type="text" name="title" class="form-control" required 
                           value="<?php echo escape($_POST['title'] ?? ''); ?>" 
                           placeholder="请输入帖子标题">
                </div>
                
                <!-- 分类选择 -->
                <div class="form-group">
                    <label class="form-label">帖子分类</label>
                    <select name="subcategory" class="form-select" required>
                        <option value="">请选择分类</option>
                        <?php foreach ($subcategories as $subcat): ?>
                            <option value="<?php echo $subcat['id']; ?>" 
                                <?php echo ($subcategory_id == $subcat['id'] || ($_POST['subcategory'] ?? '') == $subcat['id']) ? 'selected' : ''; ?>>
                                <?php echo escape($subcat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- 匿名选项（仅树洞规则） -->
                <?php if ($rules['type'] === 'treehole'): ?>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_anonymous" id="is_anonymous" value="1"
                            <?php echo ($_POST['is_anonymous'] ?? '') ? 'checked' : ''; ?>>
                        <label for="is_anonymous">匿名提问</label>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- 正文编辑器 -->
                <div class="form-group">
                    <label class="form-label">帖子内容</label>
                    <div class="editor-toolbar">
                        <button type="button" class="editor-btn" onclick="formatText('bold')"><b>粗体</b></button>
                        <button type="button" class="editor-btn" onclick="formatText('italic')"><i>斜体</i></button>
                        <button type="button" class="editor-btn" onclick="formatText('underline')"><u>下划线</u></button>
                        <button type="button" class="editor-btn" onclick="insertEmoji('😊')">😊 表情</button>
                        <button type="button" class="editor-btn" onclick="insertLink()">🔗 链接</button>
                    </div>
                    <textarea name="content" id="content" class="form-control" required 
                              placeholder="请输入帖子内容"><?php echo escape($_POST['content'] ?? ''); ?></textarea>
                    <div class="char-count">
                        <span id="charCount">0</span> 字符
                    </div>
                </div>
                
                <!-- 附件上传（通用和树洞规则） -->
                <?php if ($rules['type'] !== 'promotion'): ?>
                <div class="form-group">
                    <label class="form-label">附件上传</label>
                    <div class="file-upload">
                        <input type="file" name="attachments[]" multiple 
                               accept="<?php echo $rules['type'] === 'general' ? '*' : 'image/*,.pdf,.doc,.docx,.zip,.rar'; ?>">
                        <p>点击或拖拽文件到此处上传</p>
                        <?php if ($rules['type'] === 'general'): ?>
                            <p class="text-muted">支持所有格式文件（图片、音频、视频、文档等）</p>
                        <?php else: ?>
                            <p class="text-muted">支持图片、PDF、Word文档、压缩文件等格式</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- 提交按钮 -->
                <div class="form-group">
                    <button type="submit" name="submit_post" class="submit-btn" 
                            <?php echo $error ? 'disabled' : ''; ?>>
                        发布帖子
                    </button>
                    <a href="<?php echo BASE_PATH; ?>/category.php?id=<?php echo $category_id; ?>" 
                       style="margin-left: 15px; color: #666;">取消</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    // 字符计数
    const contentTextarea = document.getElementById('content');
    const charCount = document.getElementById('charCount');
    
    contentTextarea.addEventListener('input', function() {
        charCount.textContent = this.value.length;
    });
    
    // 初始化字符计数
    charCount.textContent = contentTextarea.value.length;
    
    // 文本格式化函数
    function formatText(type) {
        const textarea = document.getElementById('content');
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
    
    // 插入表情
    function insertEmoji(emoji) {
        const textarea = document.getElementById('content');
        const start = textarea.selectionStart;
        textarea.value = textarea.value.substring(0, start) + emoji + textarea.value.substring(start);
        textarea.focus();
        textarea.setSelectionRange(start + emoji.length, start + emoji.length);
    }
    
    // 插入链接
    function insertLink() {
        const url = prompt('请输入链接地址：');
        if (url) {
            const text = prompt('请输入链接显示文本（可选）：') || url;
            const textarea = document.getElementById('content');
            const start = textarea.selectionStart;
            const link = `[${text}](${url})`;
            textarea.value = textarea.value.substring(0, start) + link + textarea.value.substring(start);
            textarea.focus();
            textarea.setSelectionRange(start + link.length, start + link.length);
        }
    }
    
    // 文件上传区域交互
    const fileUpload = document.querySelector('.file-upload');
    const fileInput = fileUpload?.querySelector('input[type="file"]');
    
    if (fileUpload && fileInput) {
        fileUpload.addEventListener('click', () => fileInput.click());
        
        fileUpload.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUpload.style.borderColor = '#e91e63';
            fileUpload.style.background = '#f8f9fa';
        });
        
        fileUpload.addEventListener('dragleave', () => {
            fileUpload.style.borderColor = '#ddd';
            fileUpload.style.background = 'white';
        });
        
        fileUpload.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUpload.style.borderColor = '#ddd';
            fileUpload.style.background = 'white';
            fileInput.files = e.dataTransfer.files;
        });
    }
    </script>
</body>
</html>