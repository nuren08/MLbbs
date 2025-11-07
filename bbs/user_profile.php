<?php
require_once __DIR__ . '/includes/config.php';

// 获取要查看的用户ID
$user_id = $_GET['id'] ?? 0;
if (!$user_id) {
    redirect(BASE_PATH . '/index.php');
}

try {
    // 获取用户信息
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND status = 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        die('用户不存在或已被禁用');
    }
    
    // 获取当前登录用户信息
    $currentUser = getCurrentUser();
    $isOwnProfile = $currentUser && $currentUser['user_id'] == $user_id;
    
    // 获取用户统计数据
    $postsStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE author_id = ? AND status = 'approved'");
    $postsStmt->execute([$user['id']]);
    $postsCount = $postsStmt->fetchColumn();
    
    $followersStmt = $pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE following_id = ?");
    $followersStmt->execute([$user['id']]);
    $followersCount = $followersStmt->fetchColumn();
    
    $followingStmt = $pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ?");
    $followingStmt->execute([$user['id']]);
    $followingCount = $followingStmt->fetchColumn();
    
    // 检查关注状态
    $isFollowing = false;
    if ($currentUser) {
        $followStmt = $pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ? AND following_id = ?");
        $followStmt->execute([$currentUser['id'], $user['id']]);
        $isFollowing = $followStmt->fetchColumn() > 0;
    }
    
    // 检查拉黑状态
    $isBlocked = false;
    if ($currentUser) {
        $blockStmt = $pdo->prepare("SELECT COUNT(*) FROM user_blocks WHERE user_id = ? AND blocked_user_id = ?");
        $blockStmt->execute([$currentUser['id'], $user['id']]);
        $isBlocked = $blockStmt->fetchColumn() > 0;
    }
    
} catch (PDOException $e) {
    error_log("获取用户资料错误: " . $e->getMessage());
    die('系统错误，请稍后再试');
}

include __DIR__ . '/includes/header.php';
?>

