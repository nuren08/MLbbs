<?php
require_once 'config/db_config.php';
checkAdminAuth();

$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$stats = getAdminStats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ML论坛 - 管理后台</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <!-- 侧边栏 -->
        <div class="admin-sidebar">
            <div class="sidebar-header">
                <img src="<?= BASE_PATH ?>/assets/images/logo_ml.png" alt="ML论坛LOGO" class="sidebar-logo">
                <h2>ML论坛后台</h2>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?= $current_page == 'dashboard' ? 'active' : '' ?>">
                        <a href="?page=dashboard">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>控制台</span>
                        </a>
                    </li>
                    
                    <li class="nav-section">内容管理</li>
                    <li class="<?= $current_page == 'category_manage' ? 'active' : '' ?>">
                        <a href="?page=category_manage">
                            <i class="fas fa-sitemap"></i>
                            <span>导航板块管理</span>
                        </a>
                    </li>
                    <li class="<?= $current_page == 'announcement_manage' ? 'active' : '' ?>">
                        <a href="?page=announcement_manage">
                            <i class="fas fa-bullhorn"></i>
                            <span>公告管理</span>
                        </a>
                    </li>
                    <li class="<?= $current_page == 'post_audit' ? 'active' : '' ?>">
                        <a href="?page=post_audit">
                            <i class="fas fa-file-alt"></i>
                            <span>帖子审核</span>
                            <?php if ($stats['pending_posts'] > 0): ?>
                                <span class="badge"><?= $stats['pending_posts'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="<?= $current_page == 'treehole_audit' ? 'active' : '' ?>">
                        <a href="?page=treehole_audit">
                            <i class="fas fa-question-circle"></i>
                            <span>树洞申请审核</span>
                            <?php if ($stats['pending_treehole'] > 0): ?>
                                <span class="badge"><?= $stats['pending_treehole'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="nav-section">用户管理</li>
                    <li class="<?= $current_page == 'user_manage' ? 'active' : '' ?>">
                        <a href="?page=user_manage">
                            <i class="fas fa-users"></i>
                            <span>用户管理</span>
                        </a>
                    </li>
                    <li class="<?= $current_page == 'level_manage' ? 'active' : '' ?>">
                        <a href="?page=level_manage">
                            <i class="fas fa-chart-line"></i>
                            <span>等级管理</span>
                        </a>
                    </li>
                    <li class="<?= $current_page == 'realname_manage' ? 'active' : '' ?>">
                        <a href="?page=realname_manage">
                            <i class="fas fa-id-card"></i>
                            <span>实名认证管理</span>
                        </a>
                    </li>
                    
                    <li class="nav-section">系统配置</li>
                    <li class="<?= $current_page == 'rule_manage' ? 'active' : '' ?>">
                        <a href="?page=rule_manage">
                            <i class="fas fa-cogs"></i>
                            <span>规则管理</span>
                        </a>
                    </li>
                    <li class="<?= $current_page == 'ad_manage' ? 'active' : '' ?>">
                        <a href="?page=ad_manage">
                            <i class="fas fa-ad"></i>
                            <span>广告管理</span>
                        </a>
                    </li>
                    <li class="<?= $current_page == 'email_config' ? 'active' : '' ?>">
                        <a href="?page=email_config">
                            <i class="fas fa-envelope"></i>
                            <span>邮箱配置</span>
                        </a>
                    </li>
                    <li class="<?= $current_page == 'email_image_manage' ? 'active' : '' ?>">
                        <a href="?page=email_image_manage">
                            <i class="fas fa-images"></i>
                            <span>邮件图片管理</span>
                        </a>
                    </li>
                    <li class="<?= $current_page == 'send_message' ? 'active' : '' ?>">
                        <a href="?page=send_message">
                            <i class="fas fa-paper-plane"></i>
                            <span>发送消息</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- 主内容区 -->
        <div class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <button class="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1><?= getPageTitle($current_page) ?></h1>
                </div>
                <div class="header-right">
                    <span class="welcome">欢迎，<?= $_SESSION['user_nickname'] ?? '管理员' ?></span>
                    <a href="<?= BASE_PATH ?>/logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> 退出
                    </a>
                </div>
            </header>

            <div class="admin-content">
                <?php
                // 加载对应的页面内容
                $page_file = "pages/{$current_page}.php";
                if (file_exists($page_file)) {
                    include $page_file;
                } else {
                    include 'pages/dashboard.php';
                }
                ?>
            </div>
        </div>
    </div>

    <script src="<?= BASE_PATH ?>/assets/js/admin.js"></script>
</body>
</html>

<?php
function getPageTitle($page) {
    $titles = [
        'dashboard' => '控制台',
        'category_manage' => '导航板块管理',
        'announcement_manage' => '公告管理',
        'post_audit' => '帖子审核',
        'treehole_audit' => '树洞申请审核',
        'user_manage' => '用户管理',
        'level_manage' => '等级管理',
        'realname_manage' => '实名认证管理',
        'rule_manage' => '规则管理',
        'ad_manage' => '广告管理',
        'email_config' => '邮箱配置',
        'email_image_manage' => '邮件图片管理',
        'send_message' => '发送消息'
    ];
    return $titles[$page] ?? '控制台';
}
?>