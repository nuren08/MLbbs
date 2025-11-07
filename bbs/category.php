<?php
require_once 'includes/config.php';

// 获取分类ID
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 15;
$per_page = in_array($per_page, [15, 25]) ? $per_page : 15;

// 获取分类信息
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$category_id]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    die("分类不存在");
}

// 获取规则信息
$rules = $forum->getCategoryRules($category_id);

// 获取分类下的帖子
$offset = ($page - 1) * $per_page;
$stmt = $pdo->prepare("
    SELECT SQL_CALC_FOUND_ROWS p.*, u.username, u.nickname 
    FROM posts p 
    LEFT JOIN users u ON p.author_id = u.id 
    WHERE p.subcategory_id = ? AND p.status = 'approved' 
    ORDER BY p.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $category_id, PDO::PARAM_INT);
$stmt->bindValue(2, $per_page, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_stmt = $pdo->query("SELECT FOUND_ROWS()");
$total_posts = $total_stmt->fetchColumn();
$total_pages = ceil($total_posts / $per_page);

// 获取热门帖子
$stmt = $pdo->prepare("
    SELECT p.*, u.username, u.nickname 
    FROM posts p 
    LEFT JOIN users u ON p.author_id = u.id 
    WHERE p.subcategory_id = ? AND p.status = 'approved' 
    ORDER BY p.views DESC, p.likes DESC 
    LIMIT 5
");
$stmt->execute([$category_id]);
$hot_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取推荐帖子
$stmt = $pdo->prepare("
    SELECT p.*, u.username, u.nickname 
    FROM posts p 
    LEFT JOIN users u ON p.author_id = u.id 
    WHERE p.subcategory_id = ? AND p.status = 'approved' 
    ORDER BY RAND() 
    LIMIT 5
");
$stmt->execute([$category_id]);
$recommended_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取导航分类
$categories = $forum->getCategories();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($category['name']); ?> - ML论坛</title>
    <link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>/css/style.css">
    <link rel="icon" href="<?php echo ASSETS_PATH; ?>/images/favicon_ml.png" type="image/png">
    <style>
        /* 复用首页的样式，这里只添加分类页面特有的样式 */
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e91e63;
        }
        
        .category-header h1 {
            color: #333;
            margin: 0;
        }
        
        .category-actions {
            display: flex;
            gap: 15px;
        }
        
        .rule-btn, .new-post-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .rule-btn {
            background: #6c757d;
            color: white;
        }
        
        .new-post-btn {
            background: #e91e63;
            color: white;
        }
        
        .rule-btn:hover, .new-post-btn:hover {
            opacity: 0.9;
        }
        
        /* 规则模态框 */
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
            margin: 10% auto;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }
        
        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
        
        .close:hover {
            color: #000;
        }
        
        .rules-content {
            line-height: 1.6;
            margin-top: 20px;
        }
        
        .rules-content p {
            margin-bottom: 10px;
            padding-left: 10px;
        }
        
        /* 帖子列表样式 */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .per-page-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .per-page-selector select {
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .posts-list {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .post-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s ease;
        }
        
        .post-item:hover {
            background: #f8f9fa;
        }
        
        .post-item:last-child {
            border-bottom: none;
        }
        
        .post-title {
            margin-bottom: 8px;
        }
        
        .post-title a {
            color: #333;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .post-title a:hover {
            color: #e91e63;
        }
        
        .post-info {
            display: flex;
            gap: 15px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .no-posts {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            font-size: 1.1rem;
        }
        
        /* 分页样式 */
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
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background: #f8f9fa;
        }
        
        .pagination a.active {
            background: #e91e63;
            color: white;
            border-color: #e91e63;
        }
        
        /* 发帖按钮悬浮 */
        .floating-post-btn {
            position: fixed;
            right: 20px;
            bottom: 180px;
            z-index: 1000;
            cursor: move;
        }
        
        .post-button {
            background: #e91e63;
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.3);
            transition: all 0.3s ease;
        }
        
        .post-button:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(233, 30, 99, 0.4);
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
                        <li class="nav-item <?php echo $category_item['id'] == $category['id'] ? 'active' : ''; ?>">
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
        <!-- 分类头部 -->
        <div class="category-header">
            <h1><?php echo escape($category['name']); ?></h1>
            <div class="category-actions">
                <button class="rule-btn" onclick="showRules()">规则</button>
                <?php if (isLoggedIn()): ?>
                    <a href="<?php echo BASE_PATH; ?>/new_post.php?category=<?php echo $category_id; ?>" class="new-post-btn">发帖</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 规则弹窗 -->
        <div id="rulesModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeRules()">&times;</span>
                <h3><?php echo escape($category['name']); ?> - 板块规则</h3>
                <div class="rules-content">
                    <?php if ($rules): ?>
                        <p><strong><?php echo $rules['description']; ?></strong></p>
                        <?php if ($rules['type'] === 'general'): ?>
                            <p>• 下载附件扣取 <?php echo $rules['download_points']; ?> 积分</p>
                            <p>• 发帖可获得积分和经验奖励</p>
                            <p>• 支持上传各种格式的资源文件</p>
                            <p>• 评论区支持富文本编辑</p>
                        <?php elseif ($rules['type'] === 'treehole'): ?>
                            <p>• 发帖提问扣取 <?php echo $rules['post_points']; ?> 积分</p>
                            <p>• 帖子有效期 <?php echo $rules['expiry_days']; ?> 天</p>
                            <p>• 可匿名提问，可申请关闭帖子</p>
                            <p>• 回答人之间下载附件免积分</p>
                        <?php elseif ($rules['type'] === 'promotion'): ?>
                            <p>• 发帖扣取 <?php echo $rules['post_points']; ?> 积分</p>
                            <p>• 帖子有效期 <?php echo $rules['expiry_days']; ?> 天</p>
                            <p>• 浏览和评论广告有额外积分奖励</p>
                            <p>• 评论区仅支持文字和图片</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>暂无规则说明</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 搜索框 -->
        <div class="search-section">
            <form action="<?php echo BASE_PATH; ?>/search.php" method="get" class="search-form">
                <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                <input type="text" name="q" placeholder="搜索本分类帖子..." class="search-input">
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
                                <span>浏览: <?php echo $post['views']; ?></span>
                                <span>时间: <?php echo date('m-d H:i', strtotime($post['created_at'])); ?></span>
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
                                <span>浏览: <?php echo $post['views']; ?></span>
                                <span>时间: <?php echo date('m-d H:i', strtotime($post['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 帖子列表 -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">帖子列表</h2>
                <div class="per-page-selector">
                    <label>每页显示：</label>
                    <select onchange="changePerPage(this.value)">
                        <option value="15" <?php echo $per_page == 15 ? 'selected' : ''; ?>>15</option>
                        <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                    </select>
                </div>
            </div>
            
            <div class="posts-list">
                <?php if (empty($posts)): ?>
                    <div class="no-posts">暂无帖子</div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="post-item">
                            <div class="post-title">
                                <a href="<?php echo BASE_PATH; ?>/post.php?id=<?php echo $post['id']; ?>">
                                    <?php echo escape($post['title']); ?>
                                </a>
                            </div>
                            <div class="post-info">
                                <span>作者: <?php echo escape($post['nickname'] ?: $post['username']); ?></span>
                                <span>浏览: <?php echo $post['views']; ?></span>
                                <span>时间: <?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?id=<?php echo $category_id; ?>&page=<?php echo $i; ?>&per_page=<?php echo $per_page; ?>" 
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

    <!-- 发帖按钮 -->
    <?php if (isLoggedIn()): ?>
    <div id="post-btn" class="floating-post-btn">
        <button onclick="location.href='<?php echo BASE_PATH; ?>/new_post.php?category=<?php echo $category_id; ?>'" class="post-button">发帖</button>
    </div>
    <?php endif; ?>

    <script>
    function changePerPage(value) {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', value);
        url.searchParams.set('page', '1'); // 重置到第一页
        window.location.href = url.toString();
    }

    function showRules() {
        document.getElementById('rulesModal').style.display = 'block';
    }

    function closeRules() {
        document.getElementById('rulesModal').style.display = 'none';
    }

    // 点击模态框外部关闭
    window.onclick = function(event) {
        const modal = document.getElementById('rulesModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // 使签到按钮可拖动
    document.addEventListener('DOMContentLoaded', function() {
        const signinBtn = document.getElementById('signin-btn');
        const postBtn = document.getElementById('post-btn');
        
        function makeDraggable(element) {
            let isDragging = false;
            let currentX, currentY, initialX, initialY, xOffset = 0, yOffset = 0;

            element.addEventListener('mousedown', dragStart);
            document.addEventListener('mouseup', dragEnd);
            document.addEventListener('mousemove', drag);

            function dragStart(e) {
                initialX = e.clientX - xOffset;
                initialY = e.clientY - yOffset;
                if (e.target === element || e.target.classList.contains('signin-button') || e.target.classList.contains('post-button')) {
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
                    setTranslate(currentX, currentY, element);
                }
            }

            function setTranslate(xPos, yPos, el) {
                el.style.transform = `translate3d(${xPos}px, ${yPos}px, 0)`;
            }
        }

        if (signinBtn) makeDraggable(signinBtn);
        if (postBtn) makeDraggable(postBtn);
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
    </script>
</body>
</html>