<?php
// 数据库配置文件 - 包含在主配置中
require_once __DIR__ . '/../../includes/config.php';

// 管理后台权限检查
function checkAdminAuth() {
    if (!isset($_SESSION['user_id'])) {
        redirect(BASE_PATH . '/login.php');
    }
    $user = getCurrentUser();
    if (!$user || $user['level'] < 8) { // 只有8级用户可访问管理后台
        die('权限不足');
    }
}

// 管理后台专用函数
function getAdminStats() {
    global $pdo;
    $stats = [];
    try {
        // 今日访问量（简化版，实际应使用更复杂的统计）
        $stmt = $pdo->query("SELECT COUNT(*) as today_visits FROM users WHERE DATE(last_login) = CURDATE()");
        $stats['today_visits'] = $stmt->fetch()['today_visits'];
        
        // 总用户数
        $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
        $stats['total_users'] = $stmt->fetch()['total_users'];
        
        // 今日注册数
        $stmt = $pdo->query("SELECT COUNT(*) as today_registers FROM users WHERE DATE(register_time) = CURDATE()");
        $stats['today_registers'] = $stmt->fetch()['today_registers'];
        
        // 待审核帖子
        $stmt = $pdo->query("SELECT COUNT(*) as pending_posts FROM posts WHERE status = 'pending'");
        $stats['pending_posts'] = $stmt->fetch()['pending_posts'];
        
        // 总帖子数
        $stmt = $pdo->query("SELECT COUNT(*) as total_posts FROM posts WHERE status = 'approved'");
        $stats['total_posts'] = $stmt->fetch()['total_posts'];
        
        // 今日发帖量
        $stmt = $pdo->query("SELECT COUNT(*) as today_posts FROM posts WHERE DATE(created_at) = CURDATE()");
        $stats['today_posts'] = $stmt->fetch()['today_posts'];
        
        // 历史发帖量
        $stmt = $pdo->query("SELECT COUNT(*) as total_posts_all FROM posts");
        $stats['total_posts_all'] = $stmt->fetch()['total_posts_all'];
        
        // 树洞待审核申请
        $stmt = $pdo->query("SELECT COUNT(*) as pending_treehole FROM posts WHERE rule_type = 'treehole' AND close_request = 1 AND close_status = 'pending'");
        $stats['pending_treehole'] = $stmt->fetch()['pending_treehole'];
        
    } catch (PDOException $e) {
        error_log("获取管理统计失败: " . $e->getMessage());
    }
    return $stats;
}

// 获取所有用户
function getAllUsers($page = 1, $per_page = 20, $search = '') {
    global $pdo;
    $offset = ($page - 1) * $per_page;
    
    $where = '';
    $params = [];
    
    if (!empty($search)) {
        $where = "WHERE username LIKE ? OR email LIKE ? OR nickname LIKE ?";
        $search_term = "%$search%";
        $params = [$search_term, $search_term, $search_term];
    }
    
    $stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM users $where ORDER BY register_time DESC LIMIT ? OFFSET ?");
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    $total_stmt = $pdo->query("SELECT FOUND_ROWS()");
    $total_users = $total_stmt->fetchColumn();
    
    return [
        'users' => $users,
        'total' => $total_users,
        'pages' => ceil($total_users / $per_page)
    ];
}

// 封禁/解封用户
function toggleUserStatus($user_id, $status) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $user_id]);
    } catch (PDOException $e) {
        error_log("修改用户状态失败: " . $e->getMessage());
        return false;
    }
}

// 重置用户密码
function resetUserPassword($user_id, $new_password) {
    global $pdo;
    try {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashed_password, $user_id]);
    } catch (PDOException $e) {
        error_log("重置用户密码失败: " . $e->getMessage());
        return false;
    }
}

// 调整用户积分
function adjustUserPoints($user_id, $points, $add_exp = false) {
    global $pdo;
    try {
        if ($add_exp) {
            $stmt = $pdo->prepare("UPDATE users SET points = points + ?, exp = exp + ? WHERE id = ?");
            return $stmt->execute([$points, $points, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?");
            return $stmt->execute([$points, $user_id]);
        }
    } catch (PDOException $e) {
        error_log("调整用户积分失败: " . $e->getMessage());
        return false;
    }
}

// 获取系统设置
function getAdminSettings() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM system_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['setting_key']] = $setting['setting_value'];
        }
        return $result;
    } catch (PDOException $e) {
        error_log("获取系统设置失败: " . $e->getMessage());
        return [];
    }
}

// 更新系统设置
function updateAdminSettings($settings) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("REPLACE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("更新系统设置失败: " . $e->getMessage());
        return false;
    }
}

// 获取用户详情
function getUserDetail($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("获取用户详情失败: " . $e->getMessage());
        return null;
    }
}

// 获取用户的帖子
function getUserPosts($user_id, $page = 1, $per_page = 10) {
    global $pdo;
    $offset = ($page - 1) * $per_page;
    
    try {
        $stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS p.*, c.name as category_name 
                              FROM posts p 
                              LEFT JOIN categories c ON p.category_id = c.id 
                              WHERE p.author_id = ? 
                              ORDER BY p.created_at DESC 
                              LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $posts = $stmt->fetchAll();
        
        $total_stmt = $pdo->query("SELECT FOUND_ROWS()");
        $total = $total_stmt->fetchColumn();
        
        return [
            'posts' => $posts,
            'total' => $total,
            'pages' => ceil($total / $per_page)
        ];
    } catch (PDOException $e) {
        error_log("获取用户帖子失败: " . $e->getMessage());
        return ['posts' => [], 'total' => 0, 'pages' => 0];
    }
}

// 获取用户的回复
function getUserReplies($user_id, $page = 1, $per_page = 10) {
    global $pdo;
    $offset = ($page - 1) * $per_page;
    
    try {
        $stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS r.*, p.title as post_title 
                              FROM replies r 
                              LEFT JOIN posts p ON r.post_id = p.id 
                              WHERE r.user_id = ? 
                              ORDER BY r.created_at DESC 
                              LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $replies = $stmt->fetchAll();
        
        $total_stmt = $pdo->query("SELECT FOUND_ROWS()");
        $total = $total_stmt->fetchColumn();
        
        return [
            'replies' => $replies,
            'total' => $total,
            'pages' => ceil($total / $per_page)
        ];
    } catch (PDOException $e) {
        error_log("获取用户回复失败: " . $e->getMessage());
        return ['replies' => [], 'total' => 0, 'pages' => 0];
    }
}

// 获取积分记录
function getUserPointsLog($user_id, $page = 1, $per_page = 10) {
    global $pdo;
    $offset = ($page - 1) * $per_page;
    
    try {
        $stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS * 
                              FROM points_log 
                              WHERE user_id = ? 
                              ORDER BY created_at DESC 
                              LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $logs = $stmt->fetchAll();
        
        $total_stmt = $pdo->query("SELECT FOUND_ROWS()");
        $total = $total_stmt->fetchColumn();
        
        return [
            'logs' => $logs,
            'total' => $total,
            'pages' => ceil($total / $per_page)
        ];
    } catch (PDOException $e) {
        error_log("获取积分记录失败: " . $e->getMessage());
        return ['logs' => [], 'total' => 0, 'pages' => 0];
    }
}
?>
