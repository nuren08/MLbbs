<?php
require_once 'includes/config.php';

$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 15;

$results = [];
$total_results = 0;
$total_pages = 0;

if (!empty($keyword)) {
    // 记录搜索日志（如果用户已登录）
    if (isLoggedIn()) {
        $currentUser = getCurrentUser();
        try {
            $stmt = $pdo->prepare("INSERT INTO search_logs (user_id, keyword) VALUES (?, ?)");
            $stmt->execute([$currentUser['id'], $keyword]);
        } catch (PDOException $e) {
            error_log("记录搜索日志失败: " . $e->getMessage());
        }
    }

    // 构建搜索查询
    $where_conditions = ["p.status = 'approved'"];
    $params = [];
    
    // 关键词搜索条件
    $keyword_conditions = [];
    $keyword_words = explode(' ', $keyword);
    foreach ($keyword_words as $word) {
        if (!empty(trim($word))) {
            $keyword_conditions[] = "(p.title LIKE ? OR p.content LIKE ?)";
            $params[] = "%{$word}%";
            $params[] = "%{$word}%";
        }
    }
    
    if (!empty($keyword_conditions)) {
        $where_conditions[] = "(" . implode(" AND ", $keyword_conditions) . ")";
    }
    
    // 分类筛选条件
    if ($category_id > 0) {
        $where_conditions[] = "p.subcategory_id = ?";
        $params[] = $category_id;
    }
    
    $where_sql = implode(" AND ", $where_conditions);
    
    // 获取搜索结果
    $offset = ($page - 1) * $per_page;
    
    try {
        $sql = "
            SELECT SQL_CALC_FOUND_ROWS 
                   p.*, u.username, u.nickname, 
                   c.name as category_name, sc.name as subcategory_name
            FROM posts p
            LEFT JOIN users u ON p.author_id = u.id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN categories sc ON p.subcategory_id = sc.id
            WHERE {$where_sql}
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $params[] = $per_page;
        $params[] = $offset;
        
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_stmt = $pdo->query("SELECT FOUND_ROWS()");
        $total_results = $total_stmt->fetchColumn();
        $total_pages = ceil($total_results / $per_page);
        
    } catch (PDOException $e) {
        error_log("搜索查询失败: " . $e->getMessage());
    }
}

// 获取导航分类
$categories = $forum->getCategories();

