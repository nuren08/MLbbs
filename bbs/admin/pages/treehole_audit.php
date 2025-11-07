<?php
// 获取待审核的树洞关闭申请
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$per_page = 20;

$where = "WHERE p.rule_type = 'treehole' AND p.close_request = 1 AND p.close_status = 'pending'";
$params = [];

// 搜索处理
$search = isset($_GET['search']) ? escape($_GET['search']) : '';
if (!empty($search)) {
    $where .= " AND (p.title LIKE ? OR u.username LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term]);
}

$stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS p.*, u.username, u.nickname, c.name as category_name,
                      (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id) as reply_count
                      FROM posts p 
                      LEFT JOIN users u ON p.author_id = u.id 
                      LEFT JOIN categories c ON p.category_id = c.id 
                      $where 
                      ORDER BY p.created_at DESC 
                      LIMIT ? OFFSET ?");
$params[] = $per_page;
$params[] = ($page - 1) * $per_page;

$stmt->execute($params);
$applications = $stmt->fetchAll();

$total_stmt = $pdo->query("SELECT FOUND_ROWS()");
$total_applications = $total_stmt->fetchColumn();
$total_pages = ceil($total_applications / $per_page);

// 处理审核操作
if (isset($_GET['action']) && isset($_GET['id'])) {
    $post_id = intval($_GET['id']);
    $action = escape($_GET['action']);
    
    if (in_array($action, ['approve', 'reject'])) {
        $new_status = $action == 'approve' ? 'approved' : 'rejected';
        
        try {
            // 更新帖子状态
            $stmt = $pdo->prepare("UPDATE posts SET close_status = ? WHERE id = ?");
            $stmt->execute([$new_status, $post_id]);
            
            // 如果审核通过，退还积分给用户
            if ($new_status == 'approved') {
                $post = $pdo->prepare("SELECT author_id FROM posts WHERE id = ?");
                $post->execute([$post_id]);
                $post_data = $post->fetch();
                
                $treehole_points = getSystemSetting('treehole_rule_points', 100);
                
                // 退还积分给用户
                $update_user = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                $update_user->execute([$treehole_points, $post_data['author_id']]);
                
                // 记录积分退还
                $log = $pdo->prepare("INSERT INTO points_log (user_id, points, exp, type, description) VALUES (?, ?, 0, 'admin_adjust', ?)");
                $log->execute([$post_data['author_id'], $treehole_points, '树洞关闭申请通过，积分退还']);
                
                // 记录关闭申请
                $close_request = $pdo->prepare("INSERT INTO treehole_close_requests (post_id, user_id, status, admin_notes) VALUES (?, ?, 'approved', '管理员审核通过')");
                $close_request->execute([$post_id, $post_data['author_id']]);
            } else {
                // 记录驳回申请
                $close_request = $pdo->prepare("INSERT INTO treehole_close_requests (post_id, user_id, status, admin_notes) VALUES (?, ?, 'rejected', '管理员审核驳回')");
                $close_request->execute([$post_id, $post_data['author_id']]);
            }
            
            echo '<script>showMessage("操作成功"); setTimeout(() => window.location.reload(), 1000);</script>';
        } catch (PDOException $e) {
            echo '<script>showMessage("操作失败: ' . $e->getMessage() . '", "error");</script>';
        }
    }
}
?>

<div class="page-header">
    <h2>树洞申请审核</h2>
    <div class="header-actions">
        <form method="GET" class="search-form">
            <input type="hidden" name="page" value="treehole_audit">
            <input type="text" name="search" placeholder="搜索帖子标题或作者..." value="<?= $search ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
</div>

