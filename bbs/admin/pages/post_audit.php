<?php
// 获取待审核的帖子
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$per_page = 20;

$where = "WHERE p.status = 'pending'";
$params = [];

// 搜索处理
$search = isset($_GET['search']) ? escape($_GET['search']) : '';
if (!empty($search)) {
    $where .= " AND (p.title LIKE ? OR u.username LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term]);
}

$stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS p.*, u.username, u.nickname, c.name as category_name 
                       FROM posts p 
                       LEFT JOIN users u ON p.author_id = u.id 
                       LEFT JOIN categories c ON p.category_id = c.id 
                       $where 
                       ORDER BY p.created_at DESC 
                       LIMIT ? OFFSET ?");
$params[] = $per_page;
$params[] = ($page - 1) * $per_page;

$stmt->execute($params);
$posts = $stmt->fetchAll();

$total_stmt = $pdo->query("SELECT FOUND_ROWS()");
$total_posts = $total_stmt->fetchColumn();
$total_pages = ceil($total_posts / $per_page);

// 处理审核操作
if (isset($_GET['action']) && isset($_GET['id'])) {
    $post_id = intval($_GET['id']);
    $action = escape($_GET['action']);
    
    if (in_array($action, ['approve', 'reject'])) {
        $new_status = $action == 'approve' ? 'approved' : 'rejected';
        
        try {
            $stmt = $pdo->prepare("UPDATE posts SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $post_id]);
            
            // 如果审核通过，给作者发放奖励
            if ($new_status == 'approved') {
                $post = $pdo->prepare("SELECT author_id, rule_type FROM posts WHERE id = ?");
                $post->execute([$post_id]);
                $post_data = $post->fetch();
                
                $user = $pdo->prepare("SELECT level, exp, points FROM users WHERE id = ?");
                $user->execute([$post_data['author_id']]);
                $user_data = $user->fetch();
                
                $level = $user_data['level'];
                $rewards = getLevelRewards($level);
                $points = $rewards['post'];
                $exp = $points;
                
                // 树洞规则发帖不奖励积分，反而扣积分
                if ($post_data['rule_type'] == 'treehole') {
                    $treehole_points = getSystemSetting('treehole_rule_points', 100);
                    $points = -$treehole_points;
                    $exp = 0;
                }
                
                // 推广规则发帖不奖励积分，反而扣积分
                if ($post_data['rule_type'] == 'promotion') {
                    $promotion_points = getSystemSetting('promotion_rule_points', 300);
                    $points = -$promotion_points;
                    $exp = 0;
                }
                
                // 更新用户积分和经验
                $update_user = $pdo->prepare("UPDATE users SET points = points + ?, exp = exp + ? WHERE id = ?");
                $update_user->execute([$points, $exp, $post_data['author_id']]);
                
                // 记录积分变动
                $log = $pdo->prepare("INSERT INTO points_log (user_id, points, exp, type, description) VALUES (?, ?, ?, 'post', ?)");
                $log->execute([$post_data['author_id'], $points, $exp, '帖子审核通过']);
            }
            
            echo '<script>showMessage("操作成功"); setTimeout(() => window.location.reload(), 1000);</script>';
        } catch (PDOException $e) {
            echo '<script>showMessage("操作失败: ' . $e->getMessage() . '", "error");</script>';
        }
    }
}

// 处理删除帖子
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$delete_id]);
        echo '<script>showMessage("帖子删除成功"); setTimeout(() => window.location.reload(), 1000);</script>';
    } catch (PDOException $e) {
        echo '<script>showMessage("删除失败: ' . $e->getMessage() . '", "error");</script>';
    }
}
?>

<div class="page-header">
    <h2>帖子审核</h2>
    <div class="header-actions">
        <form method="GET" class="search-form">
            <input type="hidden" name="page" value="post_audit">
            <input type="text" name="search" placeholder="搜索帖子标题或作者..." value="<?= $search ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
</div>

<div class="card">
    <h3>待审核帖子 (<?= $total_posts ?>)</h3>
    
    <?php if (empty($posts)): ?>
        <div class="no-data">暂无待审核帖子</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>标题</th>
                        <th>作者</th>
                        <th>分类</th>
                        <th>规则</th>
                        <th>发帖时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td><?= $post['id'] ?></td>
                            <td>
                                <a href="<?= BASE_PATH ?>/post.php?id=<?= $post['id'] ?>" target="_blank">
                                    <?= mb_substr($post['title'], 0, 30) ?>
                                    <?= mb_strlen($post['title']) > 30 ? '...' : '' ?>
                                </a>
                            </td>
                            <td><?= $post['nickname'] ?? $post['username'] ?></td>
                            <td><?= $post['category_name'] ?></td>
                            <td>
                                <span class="badge badge-<?= $post['rule_type'] ?>">
                                    <?= $post['rule_type'] ?>
                                </span>
                            </td>
                            <td><?= date('Y-m-d H:i', strtotime($post['created_at'])) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="<?= BASE_PATH ?>/post.php?id=<?= $post['id'] ?>" target="_blank" class="btn btn-sm btn-info" title="查看">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button onclick="approvePost(<?= $post['id'] ?>)" class="btn btn-sm btn-success" title="通过">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button onclick="rejectPost(<?= $post['id'] ?>)" class="btn btn-sm btn-danger" title="驳回">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <button onclick="deletePost(<?= $post['id'] ?>)" class="btn btn-sm btn-warning" title="删除">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 分页 -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=post_audit&p=<?= $i ?>&search=<?= urlencode($search) ?>" 
                       class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function approvePost(id) {
    if (confirm('确定要通过这个帖子吗？')) {
        window.location.href = '?page=post_audit&action=approve&id=' + id;
    }
}

function rejectPost(id) {
    if (confirm('确定要驳回这个帖子吗？')) {
        window.location.href = '?page=post_audit&action=reject&id=' + id;
    }
}

function deletePost(id) {
    if (confirm('确定要删除这个帖子吗？此操作不可恢复！')) {
        window.location.href = '?page=post_audit&delete_id=' + id;
    }
}
</script>

<style>
.search-form {
    display: flex;
    gap: 10px;
}

.search-form input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    width: 300px;
}

.search-form button {
    background: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 8px 15px;
    cursor: pointer;
}

.pagination {
    display: flex;
    gap: 5px;
    margin-top: 20px;
    justify-content: center;
}

.pagination a {
    padding: 8px 12px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    text-decoration: none;
    color: #007bff;
}

.pagination a.active {
    background: #007bff;
    color: white;
    border-color: #007bff;
}

.pagination a:hover:not(.active) {
    background: #f8f9fa;
}
</style>