<?php
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json');

// 检查登录状态
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => '请先登录']);
}

$currentUser = getCurrentUser();
$to_user_id = $_POST['to_user_id'] ?? 0;
$message = trim($_POST['message'] ?? '');

if (empty($to_user_id) || empty($message)) {
    jsonResponse(['success' => false, 'message' => '参数错误']);
}

try {
    // 检查目标用户是否存在
    $targetStmt = $pdo->prepare("SELECT id, allow_follow FROM users WHERE id = ? AND status = 1");
    $targetStmt->execute([$to_user_id]);
    $targetUser = $targetStmt->fetch();
    
    if (!$targetUser) {
        jsonResponse(['success' => false, 'message' => '用户不存在或已被禁用']);
    }
    
    // 检查是否被拉黑
    $blockStmt = $pdo->prepare("SELECT id FROM user_blocks WHERE user_id = ? AND blocked_user_id = ?");
    $blockStmt->execute([$targetUser['id'], $currentUser['id']]);
    $isBlocked = $blockStmt->fetch();
    
    if ($isBlocked) {
        jsonResponse(['success' => false, 'message' => '您已被该用户拉黑，无法发送消息']);
    }
    
    // 检查是否关注（如果需要关注才能发私信）
    $followStmt = $pdo->prepare("SELECT id FROM user_follows WHERE follower_id = ? AND following_id = ?");
    $followStmt->execute([$currentUser['id'], $to_user_id]);
    $isFollowing = $followStmt->fetch();
    
    if (!$isFollowing) {
        jsonResponse(['success' => false, 'message' => '请先关注该用户再发送私信']);
    }
    
    // 插入消息
    $insertStmt = $pdo->prepare("
        INSERT INTO messages (from_user_id, to_user_id, content, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $insertStmt->execute([$currentUser['id'], $to_user_id, $message]);
    
    $messageId = $pdo->lastInsertId();
    
    // 检查是否是第一次对话
    $checkConvStmt = $pdo->prepare("
        SELECT COUNT(*) as message_count 
        FROM messages 
        WHERE (from_user_id = ? AND to_user_id = ?) 
           OR (from_user_id = ? AND to_user_id = ?)
    ");
    $checkConvStmt->execute([$currentUser['id'], $to_user_id, $to_user_id, $currentUser['id']]);
    $messageCount = $checkConvStmt->fetch()['message_count'];
    
    $isNewConversation = $messageCount === 1;
    
    // 返回消息数据
    $messageData = [
        'id' => $messageId,
        'from_user_id' => $currentUser['id'],
        'to_user_id' => $to_user_id,
        'content' => $message,
        'time' => date('H:i'),
        'is_read' => 0
    ];
    
    jsonResponse([
        'success' => true,
        'message' => '发送成功',
        'is_new_conversation' => $isNewConversation,
        'message' => $messageData
    ]);
    
} catch (PDOException $e) {
    error_log("发送消息错误: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => '系统错误，请稍后再试']);
}
?>