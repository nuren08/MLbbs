<?php
require_once __DIR__ . '/../includes/config.php';

// 检查登录状态
if (!isLoggedIn()) {
    echo jsonResponse(['success' => false, 'message' => '请先登录']);
}

$currentUser = getCurrentUser();
$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

if ($post_id <= 0) {
    echo jsonResponse(['success' => false, 'message' => '帖子ID无效']);
}

// 取消收藏
$result = removeFromFavorites($currentUser['id'], $post_id);

if ($result) {
    echo jsonResponse(['success' => true, 'message' => '取消收藏成功']);
} else {
    echo jsonResponse(['success' => false, 'message' => '取消收藏失败']);
}
?>