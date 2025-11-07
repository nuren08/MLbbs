<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// 检查登录状态
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => '请先登录']);
}

$currentUser = getCurrentUser();
$user_id = $_POST['user_id'] ?? 0;
$action = $_POST['action'] ?? ''; // block 或 unblock

if (empty($user_id) || !in_array($action, ['block', 'unblock'])) {
    jsonResponse(['success' => false, 'message' => '参数错误']);
}

try {
    // 检查目标用户是否存在
    $targetStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND status = 1");
    $targetStmt->execute([$user_id]);
    $targetUser = $targetStmt->fetch();
    
    if (!$targetUser) {
        jsonResponse(['success' => false, 'message' => '用户不存在或已被禁用']);
    }
    
    // 检查是否已经拉黑
    $checkStmt = $pdo->prepare("SELECT id FROM user_blocks WHERE user_id = ? AND blocked_user_id = ?");
    $checkStmt->execute([$currentUser['id'], $user_id]);
    $isBlocked = $checkStmt->fetch();
    
    if ($action === 'block') {
        if ($isBlocked) {
            jsonResponse(['success' => false, 'message' => '已经拉黑该用户']);
        }
        
        // 添加拉黑
        $insertStmt = $pdo->prepare("INSERT INTO user_blocks (user_id, blocked_user_id, created_at) VALUES (?, ?, NOW())");
        $insertStmt->execute([$currentUser['id'], $user_id]);
        
        // 同时取消关注（如果有关注的话）
        $deleteFollowStmt = $pdo->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?");
        $deleteFollowStmt->execute([$currentUser['id'], $user_id]);
        
        jsonResponse(['success' => true, 'message' => '拉黑成功']);
        
    } elseif ($action === 'unblock') {
        if (!$isBlocked) {
            jsonResponse(['success' => false, 'message' => '未拉黑该用户']);
        }
        
        // 取消拉黑
        $deleteStmt = $pdo->prepare("DELETE FROM user_blocks WHERE user_id = ? AND blocked_user_id = ?");
        $deleteStmt->execute([$currentUser['id'], $user_id]);
        
        jsonResponse(['success' => true, 'message' => '取消拉黑成功']);
    }
    
} catch (PDOException $e) {
    error_log("拉黑操作错误: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => '系统错误，请稍后再试']);
}
?>