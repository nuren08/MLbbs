<?php
// 抽奖逻辑处理
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

// 检查参数
$type = $_POST['type'] ?? '';
$user_id = $_POST['user_id'] ?? 0;

if (!in_array($type, ['7', '30', '365']) || empty($user_id)) {
    jsonResponse(['success' => false, 'message' => '参数错误']);
}

try {
    // 开始事务
    $pdo->beginTransaction();
    
    // 检查用户是否存在
    $userStmt = $pdo->prepare("SELECT id, user_id FROM users WHERE user_id = ?");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        throw new Exception('用户不存在');
    }
    
    // 检查是否已经有抽奖记录
    $checkStmt = $pdo->prepare("SELECT id FROM lottery_records WHERE user_id = ? AND lottery_type = ?");
    $checkStmt->execute([$user['id'], $type]);
    $hasRecord = $checkStmt->fetch();
    
    if ($hasRecord) {
        throw new Exception('您已经使用过本次抽奖机会了');
    }
    
    // 根据类型设置奖品和概率
    $prizes = [];
    switch ($type) {
        case '7':
            $prizes = [
                ['name' => '10积分', 'points' => 10, 'probability' => 90],
                ['name' => '30积分', 'points' => 30, 'probability' => 80],
                ['name' => '50积分', 'points' => 50, 'probability' => 50],
                ['name' => '100积分', 'points' => 100, 'probability' => 0],
                ['name' => '500积分', 'points' => 500, 'probability' => 0],
                ['name' => '3650积分', 'points' => 3650, 'probability' => 0]
            ];
            break;
            
        case '30':
            $prizes = [
                ['name' => '10积分', 'points' => 10, 'probability' => 10],
                ['name' => '30积分', 'points' => 30, 'probability' => 20],
                ['name' => '50积分', 'points' => 50, 'probability' => 30],
                ['name' => '100积分', 'points' => 100, 'probability' => 80],
                ['name' => '500积分', 'points' => 500, 'probability' => 5],
                ['name' => '3650积分', 'points' => 3650, 'probability' => 0]
            ];
            break;
            
        case '365':
            $prizes = [
                ['name' => '10积分', 'points' => 10, 'probability' => 0],
                ['name' => '30积分', 'points' => 30, 'probability' => 0],
                ['name' => '50积分', 'points' => 50, 'probability' => 0],
                ['name' => '100积分', 'points' => 100, 'probability' => 0],
                ['name' => '500积分', 'points' => 500, 'probability' => 10],
                ['name' => '3650积分', 'points' => 3650, 'probability' => 90]
            ];
            break;
    }
    
    // 计算总概率（处理概率为0的情况）
    $totalProbability = array_sum(array_column($prizes, 'probability'));
    
    // 生成随机数决定奖品
    $random = mt_rand(1, $totalProbability);
    $currentProbability = 0;
    $selectedPrize = null;
    $selectedIndex = 0;
    
    foreach ($prizes as $index => $prize) {
        $currentProbability += $prize['probability'];
        if ($random <= $currentProbability) {
            $selectedPrize = $prize;
            $selectedIndex = $index;
            break;
        }
    }
    
    if (!$selectedPrize) {
        // 如果没有选中奖品（理论上不会发生），选择第一个
        $selectedPrize = $prizes[0];
        $selectedIndex = 0;
    }
    
    // 记录抽奖结果
    $insertStmt = $pdo->prepare("
        INSERT INTO lottery_records (user_id, lottery_type, prize_points, prize_name) 
        VALUES (?, ?, ?, ?)
    ");
    $insertStmt->execute([$user['id'], $type, $selectedPrize['points'], $selectedPrize['name']]);
    
    // 更新用户积分和经验值
    $updateStmt = $pdo->prepare("UPDATE users SET points = points + ?, exp = exp + ? WHERE id = ?");
    $updateStmt->execute([$selectedPrize['points'], $selectedPrize['points'], $user['id']]);
    
    // 提交事务
    $pdo->commit();
    
    jsonResponse([
        'success' => true,
        'prize_name' => $selectedPrize['name'],
        'prize_points' => $selectedPrize['points'],
        'prize_index' => $selectedIndex
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(['success' => false, 'message' => $e->getMessage()]);
}
?>