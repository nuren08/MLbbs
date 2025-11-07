<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// 检查登录状态
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => '请先登录']);
}

$currentUser = getCurrentUser();
$user_id = $_GET['user_id'] ?? 0;
$last_time = $_GET['last_time'] ?? '';

if (empty($user_id)) {
    jsonResponse(['success' => false, 'message' => '参数错误']);
}

try {
    // 构建查询条件
    $whereClause = "(from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)";
    $params = [$currentUser['id'], $user_id, $user_id, $currentUser['id']];
    
    if (!empty($last_time)) {
        $whereClause .= " AND created_at > ?";
        $params[] = $last_time;
    }
    
    // 获取新消息
    $messagesStmt = $pdo->prepare("
        SELECT m.*, 
               u_from.nickname as from_nickname,
               u_from.avatar as from_avatar
        FROM messages m
        LEFT JOIN users u_from ON m.from_user_id = u_from.id
        WHERE $whereClause
        ORDER BY m.created_at ASC
    ");
    $messagesStmt->execute($params);
    $messages = $messagesStmt->fetchAll();
    
    // 格式化消息数据
    $formattedMessages = [];
    foreach ($messages as $message) {
        $formattedMessages[] = [
            'id' => $message['id'],
            'from_user_id' => $message['from_user_id'],
            'to_user_id' => $message['to_user_id'],
            'content' => $message['content'],
            'time' => date('H:i', strtotime($message['created_at'])),
            'is_read' => $message['is_read'],
            'from_avatar' => $message['from_avatar']
        ];
        
        // 如果是发给当前用户的消息，标记为已读
        if ($message['to_user_id'] == $currentUser['id'] && !$message['is_read']) {
            $updateStmt = $pdo->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ?");
            $updateStmt->execute([$message['id']]);
        }
    }
    
    jsonResponse([
        'success' => true,
        'messages' => $formattedMessages
    ]);
    
} catch (PDOException $e) {
    error_log("获取新消息错误: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => '系统错误，请稍后再试']);
}
?>