<div class="card">
    <h3>待审核树洞关闭申请 (<?= $total_applications ?>)</h3>
    
    <?php if (empty($applications)): ?>
        <div class="no-data">暂无待审核的树洞关闭申请</div>
    <?php else: ?>
        <div class="applications-list">
            <?php foreach ($applications as $app): ?>
                <div class="application-card">
                    <div class="application-header">
                        <div class="application-info">
                            <h4>
                                <a href="<?= BASE_PATH ?>/post.php?id=<?= $app['id'] ?>" target="_blank">
                                    <?= $app['title'] ?>
                                </a>
                            </h4>
                            <div class="application-meta">
                                <span>作者: <?= $app['nickname'] ?? $app['username'] ?></span>
                                <span>发布时间: <?= date('Y-m-d H:i', strtotime($app['created_at'])) ?></span>
                                <span>回复数: <?= $app['reply_count'] ?></span>
                                <span>过期时间: <?= $app['expiry_date'] ? date('Y-m-d H:i', strtotime($app['expiry_date'])) : '未设置' ?></span>
                            </div>
                        </div>
                        <div class="application-actions">
                            <button onclick="viewPostDetails(<?= $app['id'] ?>)" class="btn btn-info" title="查看详情">
                                <i class="fas fa-eye"></i> 查看详情
                            </button>
                            <button onclick="approveApplication(<?= $app['id'] ?>)" class="btn btn-success" title="通过申请">
                                <i class="fas fa-check"></i> 通过
                            </button>
                            <button onclick="rejectApplication(<?= $app['id'] ?>)" class="btn btn-danger" title="驳回申请">
                                <i class="fas fa-times"></i> 驳回
                            </button>
                        </div>
                    </div>
                    
                    <!-- 帖子内容预览 -->
                    <div class="application-content">
                        <h5>帖子内容预览：</h5>
                        <div class="content-preview">
                            <?= mb_substr(strip_tags($app['content']), 0, 200) ?>
                            <?= mb_strlen(strip_tags($app['content'])) > 200 ? '...' : '' ?>
                        </div>
                    </div>
                    
                    <!-- 回复预览 -->
                    <div class="application-replies">
                        <h5>最新回复：</h5>
                        <?php
                        $replies = $pdo->prepare("SELECT r.content, u.username, u.nickname, r.created_at 
                                                 FROM replies r 
                                                 LEFT JOIN users u ON r.user_id = u.id 
                                                 WHERE r.post_id = ? 
                                                 ORDER BY r.created_at DESC 
                                                 LIMIT 3");
                        $replies->execute([$app['id']]);
                        $post_replies = $replies->fetchAll();
                        ?>
                        
                        <?php if (empty($post_replies)): ?>
                            <div class="no-replies">暂无回复</div>
                        <?php else: ?>
                            <?php foreach ($post_replies as $reply): ?>
                                <div class="reply-item">
                                    <div class="reply-author">
                                        <strong><?= $reply['nickname'] ?? $reply['username'] ?></strong>
                                        <span class="reply-time"><?= date('m-d H:i', strtotime($reply['created_at'])) ?></span>
                                    </div>
                                    <div class="reply-content">
                                        <?= mb_substr(strip_tags($reply['content']), 0, 100) ?>
                                        <?= mb_strlen(strip_tags($reply['content'])) > 100 ? '...' : '' ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- 分页 -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=treehole_audit&p=<?= $i ?>&search=<?= urlencode($search) ?>" 
                       class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- 查看详情模态框 -->
<div id="postDetailsModal" class="modal">
    <div class="modal-content large">
        <div class="modal-header">
            <h3>帖子详情</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="postDetailsContent">
            <!-- 内容将通过AJAX加载 -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('postDetailsModal')">关闭</button>
        </div>
    </div>
</div>

<script>
function viewPostDetails(postId) {
    // 显示加载中
    document.getElementById('postDetailsContent').innerHTML = '<div class="loading">加载中...</div>';
    openModal('postDetailsModal');
    
    // 加载帖子详情
    fetch(`<?= BASE_PATH ?>/ajax/get_post_details.php?id=${postId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('postDetailsContent').innerHTML = data.html;
            } else {
                document.getElementById('postDetailsContent').innerHTML = `<div class="error">加载失败: ${data.message}</div>`;
            }
        })
        .catch(error => {
            document.getElementById('postDetailsContent').innerHTML = `<div class="error">网络错误: ${error}</div>`;
        });
}

function approveApplication(id) {
    if (confirm('确定要通过这个关闭申请吗？系统将退还100积分给用户。')) {
        window.location.href = '?page=treehole_audit&action=approve&id=' + id;
    }
}

function rejectApplication(id) {
    if (confirm('确定要驳回这个关闭申请吗？')) {
        window.location.href = '?page=treehole_audit&action=reject&id=' + id;
    }
}

function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// 关闭模态框
document.querySelectorAll('.modal-close').forEach(button => {
    button.addEventListener('click', function() {
        this.closest('.modal').style.display = 'none';
    });
});

// 点击模态框外部关闭
window.addEventListener('click', function(event) {
    document.querySelectorAll('.modal').forEach(modal => {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<style>
.applications-list {
    display: grid;
    gap: 20px;
}

.application-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    background: white;
}

.application-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.application-info h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.application-info h4 a {
    color: #007bff;
    text-decoration: none;
}

.application-info h4 a:hover {
    text-decoration: underline;
}

.application-meta {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    font-size: 14px;
    color: #6c757d;
}

.application-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

.application-content, .application-replies {
    margin-bottom: 15px;
}

.application-content h5, .application-replies h5 {
    margin: 0 0 10px 0;
    color: #495057;
    font-size: 14px;
}

.content-preview {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    font-size: 14px;
    line-height: 1.5;
}

.reply-item {
    border-bottom: 1px solid #eee;
    padding: 10px 0;
}

.reply-item:last-child {
    border-bottom: none;
}

.reply-author {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.reply-author strong {
    color: #333;
    font-size: 14px;
}

.reply-time {
    color: #6c757d;
    font-size: 12px;
}

.reply-content {
    color: #555;
    font-size: 13px;
    line-height: 1.4;
}

.no-replies {
    color: #6c757d;
    font-style: italic;
    text-align: center;
    padding: 10px;
}

.modal-content.large {
    width: 800px;
    max-width: 95%;
}

.loading, .error {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.error {
    color: #dc3545;
}

@media (max-width: 768px) {
    .application-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .application-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .application-meta {
        flex-direction: column;
        gap: 5px;
    }
}
</style>