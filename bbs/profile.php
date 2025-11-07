<?php
require_once __DIR__ . '/includes/config.php';

// 检查登录状态
if (!isLoggedIn()) {
    redirect(BASE_PATH . '/login.php');
}

$currentUser = getCurrentUser();
$error = '';
$success = '';

// 处理个人资料更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $nickname = escape($_POST['nickname'] ?? '');
    $location_province = escape($_POST['location_province'] ?? '');
    $location_city = escape($_POST['location_city'] ?? '');
    $location_county = escape($_POST['location_county'] ?? '');
    $signature = escape($_POST['signature'] ?? '');
    $allow_follow = isset($_POST['allow_follow']) ? 1 : 0;

    try {
        $updateStmt = $pdo->prepare("
            UPDATE users 
            SET nickname = ?, location_province = ?, location_city = ?, location_county = ?, 
            signature = ?, allow_follow = ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $nickname, $location_province, $location_city, $location_county, 
            $signature, $allow_follow, $currentUser['id']
        ]);
        $success = '个人资料更新成功';
        // 刷新用户信息
        $currentUser = getCurrentUser();
    } catch (PDOException $e) {
        error_log("更新个人资料错误: " . $e->getMessage());
        $error = '更新失败，请稍后再试';
    }
}

// 处理头像上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['avatar']['type'];
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = __DIR__ . '/../uploads/avatars/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $currentUser['user_id'] . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $file_path)) {
                // 更新数据库
                $avatar_url = BASE_PATH . '/uploads/avatars/' . $filename;
                $updateStmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $updateStmt->execute([$avatar_url, $currentUser['id']]);
                $success = '头像更新成功';
                $currentUser = getCurrentUser();
            } else {
                $error = '头像上传失败';
            }
        } else {
            $error = '只支持 JPG, PNG, GIF 格式的图片';
        }
    } else {
        $error = '请选择头像文件';
    }
}

// 处理背景图片上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_background'])) {
    if (isset($_FILES['background']) && $_FILES['background']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['background']['type'];
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = __DIR__ . '/../uploads/backgrounds/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_extension = pathinfo($_FILES['background']['name'], PATHINFO_EXTENSION);
            $filename = 'bg_' . $currentUser['user_id'] . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['background']['tmp_name'], $file_path)) {
                $bg_url = BASE_PATH . '/uploads/backgrounds/' . $filename;
                $updateStmt = $pdo->prepare("UPDATE users SET background = ? WHERE id = ?");
                $updateStmt->execute([$bg_url, $currentUser['id']]);
                $success = '背景图片更新成功';
                $currentUser = getCurrentUser();
            } else {
                $error = '背景图片上传失败';
            }
        } else {
            $error = '只支持 JPG, PNG, GIF 格式的图片';
        }
    } else {
        $error = '请选择背景图片';
    }
}

// 获取用户统计数据
try {
    // 帖子数
    $postsStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE author_id = ? AND status = 'approved'");
    $postsStmt->execute([$currentUser['id']]);
    $postsCount = $postsStmt->fetchColumn();

    // 粉丝数
    $followersStmt = $pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE following_id = ?");
    $followersStmt->execute([$currentUser['id']]);
    $followersCount = $followersStmt->fetchColumn();

    // 关注数
    $followingStmt = $pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ?");
    $followingStmt->execute([$currentUser['id']]);
    $followingCount = $followingStmt->fetchColumn();

    // 收藏数
    $favoritesStmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ?");
    $favoritesStmt->execute([$currentUser['id']]);
    $favoritesCount = $favoritesStmt->fetchColumn();

    // 今日是否已签到
    $signinStmt = $pdo->prepare("SELECT COUNT(*) FROM sign_ins WHERE user_id = ? AND sign_date = CURDATE()");
    $signinStmt->execute([$currentUser['id']]);
    $hasSignedIn = $signinStmt->fetchColumn() > 0;

    // 连续签到天数
    $continuousStmt = $pdo->prepare("SELECT continuous_days FROM sign_ins WHERE user_id = ? ORDER BY sign_date DESC LIMIT 1");
    $continuousStmt->execute([$currentUser['id']]);
    $continuousDays = $continuousStmt->fetchColumn() ?: 0;

} catch (PDOException $e) {
    error_log("获取用户统计错误: " . $e->getMessage());
}

