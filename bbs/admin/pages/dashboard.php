<?php
$stats = getAdminStats();
?>
<div class="dashboard">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-eye"></i>
            </div>
            <div class="stat-info">
                <h3><?= $stats['today_visits'] ?></h3>
                <p>今日访问量</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3><?= $stats['total_users'] ?></h3>
                <p>总用户数</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="stat-info">
                <h3><?= $stats['today_registers'] ?></h3>
                <p>今日注册数</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card danger">
                <div class="stat-icon danger">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['pending_posts'] ?></h3>
                    <p>待审核帖子</p>
                </div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-comments"></i>
            </div>
            <div class="stat-info">
                <h3><?= $stats['total_posts'] ?></h3>
                <p>总帖子数</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card secondary">
                <div class="stat-icon secondary">
                    <i class="fas fa-question-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['pending_treehole'] ?></h3>
                    <p>树洞待审核</p>
                </div>
            </div>
        </div>
    </div>

    <div class="quick-actions">
        <h2>快捷操作</h2>
        <div class="action-grid">
            <a href="?page=post_audit" class="action-card">
                <i class="fas fa-file-alt"></i>
                <span>审核帖子</span>
                <?php if ($stats['pending_posts'] > 0): ?>
                    <span class="action-badge"><?= $stats['pending_posts'] ?></span>
                <?php endif; ?>
            </a>
            
            <a href="?page=treehole_audit" class="action-card">
                <i class="fas fa-question-circle"></i>
                <span>树洞审核</span>
                <?php if ($stats['pending_treehole'] > 0): ?>
                    <span class="action-badge"><?= $stats['pending_treehole'] ?></span>
                <?php endif; ?>
            </a>
            
            <a href="?page=user_manage" class="action-card">
                <i class="fas fa-users"></i>
                <span>用户管理</span>
            </a>
            
            <a href="?page=announcement_manage" class="action-card">
                <i class="fas fa-bullhorn"></i>
                <span>发布公告</span>
            </a>
            
            <a href="?page=category_manage" class="action-card">
                <i class="fas fa-sitemap"></i>
                <span>板块管理</span>
            </a>
            
            <a href="?page=send_message" class="action-card">
                <i class="fas fa-paper-plane"></i>
                <span>发送消息</span>
            </a>
        </div>
    </div>

    <div class="recent-activities">
        <h2>最近活动</h2>
        <div class="activity-list">
            <?php
            $recentActivities = getRecentActivities();
            if (empty($recentActivities)): ?>
                <div class="no-data">暂无最近活动</div>
            <?php else: ?>
                <?php foreach ($recentActivities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="<?= $activity['icon'] ?>"></i>
                        </div>
                        <div class="activity-content">
                            <p><?= $activity['content'] ?></p>
                            <span class="activity-time"><?= $activity['time'] ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
function getRecentActivities() {
    global $pdo;
    $activities = [];
    
    try {
        // 获取最近注册的用户
        $stmt = $pdo->query("SELECT username, register_time FROM users ORDER BY register_time DESC LIMIT 5");
        $recentUsers = $stmt->fetchAll();
        
        foreach ($recentUsers as $user) {
            $activities[] = [
                'icon' => 'fas fa-user-plus',
                'content' => "新用户 {$user['username']} 注册",
                'time' => date('m-d H:i', strtotime($user['register_time']))
            ];
        }
        
        // 获取最近发布的帖子
        $stmt = $pdo->query("SELECT title, created_at FROM posts ORDER BY created_at DESC LIMIT 5");
        $recentPosts = $stmt->fetchAll();
        
        foreach ($recentPosts as $post) {
            $activities[] = [
                'icon' => 'fas fa-file-alt',
                'content' => "新帖子: " . (strlen($post['title']) > 20 ? substr($post['title'], 0, 20) . '...' : $post['title']),
                'time' => date('m-d H:i', strtotime($post['created_at']))
            ];
        }
        
        // 按时间排序
        usort($activities, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
        
        return array_slice($activities, 0, 10);
        
    } catch (PDOException $e) {
        error_log("获取最近活动失败: " . $e->getMessage());
        return [];
    }
}
?>