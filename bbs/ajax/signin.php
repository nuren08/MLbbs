<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// 检查登录状态
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => '请先登录']);
}

$currentUser = getCurrentUser();

try {
    // 检查今日是否已签到
    $todayStmt = $pdo->prepare("SELECT * FROM sign_ins WHERE user_id = ? AND sign_date = CURDATE()");
    $todayStmt->execute([$currentUser['id']]);
    $todaySignin = $todayStmt->fetch();
    
    if ($todaySignin) {
        jsonResponse(['success' => false, 'message' => '今天已经签到过了']);
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
    
    // 计算连续签到天数
    $yesterdayStmt = $pdo->prepare("SELECT * FROM sign_ins WHERE user_id = ? AND sign_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
    $yesterdayStmt->execute([$currentUser['id']]);
    $yesterdaySignin = $yesterdayStmt->fetch();
    
    $newContinuousDays = $yesterdaySignin ? $yesterdaySignin['continuous_days'] + 1 : 1;
    
    // 插入签到记录
    $insertStmt = $pdo->prepare("
        INSERT INTO sign_ins (user_id, sign_date, continuous_days, total_days, points_earned) 
        VALUES (?, CURDATE(), ?, ?, ?)
    ");
    $insertStmt->execute([$currentUser['id'], $newContinuousDays, $totalDays + 1, $pointsEarned]);
    
    // 更新用户积分和经验值
    $updateStmt = $pdo->prepare("UPDATE users SET points = points + ?, exp = exp + ? WHERE id = ?");
    $updateStmt->execute([$pointsEarned, $pointsEarned, $currentUser['id']]);
    
    // 检查是否触发抽奖
    $triggerLottery = false;
    $lotteryType = '';
    if ($newContinuousDays == 7 || $newContinuousDays == 30 || $newContinuousDays == 365) {
        $triggerLottery = true;
        $lotteryType = $newContinuousDays;
    }
    
    // 提交事务
    $pdo->commit();
    
    // 获取更新后的用户信息
    $userStmt = $pdo->prepare("SELECT points, exp FROM users WHERE id = ?");
    $userStmt->execute([$currentUser['id']]);
    $updatedUser = $userStmt->fetch();
    
    jsonResponse([
        'success' => true,
        'message' => "签到成功！获得{$pointsEarned}积分和{$pointsEarned}经验值",
        'points_earned' => $pointsEarned,
        'continuous_days' => $newContinuousDays,
        'total_days' => $totalDays + 1,
        'current_points' => $updatedUser['points'],
        'current_exp' => $updatedUser['exp'],
        'trigger_lottery' => $triggerLottery,
        'lottery_type' => $lotteryType
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("签到错误: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => '签到失败，请稍后再试']);
}
?>