// 获取收藏的帖子
$favorites_page = isset($_GET['favorites_page']) ? max(1, intval($_GET['favorites_page'])) : 1;
$favorites_per_page = 10;
$favorites_data = getUserFavorites($currentUser['id'], $favorites_page, $favorites_per_page);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人中心 - ML论坛</title>
    <link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>/css/style.css">
    <link rel="icon" href="<?php echo ASSETS_PATH; ?>/images/favicon_ml.png" type="image/png">
    <style>
        .user-avatar-lg {
            width: 120px;
            height: 120px;
            object-fit: cover;
        }
        
        .level-badge {
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .background-preview {
            border: 2px dashed #dee2e6;
        }
        
        .nav-tabs .nav-link.active {
            border-bottom: 3px solid #e91e63;
            font-weight: bold;
        }
        
        .post-item {
            transition: background-color 0.3s ease;
        }
        
        .post-item:hover {
            background-color: #f8f9fa;
        }
        
        .favorites-grid {
            display: grid;
            gap: 15px;
        }
        
        .favorite-item {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s ease;
        }
        
        .favorite-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .favorite-title {
            font-size: 1.1rem;
            margin-bottom: 8px;
        }
        
        .favorite-title a {
            color: #333;
            text-decoration: none;
        }
        
        .favorite-title a:hover {
            color: #e91e63;
        }
        
        .favorite-meta {
            display: flex;
            gap: 15px;
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .favorite-date {
            color: #999;
            font-size: 0.8rem;
        }
        
        .remove-favorite {
            background: #dc3545;
            color: white;
            border: none;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.8rem;
            cursor: pointer;
            margin-top: 5px;
        }
        
        .remove-favorite:hover {
            background: #c82333;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
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
        
        .no-content {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .no-content i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ccc;
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
                <a href="<?php echo BASE_PATH; ?>/profile.php" class="user-link">
                    <?php echo escape($currentUser['nickname'] ?: $currentUser['username']); ?>
                </a>
                <a href="<?php echo BASE_PATH; ?>/logout.php" class="logout-btn">退出</a>
            </div>
        </div>
    </header>

    <!-- 导航 -->
    <nav class="main-nav">
        <div class="container">
            <ul class="nav-list">
                <?php 
                $categories = $forum->getCategories();
                foreach ($categories as $category_item): ?>
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

    <div class="container" style="max-width: 1200px; margin: 20px auto; padding: 0 20px;">
        <div class="row">
            <!-- 左侧个人信息 -->
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-body text-center">
                        <!-- 用户头像 -->
                        <div class="position-relative mb-3">
                            <img src="<?php echo $currentUser['avatar'] ?: (ASSETS_PATH . '/images/tx_ml.png'); ?>" 
                                alt="用户头像" class="user-avatar-lg rounded-circle border">
                            <button class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle" 
                                data-bs-toggle="modal" data-bs-target="#avatarModal" title="更换头像">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>

                        <!-- 用户基本信息 -->
                        <h4 class="mb-1"><?php echo escape($currentUser['nickname'] ?: $currentUser['username']); ?></h4>
                        <p class="text-muted mb-2">ID: <?php echo $currentUser['user_id']; ?></p>
                        <div class="level-badge mb-3 d-inline-block">
                            LV<?php echo getUserLevel($currentUser['exp']); ?>
                        </div>

                        <!-- 用户数据统计 -->
                        <div class="row text-center mb-3">
                            <div class="col-3">
                                <div class="fw-bold"><?php echo $postsCount; ?></div>
                                <small class="text-muted">帖子</small>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold"><?php echo $followersCount; ?></div>
                                <small class="text-muted">粉丝</small>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold"><?php echo $followingCount; ?></div>
                                <small class="text-muted">关注</small>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold"><?php echo $favoritesCount; ?></div>
                                <small class="text-muted">收藏</small>
                            </div>
                        </div>

                        <!-- 签到按钮 -->
                        <?php if (!$hasSignedIn): ?>
                            <a href="<?php echo BASE_PATH; ?>/signin.php" class="btn btn-success btn-sm w-100 mb-2">
                                <i class="fas fa-calendar-check me-1"></i>每日签到
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-success btn-sm w-100 mb-2" disabled>
                                <i class="fas fa-check me-1"></i>今日已签到 (<?php echo $continuousDays; ?>天)
                            </button>
                        <?php endif; ?>

                        <!-- 经验值进度条 -->
                        <div class="mt-3">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>经验值</span>
                                <span><?php echo $currentUser['exp']; ?></span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <?php
                                $currentLevel = getUserLevel($currentUser['exp']);
                                $nextLevelExp = [
                                    1 => 1000, 2 => 10000, 3 => 50000, 4 => 150000, 
                                    5 => 500000, 6 => 1000000, 7 => 5000000, 8 => 5000000
                                ];
                                $prevLevelExp = $currentLevel > 1 ? $nextLevelExp[$currentLevel-1] : 0;
                                $currentLevelExp = $nextLevelExp[$currentLevel];
                                $progress = $currentLevelExp > 0 ? 
                                    (($currentUser['exp'] - $prevLevelExp) / ($currentLevelExp - $prevLevelExp)) * 100 : 100;
                                $progress = min(max($progress, 0), 100);
                                ?>
                                <div class="progress-bar bg-warning" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                            <div class="text-end small text-muted mt-1">
                                下一等级: <?php echo $currentLevel < 8 ? 'LV' . ($currentLevel + 1) : '最高等级'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 积分信息 -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-coins me-2"></i>积分信息</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>当前积分:</span>
                            <strong class="text-warning"><?php echo $currentUser['points']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>当前经验:</span>
                            <strong class="text-info"><?php echo $currentUser['exp']; ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>注册时间:</span>
                            <span><?php echo date('Y-m-d H:i:s', strtotime($currentUser['register_time'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 右侧内容区域 -->
            <div class="col-md-8">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- 导航标签 -->
                <ul class="nav nav-tabs mb-4" id="profileTabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#profile">
                            <i class="fas fa-user me-1"></i>个人资料
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#security">
                            <i class="fas fa-shield-alt me-1"></i>安全中心
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#posts">
                            <i class="fas fa-file-alt me-1"></i>我的帖子
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#favorites">
                            <i class="fas fa-star me-1"></i>我的收藏
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- 个人资料标签页 -->
                    <div class="tab-pane fade show active" id="profile">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>编辑个人资料</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="update_profile" value="1">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="nickname" class="form-label">昵称</label>
                                                <input type="text" class="form-control" id="nickname" name="nickname" 
                                                    value="<?php echo escape($currentUser['nickname'] ?: ''); ?>" 
                                                    placeholder="请输入昵称">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">用户名</label>
                                                <input type="text" class="form-control" value="<?php echo escape($currentUser['username']); ?>" disabled>
                                                <div class="form-text">用户名不可修改</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">所在地</label>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <select class="form-select" id="location_province" name="location_province">
                                                    <option value="">选择省份</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <select class="form-select" id="location_city" name="location_city">
                                                    <option value="">选择城市</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <select class="form-select" id="location_county" name="location_county">
                                                    <option value="">选择区县</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="signature" class="form-label">个性签名</label>
                                        <textarea class="form-control" id="signature" name="signature" rows="3" 
                                            placeholder="请输入个性签名"><?php echo escape($currentUser['signature'] ?: ''); ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="allow_follow" name="allow_follow" 
                                                <?php echo $currentUser['allow_follow'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="allow_follow">
                                                允许他人关注我
                                            </label>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>保存修改
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- 背景图片设置 -->
                        <div class="card shadow-sm mt-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-image me-2"></i>背景图片</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <div class="background-preview rounded" 
                                        style="height: 150px; background: url('<?php echo $currentUser['background'] ?: (ASSETS_PATH . '/images/bj_ml.png'); ?>') center/cover;">
                                    </div>
                                </div>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="update_background" value="1">
                                    <div class="input-group">
                                        <input type="file" class="form-control" name="background" accept="image/*">
                                        <button type="submit" class="btn btn-outline-primary">上传背景</button>
                                    </div>
                                    <div class="form-text">支持 JPG, PNG, GIF 格式，建议尺寸 1200×300</div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- 安全中心标签页 -->
                    <div class="tab-pane fade" id="security">
                        <!-- 修改密码 -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-key me-2"></i>修改密码</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="<?php echo BASE_PATH; ?>/change_password.php">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">当前密码</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">新密码</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_new_password" class="form-label">确认新密码</label>
                                        <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">修改密码</button>
                                </form>
                            </div>
                        </div>

                        <!-- 密保问题设置 -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>密保问题</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($currentUser['security_question1'] && $currentUser['security_question2']): ?>
                                    <div class="alert alert-info">
                                        <p class="mb-2">您已设置密保问题</p>
                                        <p class="mb-1"><strong>问题一：</strong>我的小学名称</p>
                                        <p class="mb-0"><strong>问题二：</strong>我的手机尾号</p>
                                    </div>
                                    <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#securityModal">
                                        修改密保问题
                                    </button>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <p class="mb-3">您尚未设置密保问题，设置后可用于找回密码</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#securityModal">
                                            设置密保问题
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 实名认证 -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>实名认证</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($currentUser['realname_verified']): ?>
                                    <div class="alert alert-success">
                                        <h6><i class="fas fa-check-circle me-2"></i>已实名认证</h6>
                                        <p class="mb-1">认证姓名：<?php echo escape($currentUser['realname_surname']); ?>**</p>
                                        <p class="mb-0">身份证号：<?php echo substr($currentUser['realname_idcard'] ?? '', 0, 1) . '****************' . substr($currentUser['realname_idcard'] ?? '', -1, 1); ?></p>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <h6><i class="fas fa-exclamation-triangle me-2"></i>未实名认证</h6>
                                        <p class="mb-3">为响应后端实名，前端自愿监管要求原则，请您进行实名认证后再发帖和回帖。未实名用户仅能浏览帖子，请您放心，本站不存储您的身份完整信息，仅存从阿里云实名认证返回的脱敏信息。</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#realnameModal">
                                            前往实名认证
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 账号注销 -->
                        <div class="card shadow-sm border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>账号注销</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-exclamation-circle me-2"></i>警告</h6>
                                    <p class="mb-2">账号注销是不可逆的操作，一旦注销：</p>
                                    <ul class="mb-2">
                                        <li>您的所有个人信息将被永久删除</li>
                                        <li>您发布的所有帖子和回复将被删除</li>
                                        <li>您的积分和经验值将清零</li>
                                        <li>此操作无法撤销，请谨慎操作！</li>
                                    </ul>
                                </div>
                                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                    <i class="fas fa-trash-alt me-2"></i>注销账号
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 我的帖子标签页 -->
                    <div class="tab-pane fade" id="posts">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>我的帖子</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                try {
                                    $postsStmt = $pdo->prepare("
                                        SELECT p.*, c.name as category_name 
                                        FROM posts p 
                                        LEFT JOIN categories c ON p.category_id = c.id 
                                        WHERE p.author_id = ? 
                                        ORDER BY p.created_at DESC 
                                        LIMIT 10
                                    ");
                                    $postsStmt->execute([$currentUser['id']]);
                                    $posts = $postsStmt->fetchAll();

                                    if (empty($posts)) {
                                        echo '<div class="no-content">';
                                        echo '<i class="fas fa-file-alt"></i>';
                                        echo '<p>您还没有发布过帖子</p>';
                                        echo '<a href="' . BASE_PATH . '/new_post.php" class="btn btn-primary">发布第一个帖子</a>';
                                        echo '</div>';
                                    } else {
                                        foreach ($posts as $post) {
                                            echo '<div class="post-item border-bottom pb-3 mb-3">';
                                            echo '<h6><a href="' . BASE_PATH . '/post.php?id=' . $post['id'] . '" class="text-decoration-none">' . escape($post['title']) . '</a></h6>';
                                            echo '<div class="text-muted small">';
                                            echo '<span class="me-3">' . date('Y-m-d H:i', strtotime($post['created_at'])) . '</span>';
                                            echo '<span class="me-3">分类: ' . escape($post['category_name']) . '</span>';
                                            echo '<span class="me-3">浏览: ' . $post['views'] . '</span>';
                                            echo '<span>点赞: ' . $post['likes'] . '</span>';
                                            echo '</div>';
                                            echo '</div>';
                                        }
                                        echo '<div class="text-center">';
                                        echo '<a href="' . BASE_PATH . '/my_posts.php" class="btn btn-outline-primary">查看全部帖子</a>';
                                        echo '</div>';
                                    }
                                } catch (PDOException $e) {
                                    echo '<div class="alert alert-danger">加载帖子列表失败</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- 我的收藏标签页 -->
                    <div class="tab-pane fade" id="favorites">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-star me-2"></i>我的收藏</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($favorites_data['favorites'])): ?>
                                    <div class="no-content">
                                        <i class="fas fa-star"></i>
                                        <p>您还没有收藏任何帖子</p>
                                        <p class="small text-muted">浏览帖子时点击右上角的五角星按钮可以收藏帖子</p>
                                    </div>
                                <?php else: ?>
                                    <div class="favorites-grid">
                                        <?php foreach ($favorites_data['favorites'] as $favorite): ?>
                                            <div class="favorite-item">
                                                <div class="favorite-title">
                                                    <a href="<?php echo BASE_PATH; ?>/post.php?id=<?php echo $favorite['id']; ?>">
                                                        <?php echo escape($favorite['title']); ?>
                                                    </a>
                                                </div>
                                                <div class="favorite-meta">
                                                    <span>作者: <?php echo escape($favorite['nickname'] ?: $favorite['username']); ?></span>
                                                    <span>分类: <?php echo escape($favorite['category_name']); ?></span>
                                                    <span>浏览: <?php echo $favorite['views']; ?></span>
                                                </div>
                                                <div class="favorite-date">
                                                    收藏时间: <?php echo date('Y-m-d H:i', strtotime($favorite['favorited_at'])); ?>
                                                </div>
                                                <button class="remove-favorite" onclick="removeFavorite(<?php echo $favorite['id']; ?>, this)">
                                                    <i class="fas fa-trash-alt me-1"></i>取消收藏
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- 分页 -->
                                    <?php if ($favorites_data['pages'] > 1): ?>
                                        <div class="pagination">
                                            <?php for ($i = 1; $i <= $favorites_data['pages']; $i++): ?>
                                                <a href="?favorites_page=<?php echo $i; ?>" 
                                                   class="<?php echo $i == $favorites_page ? 'active' : ''; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            <?php endfor; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 头像上传模态框 -->
    <div class="modal fade" id="avatarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">更换头像</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data" id="avatarForm">
                        <input type="hidden" name="update_avatar" value="1">
                        <div class="text-center mb-3">
                            <img id="avatarPreview" src="<?php echo $currentUser['avatar'] ?: (ASSETS_PATH . '/images/tx_ml.png'); ?>" 
                                alt="头像预览" class="rounded-circle border" style="width: 150px; height: 150px; object-fit: cover;">
                        </div>
                        <div class="mb-3">
                            <input type="file" class="form-control" name="avatar" id="avatarInput" accept="image/*">
                            <div class="form-text">支持 JPG, PNG, GIF 格式，建议正方形图片</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" form="avatarForm" class="btn btn-primary">保存头像</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 密保问题模态框 -->
    <div class="modal fade" id="securityModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $currentUser['security_question1'] ? '修改' : '设置'; ?>密保问题</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo BASE_PATH; ?>/update_security_questions.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">问题一：我的小学名称</label>
                            <input type="text" class="form-control" name="security_answer1" 
                                placeholder="请输入您的小学名称" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">问题二：我的手机尾号</label>
                            <input type="text" class="form-control" name="security_answer2" 
                                placeholder="请输入您的手机尾号（4位数字）" maxlength="4" required>
                        </div>
                        <div class="alert alert-info">
                            <small>密保问题可用于找回密码，请确保答案准确且易于记忆</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">保存设置</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 实名认证模态框 -->
    <div class="modal fade" id="realnameModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">实名认证</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo BASE_PATH; ?>/realname_verify.php">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <p class="mb-0">为响应后端实名，前端自愿监管要求原则，请您进行实名认证后再发帖和回帖。未实名用户仅能浏览帖子，请您放心，本站不存储您的身份完整信息，仅存从阿里云实名认证返回的脱敏信息。</p>
                        </div>
                        <div class="mb-3">
                            <label for="realname" class="form-label">真实姓名</label>
                            <input type="text" class="form-control" id="realname" name="realname" 
                                placeholder="请输入您的真实姓名" required>
                        </div>
                        <div class="mb-3">
                            <label for="idcard" class="form-label">身份证号码</label>
                            <input type="text" class="form-control" id="idcard" name="idcard" 
                                placeholder="请输入您的身份证号码" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">提交认证</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 账号注销模态框 -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">确认注销账号</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>严重警告</h6>
                        <p class="mb-0">此操作将永久删除您的账号和所有相关数据，无法恢复！</p>
                    </div>
                    <p>请确认您了解以下后果：</p>
                    <ul>
                        <li>所有个人信息将被删除</li>
                        <li>所有帖子和回复将被删除</li>
                        <li>积分和经验值将清零</li>
                        <li>此操作不可撤销！</li>
                    </ul>
                    <p class="text-danger">如果您确定要注销账号，请联系管理员处理。</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <a href="<?php echo BASE_PATH; ?>/contact.php" class="btn btn-danger">联系管理员注销</a>
                </div>
            </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // 头像预览
    document.getElementById('avatarInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatarPreview').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });

    // 取消收藏功能
    function removeFavorite(postId, button) {
        if (!confirm('确定要取消收藏这个帖子吗？')) {
            return;
        }
        
        fetch('<?php echo BASE_PATH; ?>/ajax/remove_favorite.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'post_id=' + postId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 移除收藏项
                const favoriteItem = button.closest('.favorite-item');
                favoriteItem.style.opacity = '0';
                setTimeout(() => {
                    favoriteItem.remove();
                    // 刷新页面统计
                    location.reload();
                }, 300);
            } else {
                alert('取消收藏失败：' + data.message);
            }
        })
        .catch(error => {
            console.error('取消收藏失败:', error);
            alert('取消收藏失败，请重试');
        });
    }

    // 省市县三级联动数据
    const chinaData = {
        // 这里应该包含完整的省市县数据，由于数据量很大，这里只示例结构
        // 实际使用时应该从外部文件加载或使用API
        "北京市": {
            "市辖区": ["东城区", "西城区", "朝阳区", "丰台区", "石景山区", "海淀区", "门头沟区", "房山区", "通州区", "顺义区", "昌平区", "大兴区", "怀柔区", "平谷区"],
            "县": ["密云县", "延庆县"]
        },
        "天津市": {
            "市辖区": ["和平区", "河东区", "河西区", "南开区", "河北区", "红桥区", "东丽区", "西青区", "津南区", "北辰区", "武清区", "宝坻区", "滨海新区"],
            "县": ["宁河县", "静海县", "蓟县"]
        }
        // ... 其他省份数据
    };

    // 初始化地理位置选择
    function initLocationSelectors() {
        const provinceSelect = document.getElementById('location_province');
        const citySelect = document.getElementById('location_city');
        const countySelect = document.getElementById('location_county');

        // 填充省份
        for (const province in chinaData) {
            const option = document.createElement('option');
            option.value = province;
            option.textContent = province;
            provinceSelect.appendChild(option);
        }

        // 设置当前值
        const currentProvince = '<?php echo $currentUser['location_province'] ?? ''; ?>';
        const currentCity = '<?php echo $currentUser['location_city'] ?? ''; ?>';
        const currentCounty = '<?php echo $currentUser['location_county'] ?? ''; ?>';

        if (currentProvince) {
            provinceSelect.value = currentProvince;
            updateCities();
            if (currentCity) {
                citySelect.value = currentCity;
                updateCounties();
                if (currentCounty) {
                    countySelect.value = currentCounty;
                }
            }
        }

        // 省份变化事件
        provinceSelect.addEventListener('change', updateCities);
        citySelect.addEventListener('change', updateCounties);
    }

    function updateCities() {
        const province = document.getElementById('location_province').value;
        const citySelect = document.getElementById('location_city');
        const countySelect = document.getElementById('location_county');

        // 清空城市和区县
        citySelect.innerHTML = '<option value="">选择城市</option>';
        countySelect.innerHTML = '<option value="">选择区县</option>';

        if (province && chinaData[province]) {
            for (const cityType in chinaData[province]) {
                const cities = chinaData[province][cityType];
                cities.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city;
                    option.textContent = city;
                    citySelect.appendChild(option);
                });
            }
        }
    }

    function updateCounties() {
        const province = document.getElementById('location_province').value;
        const city = document.getElementById('location_city').value;
        const countySelect = document.getElementById('location_county');

        // 清空区县
        countySelect.innerHTML = '<option value="">选择区县</option>';

        if (province && city && chinaData[province]) {
            for (const cityType in chinaData[province]) {
                const counties = chinaData[province][cityType];
                if (counties.includes(city)) {
                    // 如果是直辖市，区县数据在下一级
                    // 这里简化处理，实际应该根据具体数据结构来
                    counties.forEach(county => {
                        const option = document.createElement('option');
                        option.value = county;
                        option.textContent = county;
                        countySelect.appendChild(option);
                    });
                    break;
                }
            }
        }
    }

    // 页面加载完成后初始化
    document.addEventListener('DOMContentLoaded', function() {
        initLocationSelectors();
        
        // 使签到按钮可拖动
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