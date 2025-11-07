<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// 检查登录状态
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => '请先登录']);
}

$currentUser = getCurrentUser();

try {
    // 检查是否可以补签
    $todayStmt = $pdo->prepare("SELECT * FROM sign_ins WHERE user_id = ? AND sign_date = CURDATE()");
    $todayStmt->execute([$currentUser['id']]);
    $todaySignin = $todayStmt->fetch();
    
    if ($todaySignin) {
        jsonResponse(['success' => false, 'message' => '今天已经签到过了，无法补签']);
    }
    
    // 检查昨天是否已签到
    $yesterdayStmt = $pdo->prepare("SELECT * FROM sign_ins WHERE user_id = ? AND sign_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
    $yesterdayStmt->execute([$currentUser['id']]);
    $yesterdaySignin = $yesterdayStmt->fetch();
    
    if ($yesterdaySignin) {
        jsonResponse(['success' => false, 'message' => '昨天已经签到过了，无需补签']);
    }
    
    // 获取连续签到天数
    $continuousStmt = $pdo->prepare("
        SELECT continuous_days 
        FROM sign_ins 
        WHERE user_id = ? 
        ORDER BY sign_date DESC 
        LIMIT 1
    ");
    $continuousStmt->execute([$currentUser['id']]);
    $continuousDays = $continuousStmt->fetchColumn() ?: 0;
    
    // 计算补签所需积分
    if ($continuousDays < 7) {
        $makeupCost = 5;
    } elseif ($continuousDays < 30) {
        $makeupCost = 18;
    } else {
        $makeupCost = 38;
    }
    
    // 检查积分是否足够
    if ($currentUser['points'] < $makeupCost) {
        jsonResponse(['success' => false, 'message' => "积分不足，补签需要{$makeupCost}积分"]);
    }
    
    // 开始事务
    $pdo->beginTransaction();
    
    // 获取用户等级奖励
    $userLevel = getUserLevel($currentUser['exp']);
    $rewards = getLevelRewards($userLevel);
    $pointsEarned = $rewards['sign_in'];
    
    // 获取总签到天数
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM sign_ins WHERE user_id = ?");
    $totalStmt->execute([$currentUser['id']]);
    $totalDays = $totalStmt->fetchColumn();
    
    // 计算连续签到天数（补签昨天的）
    $twoDaysAgoStmt = $pdo->prepare("SELECT * FROM sign_ins WHERE user_id = ? AND sign_date = DATE_SUB(CURDATE(), INTERVAL 2 DAY)");
    $twoDaysAgoStmt->execute([$currentUser['id']]);
    $twoDaysAgoSignin = $twoDaysAgoStmt->fetch();
    
    $newContinuousDays = $twoDaysAgoSignin ? $twoDaysAgoSignin['continuous_days'] + 1 : 1;
    
    // 插入补签记录（昨天的日期）
    $insertStmt = $pdo->prepare("
        INSERT INTO sign_ins (user_id, sign_date, continuous_days, total_days, points_earned, is_makeup) 
        VALUES (?, DATE_SUB(CURDATE(), INTERVAL 1 DAY), ?, ?, ?, 1)
    ");
    $insertStmt->execute([$currentUser['id'], $newContinuousDays, $totalDays + 1, $pointsEarned]);
    
    // 更新用户积分和经验值（扣除补签费用，加上签到奖励）
    $netPoints = $pointsEarned - $makeupCost;
    $updateStmt = $pdo->prepare("UPDATE users SET points = points + ?, exp = exp + ? WHERE id = ?");
    $updateStmt->execute([$netPoints, $pointsEarned, $currentUser['id']]);
    
    // 提交事务
    $pdo->commit();
    
    // 获取更新后的用户信息
    $userStmt = $pdo->prepare("SELECT points, exp FROM users WHERE id = ?");
    $userStmt->execute([$currentUser['id']]);
    $updatedUser = $userStmt->fetch();
    
    jsonResponse([
        'success' => true,
        'message' => "补签成功！获得{$pointsEarned}积分和{$pointsEarned}经验值，扣除补签费用{$makeupCost}积分，净获得{$netPoints}积分",
        'points_earned' => $pointsEarned,
        'makeup_cost' => $makeupCost,
        'net_points' => $netPoints,
        'continuous_days' => $newContinuousDays,
        'total_days' => $totalDays + 1,
        'current_points' => $updatedUser['points'],
        'current_exp' => $updatedUser['exp']
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("补签错误: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => '补签失败，请稍后再试']);
}
?>