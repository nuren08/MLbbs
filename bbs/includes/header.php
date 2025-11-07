<?php
require_once __DIR__ . '/../includes/config.php';
$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ML论坛 - 分享知识，交流思想</title>
    <link rel="icon" href="<?php echo ASSETS_PATH; ?>/images/favicon_ml.png" type="image/png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- 自定义CSS -->
    <link href="<?php echo ASSETS_PATH; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .rainbow-text {
            background: linear-gradient(45deg, #ff0000, #ff8000, #ffff00, #00ff00, #00ffff, #0000ff, #8000ff, #ff00ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: bold;
            font-size: 1.8rem;
        }
        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .announcement-bar {
            background: linear-gradient(90deg, #ff6b6b, #ffa500, #ffff00, #00ff00, #00ffff, #0000ff, #8000ff);
            color: white;
            padding: 5px 0;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            position: relative;
        }
        .announcement-content {
            display: inline-block;
            padding-left: 100%;
            animation: scroll 30s linear infinite;
        }
        @keyframes scroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-100%); }
        }
        .nav-category {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .nav-category .nav-link {
            color: #495057;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            margin: 0 2px;
        }
        .nav-category .nav-link:hover,
        .nav-category .nav-link.active {
            background: #007bff;
            color: white;
        }
        .signin-btn {
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            border: none;
            border-radius: 25px;
            padding: 8px 20px;
            color: white;
            font-weight: bold;
        }
        .floating-signin {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
            z-index: 1000;
            cursor: move;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <!-- 顶部导航 -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <!-- LOGO和网站名称 -->
            <div class="logo-container">
                <a class="navbar-brand" href="<?php echo BASE_PATH; ?>/index.php">
                    <img src="<?php echo ASSETS_PATH; ?>/images/logo_ml.png" alt="ML论坛LOGO" height="40">
                </a>
                <span class="rainbow-text">ML论坛</span>
            </div>

            <!-- 用户操作区域 -->
            <div class="d-flex align-items-center">
                <?php if (isLoggedIn()): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i>
                            <?php echo escape($currentUser['nickname'] ?: $currentUser['username']); ?>
                            <span class="badge bg-secondary ms-1">LV<?php echo getUserLevel($currentUser['exp']); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo BASE_PATH; ?>/profile.php"><i class="fas fa-user-circle me-2"></i>个人中心</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_PATH; ?>/messages.php"><i class="fas fa-envelope me-2"></i>我的消息</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_PATH; ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>退出登录</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?php echo BASE_PATH; ?>/login.php" class="btn signin-btn">
                        <i class="fas fa-sign-in-alt me-1"></i>登录/注册
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- 公告栏 -->
    <?php if (getSystemSetting('announcement_display', '1') == '1'): ?>
    <div class="announcement-bar">
        <div class="announcement-content">
            <i class="fas fa-bullhorn me-2"></i>
            <?php
            $announcements = [];
            try {
                global $pdo;
                $stmt = $pdo->query("SELECT title FROM announcements WHERE status = 1 ORDER BY created_at DESC LIMIT 5");
                $announcements = $stmt->fetchAll();
            } catch (Exception $e) {
                error_log("获取公告失败: " . $e->getMessage());
            }
            
            if (!empty($announcements)) {
                $announcementText = '';
                foreach ($announcements as $announcement) {
                    $announcementText .= '📢 ' . escape($announcement['title']) . ' | ';
                }
                echo rtrim($announcementText, ' | ');
            } else {
                echo '📢 欢迎来到ML论坛！分享知识，交流思想，共建美好社区！';
            }
            ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 分类导航 -->
    <nav class="navbar navbar-expand-lg navbar-light nav-category">
        <div class="container">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#categoryNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="categoryNav">
                <ul class="navbar-nav me-auto">
                    <?php
                    try {
                        $stmt = $pdo->prepare("
                            SELECT c1.*, 
                                   (SELECT COUNT(*) FROM categories c2 WHERE c2.parent_id = c1.id AND c2.status = 1) as sub_count
                            FROM categories c1 
                            WHERE c1.parent_id = 0 AND c1.status = 1 
                            ORDER BY c1.sort_order
                        ");
                        $stmt->execute();
                        $categories = $stmt->fetchAll();
                        
                        foreach ($categories as $category) {
                            $isActive = false;
                            if (isset($_GET['category_id']) && $_GET['category_id'] == $category['id']) {
                                $isActive = true;
                            }
                            
                            echo '<li class="nav-item dropdown">';
                            echo '<a class="nav-link ' . ($isActive ? 'active' : '') . ' dropdown-toggle" href="' . 
                                 ($category['url'] ?: BASE_PATH . '/category.php?id=' . $category['id']) . '" data-bs-toggle="dropdown">';
                            echo escape($category['name']);
                            echo '</a>';
                            
                            // 获取子分类
                            $subStmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? AND status = 1 ORDER BY sort_order");
                            $subStmt->execute([$category['id']]);
                            $subCategories = $subStmt->fetchAll();
                            
                            if (!empty($subCategories)) {
                                echo '<ul class="dropdown-menu">';
                                foreach ($subCategories as $subCategory) {
                                    echo '<li><a class="dropdown-item" href="' . BASE_PATH . '/category.php?id=' . $subCategory['id'] . '">';
                                    echo escape($subCategory['name']);
                                    echo '</a></li>';
                                }
                                echo '</ul>';
                            }
                            echo '</li>';
                        }
                    } catch (Exception $e) {
                        error_log("获取分类失败: " . $e->getMessage());
                    }
                    ?>
                </ul>
                
                <!-- 搜索框 -->
                <form class="d-flex" action="<?php echo BASE_PATH; ?>/search.php" method="get">
                    <input class="form-control me-2" type="search" name="q" placeholder="搜索全站内容..." aria-label="搜索">
                    <button class="btn btn-outline-success" type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
    </nav>

    <!-- 主内容区域 -->
    <main class="container my-4">