<div class="row">
    <!-- 左侧个人信息 -->
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-body text-center">
                <!-- 用户头像 -->
                <img src="<?php echo $user['avatar'] ?: (ASSETS_PATH . '/images/tx_ml.png'); ?>" 
                     alt="用户头像" class="user-avatar-lg rounded-circle border mb-3">
                
                <!-- 用户基本信息 -->
                <h4 class="mb-1"><?php echo escape($user['nickname'] ?: $user['username']); ?></h4>
                <p class="text-muted mb-2">ID: <?php echo $user['user_id']; ?></p>
                <div class="level-badge mb-3 d-inline-block">
                    LV<?php echo getUserLevel($user['exp']); ?>
                </div>
                
                <!-- 个性签名 -->
                <?php if ($user['signature']): ?>
                <div class="border-top pt-3 mt-3">
                    <p class="text-muted mb-0">"<?php echo escape($user['signature']); ?>"</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 用户数据统计 -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="fw-bold fs-5"><?php echo $postsCount; ?></div>
                        <small class="text-muted">帖子</small>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold fs-5"><?php echo $followersCount; ?></div>
                        <small class="text-muted">粉丝</small>
                    </div>
                    <div class="col-4">
                        <div class="fw-bold fs-5"><?php echo $followingCount; ?></div>
                        <small class="text-muted">关注</small>
                    </div>
                </div>
                
                <div class="border-top mt-3 pt-3">
                    <div class="row small text-muted">
                        <div class="col-6">注册时间:</div>
                        <div class="col-6 text-end"><?php echo date('Y-m-d', strtotime($user['register_time'])); ?></div>
                    </div>
                    <?php if ($user['location_province']): ?>
                    <div class="row small text-muted">
                        <div class="col-6">所在地:</div>
                        <div class="col-6 text-end">
                            <?php echo escape($user['location_province']); ?>
                            <?php if ($user['location_city']) echo ' · ' . escape($user['location_city']); ?>
                            <?php if ($user['location_county']) echo ' · ' . escape($user['location_county']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 右侧内容区域 -->
    <div class="col-md-8">
        <!-- 操作按钮 -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($currentUser && !$isOwnProfile): ?>
                        <?php if ($user['allow_follow']): ?>
                            <?php if ($isFollowing): ?>
                            <button class="btn btn-outline-primary" onclick="toggleFollow(<?php echo $user['id']; ?>, false)">
                                <i class="fas fa-user-minus me-1"></i>取消关注
                            </button>
                            <?php else: ?>
                            <button class="btn btn-primary" onclick="toggleFollow(<?php echo $user['id']; ?>, true)">
                                <i class="fas fa-user-plus me-1"></i>关注
                            </button>
                            <?php endif; ?>
                            
                            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#messageModal">
                                <i class="fas fa-envelope me-1"></i>发送私信
                            </button>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary" disabled>
                                <i class="fas fa-user-slash me-1"></i>该用户禁止关注
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($isBlocked): ?>
                        <button class="btn btn-outline-warning" onclick="toggleBlock(<?php echo $user['id']; ?>, false)">
                            <i class="fas fa-ban me-1"></i>取消拉黑
                        </button>
                        <?php else: ?>
                        <button class="btn btn-warning" onclick="toggleBlock(<?php echo $user['id']; ?>, true)">
                            <i class="fas fa-ban me-1"></i>拉黑
                        </button>
                        <?php endif; ?>
                    <?php elseif ($isOwnProfile): ?>
                        <a href="<?php echo BASE_PATH; ?>/profile.php" class="btn btn-primary">
                            <i class="fas fa-edit me-1"></i>编辑我的资料
                        </a>
                    <?php else: ?>
                        <a href="<?php echo BASE_PATH; ?>/login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-1"></i>登录后操作
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- 用户的最新帖子 -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>最新帖子</h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    $postsStmt = $pdo->prepare("
                        SELECT p.*, c.name as category_name 
                        FROM posts p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        WHERE p.author_id = ? AND p.status = 'approved'
                        ORDER BY p.created_at DESC 
                        LIMIT 5
                    ");
                    $postsStmt->execute([$user['id']]);
                    $posts = $postsStmt->fetchAll();
                    
                    if (empty($posts)) {
                        echo '<div class="text-center py-4 text-muted">';
                        echo '<i class="fas fa-file-alt fa-2x mb-3"></i>';
                        echo '<p>该用户还没有发布过帖子</p>';
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
                    }
                } catch (PDOException $e) {
                    echo '<div class="alert alert-danger">加载帖子列表失败</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- 发送私信模态框 -->
<div class="modal fade" id="messageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">发送私信给 <?php echo escape($user['nickname'] ?: $user['username']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo BASE_PATH; ?>/send_message.php">
                <input type="hidden" name="to_user_id" value="<?php echo $user['id']; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <textarea class="form-control" name="message" rows="5" 
                                  placeholder="请输入私信内容（支持文字、表情和图片）" required></textarea>
                    </div>
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            私信内容若对方15天未接收，将从服务器删除，已接收的消息将立即从服务器删除，仅保存在用户设备本地。
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary">发送私信</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 关注/取消关注
function toggleFollow(userId, follow) {
    const action = follow ? 'follow' : 'unfollow';
    
    fetch('<?php echo BASE_PATH; ?>/ajax/toggle_follow.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}&action=${action}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('操作失败，请稍后重试');
    });
}

// 拉黑/取消拉黑
function toggleBlock(userId, block) {
    const action = block ? 'block' : 'unblock';
    
    if (block && !confirm('确定要拉黑该用户吗？拉黑后您将无法收到对方的私信。')) {
        return;
    }
    
    fetch('<?php echo BASE_PATH; ?>/ajax/toggle_block.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `user_id=${userId}&action=${action}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || '操作失败');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('操作失败，请稍后重试');
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>