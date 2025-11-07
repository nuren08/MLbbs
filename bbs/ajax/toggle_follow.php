<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// 检查登录状态
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => '请先登录']);
}

$currentUser = getCurrentUser();
$user_id = $_POST['user_id'] ?? 0;
$action = $_POST['action'] ?? ''; // follow 或 unfollow

if (empty($user_id) || !in_array($action, ['follow', 'unfollow'])) {
    jsonResponse(['success' => false, 'message' => '参数错误']);
}

try {
    // 检查目标用户是否存在且允许被关注
    $targetStmt = $pdo->prepare("SELECT id, allow_follow FROM users WHERE id = ? AND status = 1");
    $targetStmt->execute([$user_id]);
    $targetUser = $targetStmt->fetch();
    
    if (!$targetUser) {
        jsonResponse(['success' => false, 'message' => '用户不存在或已被禁用']);
    }
    
    if ($action === 'follow' && !$targetUser['allow_follow']) {
        jsonResponse(['success' => false, 'message' => '该用户禁止被关注']);
    }
    
    // 检查是否已经关注
    $checkStmt = $pdo->prepare("SELECT id FROM user_follows WHERE follower_id = ? AND following_id = ?");
    $checkStmt->execute([$currentUser['id'], $user_id]);
    $isFollowing = $checkStmt->fetch();
    
    if ($action === 'follow') {
        if ($isFollowing) {
            jsonResponse(['success' => false, 'message' => '已经关注该用户']);
        }
        
        // 添加关注
        $insertStmt = $pdo->prepare("INSERT INTO user_follows (follower_id, following_id, created_at) VALUES (?, ?, NOW())");
        $insertStmt->execute([$currentUser['id'], $user_id]);
        
        // 发送关注通知（这里可以扩展为发送系统消息）
        
        jsonResponse(['success' => true, 'message' => '关注成功']);
        
    } elseif ($action === 'unfollow') {
        if (!$isFollowing) {
            jsonResponse(['success' => false, 'message' => '未关注该用户']);
        }
        
        // 取消关注
        $deleteStmt = $pdo->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?");
        $deleteStmt->execute([$currentUser['id'], $user_id]);
        
        jsonResponse(['success' => true, 'message' => '取消关注成功']);
    }
    
} catch (PDOException $e) {
    error_log("关注操作错误: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => '系统错误，请稍后再试']);
}
?>