// 获取当前分类信息（如果指定了分类）
$current_category = null;
if ($category_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $current_category = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>搜索 - ML论坛</title>
    <link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>/css/style.css">
    <link rel="icon" href="<?php echo ASSETS_PATH; ?>/images/favicon_ml.png" type="image/png">
    <style>
        .search-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .search-header {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #e91e63;
            border-radius: 25px;
            font-size: 16px;
        }
        
        .search-btn {
            background: #e91e63;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .search-filters {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .category-select {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }
        
        .results-info {
            color: #666;
            margin-bottom: 20px;
        }
        
        .results-list {
            background: white;
            border-radius: 8px;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .result-item {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.3s ease;
        }
        
        .result-item:hover {
            background: #f8f9fa;
        }
        
        .result-item:last-child {
            border-bottom: none;
        }
        
        .result-title {
            font-size: 1.2rem;
            margin-bottom: 8px;
        }
        
        .result-title a {
            color: #333;
            text-decoration: none;
        }
        
        .result-title a:hover {
            color: #e91e63;
        }
        
        .result-content {
            color: #666;
            line-height: 1.5;
            margin-bottom: 10px;
            max-height: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .result-meta {
            display: flex;
            gap: 15px;
            font-size: 0.9rem;
            color: #999;
        }
        
        .highlight {
            background: yellow;
            font-weight: bold;
        }
        
        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .no-results i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ccc;
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
        
        .search-suggestions {
            margin-top: 20px;
        }
        
        .suggestion-title {
            font-size: 1rem;
            margin-bottom: 10px;
            color: #666;
        }
        
        .suggestion-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .suggestion-tag {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 5px 10px;
            border-radius: 15px;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .suggestion-tag:hover {
            background: #e91e63;
            color: white;
            border-color: #e91e63;
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

    <div class="search-container">
        <!-- 搜索头部 -->
        <div class="search-header">
            <form method="GET" class="search-form">
                <input type="text" name="q" value="<?php echo escape($keyword); ?>" 
                       placeholder="搜索帖子..." class="search-input" required>
                <button type="submit" class="search-btn">搜索</button>
            </form>
            
            <div class="search-filters">
                <select name="category" class="category-select" onchange="updateCategoryFilter(this.value)">
                    <option value="0">全部分类</option>
                    <?php foreach ($categories as $category): ?>
                        <?php if ($category['parent_id'] == 0 && !empty($category['children'])): ?>
                            <optgroup label="<?php echo escape($category['name']); ?>">
                                <?php foreach ($category['children'] as $child): ?>
                                    <option value="<?php echo $child['id']; ?>" 
                                        <?php echo $category_id == $child['id'] ? 'selected' : ''; ?>>
                                        <?php echo escape($child['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                
                <?php if (!empty($keyword)): ?>
                    <div class="results-info">
                        找到约 <?php echo $total_results; ?> 条结果
                        <?php if ($current_category): ?>
                            （分类：<?php echo escape($current_category['name']); ?>）
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 搜索建议 -->
            <?php if (empty($keyword)): ?>
                <div class="search-suggestions">
                    <div class="suggestion-title">热门搜索：</div>
                    <div class="suggestion-tags">
                        <a href="?q=技术" class="suggestion-tag">技术</a>
                        <a href="?q=编程" class="suggestion-tag">编程</a>
                        <a href="?q=生活" class="suggestion-tag">生活</a>
                        <a href="?q=美食" class="suggestion-tag">美食</a>
                        <a href="?q=旅游" class="suggestion-tag">旅游</a>
                        <a href="?q=学习" class="suggestion-tag">学习</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- 搜索结果 -->
        <?php if (!empty($keyword)): ?>
            <div class="results-list">
                <?php if (empty($results)): ?>
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <p>没有找到与 "<?php echo escape($keyword); ?>" 相关的结果</p>
                        <p class="small text-muted">请尝试使用其他关键词或调整搜索条件</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($results as $result): ?>
                        <div class="result-item">
                            <div class="result-title">
                                <a href="<?php echo BASE_PATH; ?>/post.php?id=<?php echo $result['id']; ?>">
                                    <?php echo highlightKeywords(escape($result['title']), $keyword); ?>
                                </a>
                            </div>
                            <div class="result-content">
                                <?php echo highlightKeywords(getContentPreview(escape($result['content']), $keyword); ?>
                            </div>
                            <div class="result-meta">
                                <span>作者: <?php echo escape($result['nickname'] ?: $result['username']); ?></span>
                                <span>分类: <?php echo escape($result['category_name']); ?> &gt; <?php echo escape($result['subcategory_name']); ?></span>
                                <span>时间: <?php echo date('Y-m-d H:i', strtotime($result['created_at'])); ?></span>
                                <span>浏览: <?php echo $result['views']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?q=<?php echo urlencode($keyword); ?>&category=<?php echo $category_id; ?>&page=<?php echo $i; ?>" 
                           class="<?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
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
    function updateCategoryFilter(categoryId) {
        const url = new URL(window.location);
        if (categoryId > 0) {
            url.searchParams.set('category', categoryId);
        } else {
            url.searchParams.delete('category');
        }
        window.location.href = url.toString();
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

<?php
// 高亮关键词函数
function highlightKeywords($text, $keywords) {
    if (empty($keywords)) {
        return $text;
    }
    
    $keyword_array = explode(' ', $keywords);
    foreach ($keyword_array as $keyword) {
        $keyword = trim($keyword);
        if (!empty($keyword)) {
            $text = preg_replace(
                '/(' . preg_quote($keyword, '/') . ')/i',
                '<span class="highlight">$1</span>',
                $text
            );
        }
    }
    
    return $text;
}

// 获取内容预览函数
function getContentPreview($content, $max_length = 150) {
    // 移除HTML标签
    $plain_text = strip_tags($content);
    
    // 截取指定长度
    if (mb_strlen($plain_text) > $max_length) {
        $plain_text = mb_substr($plain_text, 0, $max_length) . '...';
    }
    
    return $plain_text;
}
?>