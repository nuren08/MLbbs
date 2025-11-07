<?php
require_once 'includes/config.php';

// 获取热门帖子
$stmt = $pdo->prepare("
    SELECT p.*, u.username, u.nickname, c.name as category_name 
    FROM posts p 
    LEFT JOIN users u ON p.author_id = u.id 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'approved' 
    ORDER BY p.views DESC, p.likes DESC 
    LIMIT 5
");
$stmt->execute();
$hot_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取推荐帖子
$stmt = $pdo->prepare("
    SELECT p.*, u.username, u.nickname, c.name as category_name 
    FROM posts p 
    LEFT JOIN users u ON p.author_id = u.id 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'approved' 
    ORDER BY RAND() 
    LIMIT 5
");
$stmt->execute();
$recommended_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取导航分类
$categories = $forum->getCategories();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ML论坛 - 首页</title>
    <link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>/css/style.css">
    <link rel="icon" href="<?php echo ASSETS_PATH; ?>/images/favicon_ml.png" type="image/png">
    <style>
        /* 七彩文字效果 */
        .rainbow-text {
            background: linear-gradient(45deg, #ff0000, #ff9900, #ffff00, #33cc33, #3399ff, #9966ff, #ff00ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0;
        }
        
        /* 头部样式 */
        .header {
            background: #fff;
            border-bottom: 2px solid #e0e0e0;
            padding: 10px 0;
        }
        
        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo {
            height: 50px;
            width: auto;
        }
        
        .user-section {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .login-btn, .logout-btn {
            background: #e91e63;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: bold;
        }
        
        .user-link {
            color: #333;
            text-decoration: none;
            font-weight: bold;
        }
        
        /* 导航样式 */
        .main-nav {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .nav-list {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .nav-item {
            position: relative;
        }
        
        .nav-link {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            font-weight: 500;
        }
        
        .nav-link:hover {
            background: #e9ecef;
        }
        
        .subnav {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            list-style: none;
            margin: 0;
            padding: 0;
            min-width: 200px;
            display: none;
            z-index: 1000;
        }
        
        .nav-item:hover .subnav {
            display: block;
        }
        
        .subnav li a {
            display: block;
            padding: 10px 15px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .subnav li a:hover {
            background: #f8f9fa;
        }
        
        /* 公告区域 */
        .announcement-bar {
            background: #fff3cd;
            border-bottom: 1px solid #ffeaa7;
            padding: 8px 0;
        }
        
        .announcement-scroll {
            display: flex;
            gap: 30px;
            overflow: hidden;
            white-space: nowrap;
        }
        
        .announcement-item {
            color: #856404;
            text-decoration: none;
            font-weight: 500;
        }
        
        .announcement-item:hover {
            text-decoration: underline;
        }
        
        /* 主要内容 */
        .main-content {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .search-section {
            margin-bottom: 30px;
        }
        
        .search-form {
            display: flex;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #e91e63;
            border-right: none;
            border-radius: 25px 0 0 25px;
            font-size: 16px;
        }
        
        .search-btn {
            background: #e91e63;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 0 25px 25px 0;
            cursor: pointer;
            font-size: 16px;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section-title {
            color: #333;
            border-bottom: 2px solid #e91e63;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .post-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .post-card h3 {
            margin: 0 0 10px 0;
            font-size: 1.1rem;
        }
        
        .post-card h3 a {
            color: #333;
            text-decoration: none;
        }
        
        .post-card h3 a:hover {
            color: #e91e63;
        }
        
        .post-meta {
            font-size: 0.9rem;
            color: #666;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        /* 底部广告 */
        .ad-banner {
            background: white;
            border-top: 1px solid #e0e0e0;
            padding: 15px 0;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 999;
        }
        
        .ad-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: center;
            gap: 20px;
            padding: 0 20px;
        }
        
        .ad-item img {
            height: 60px;
            width: auto;
            border-radius: 5px;
        }
        
        /* 签到按钮 */
        .floating-signin-btn {
            position: fixed;
            right: 20px;
            bottom: 100px;
            z-index: 1000;
            cursor: move;
        }
        
        .signin-button {
            background: #e91e63;
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.3);
            transition: all 0.3s ease;
        }
        
        .signin-button:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
        }
        
        /* 模态框 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .announcement-modal h2 {
            color: #e91e63;
            margin-top: 0;
        }
        
        .announcement-content {
            margin: 20px 0;
            line-height: 1.6;
        }
        
        .modal-actions {
            text-align: right;
        }
        
        .modal-actions button {
            background: #e91e63;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
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
                <?php foreach ($categories as $category): ?>
                    <?php if ($category['parent_id'] == 0): ?>
                        <li class="nav-item">
                            <a href="<?php echo $category['url'] ?: (BASE_PATH . '/category.php?id=' . $category['id']); ?>" 
                               class="nav-link">
                                <?php echo escape($category['name']); ?>
                            </a>
                            <?php if (!empty($category['children'])): ?>
                                <ul class="subnav">
                                    <?php foreach ($category['children'] as $child): ?>
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

    <!-- 公告区域 -->
    <?php if (getSystemSetting('announcement_display', '1')): ?>
    <div class="announcement-bar">
        <div class="container">
            <div class="announcement-scroll">
                <?php
                $stmt = $pdo->query("SELECT * FROM announcements WHERE status = 1 ORDER BY created_at DESC LIMIT 5");
                $announcements = $stmt->fetchAll();
                foreach ($announcements as $announcement): ?>
                    <a href="<?php echo BASE_PATH; ?>/announcement.php?id=<?php echo $announcement['id']; ?>" class="announcement-item">
                        <?php echo escape($announcement['title']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="container main-content">
        <!-- 搜索框 -->
        <div class="search-section">
            <form action="<?php echo BASE_PATH; ?>/search.php" method="get" class="search-form">
                <input type="text" name="q" placeholder="搜索全站帖子..." class="search-input">
                <button type="submit" class="search-btn">搜索</button>
            </form>
        </div>

        <!-- 热门帖子 -->
        <div class="section">
            <h2 class="section-title">热门帖子</h2>
            <div class="posts-grid">
                <?php if (empty($hot_posts)): ?>
                    <div class="post-card">
                        <p>暂无热门帖子</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($hot_posts as $post): ?>
                        <div class="post-card">
                            <h3>
                                <a href="<?php echo BASE_PATH; ?>/post.php?id=<?php echo $post['id']; ?>">
                                    <?php echo escape($post['title']); ?>
                                </a>
                            </h3>
                            <div class="post-meta">
                                <span>作者: <?php echo escape($post['nickname'] ?: $post['username']); ?></span>
                                <span>分类: <?php echo escape($post['category_name']); ?></span>
                                <span>浏览: <?php echo $post['views']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 猜您喜欢 -->
        <div class="section">
            <h2 class="section-title">猜您喜欢</h2>
            <div class="posts-grid">
                <?php if (empty($recommended_posts)): ?>
                    <div class="post-card">
                        <p>暂无推荐帖子</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recommended_posts as $post): ?>
                        <div class="post-card">
                            <h3>
                                <a href="<?php echo BASE_PATH; ?>/post.php?id=<?php echo $post['id']; ?>">
                                    <?php echo escape($post['title']); ?>
                                </a>
                            </h3>
                            <div class="post-meta">
                                <span>作者: <?php echo escape($post['nickname'] ?: $post['username']); ?></span>
                                <span>分类: <?php echo escape($post['category_name']); ?></span>
                                <span>时间: <?php echo date('m-d H:i', strtotime($post['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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

    <script>
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

    // 检查未读公告
    <?php if (isLoggedIn()): ?>
    fetch('<?php echo BASE_PATH; ?>/ajax/check_announcement.php')
        .then(response => response.json())
        .then(data => {
            if (data.has_unread) {
                showAnnouncementModal(data.announcement);
            }
        })
        .catch(error => console.error('检查公告失败:', error));
    <?php endif; ?>

    function showAnnouncementModal(announcement) {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'block';
        modal.innerHTML = `
            <div class="modal-content announcement-modal">
                <h2>${announcement.title}</h2>
                <div class="announcement-content">${announcement.content}</div>
                <div class="modal-actions">
                    <button onclick="markAnnouncementRead(${announcement.id}, this)">我知道了</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    function markAnnouncementRead(announcementId, button) {
        fetch('<?php echo BASE_PATH; ?>/ajax/mark_announcement_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'announcement_id=' + announcementId
        }).then(() => {
            const modal = button.closest('.modal');
            if (modal) {
                modal.remove();
            }
        }).catch(error => console.error('标记公告已读失败:', error));
    }

    // 公告滚动效果
    let scrollPosition = 0;
    const announcementScroll = document.querySelector('.announcement-scroll');
    
    if (announcementScroll) {
        setInterval(() => {
            scrollPosition -= 1;
            if (scrollPosition < -announcementScroll.scrollWidth) {
                scrollPosition = announcementScroll.offsetWidth;
            }
            announcementScroll.style.transform = `translateX(${scrollPosition}px)`;
        }, 30);
    }
    </script>
</body>
</html>