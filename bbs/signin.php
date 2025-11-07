<?php
require_once __DIR__ . '/includes/config.php';

// 检查登录状态
if (!isLoggedIn()) {
    redirect(BASE_PATH . '/login.php');
}

$currentUser = getCurrentUser();
$error = '';
$success = '';

// 获取用户签到信息
try {
    // 获取今日签到状态
    $todayStmt = $pdo->prepare("SELECT * FROM sign_ins WHERE user_id = ? AND sign_date = CURDATE()");
    $todayStmt->execute([$currentUser['id']]);
    $todaySignin = $todayStmt->fetch();
    
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
    
    // 获取总签到天数
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM sign_ins WHERE user_id = ?");
    $totalStmt->execute([$currentUser['id']]);
    $totalDays = $totalStmt->fetchColumn();
    
    // 获取最近7天签到记录
    $weekStmt = $pdo->prepare("
        SELECT sign_date, continuous_days, points_earned 
        FROM sign_ins 
        WHERE user_id = ? AND sign_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        ORDER BY sign_date
    ");
    $weekStmt->execute([$currentUser['id']]);
    $weekSignins = $weekStmt->fetchAll();
    
    // 构建7天签到状态数组
    $weekStatus = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $signed = false;
        $dayPoints = 0;
        
        foreach ($weekSignins as $sign) {
            if ($sign['sign_date'] == $date) {
                $signed = true;
                $dayPoints = $sign['points_earned'];
                break;
            }
        }
        
        $weekStatus[] = [
            'date' => $date,
            'day' => date('d', strtotime($date)),
            'weekday' => date('D', strtotime($date)),
            'signed' => $signed,
            'points' => $dayPoints
        ];
    }
    
    // 检查是否有补签机会
    $canMakeup = false;
    $makeupCost = 0;
    if (!$todaySignin) {
        // 检查昨天是否签到
        $yesterdayStmt = $pdo->prepare("SELECT * FROM sign_ins WHERE user_id = ? AND sign_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
        $yesterdayStmt->execute([$currentUser['id']]);
        $yesterdaySignin = $yesterdayStmt->fetch();
        
        if (!$yesterdaySignin) {
            $canMakeup = true;
            // 计算补签所需积分
            if ($continuousDays < 7) {
                $makeupCost = 5;
            } elseif ($continuousDays < 30) {
                $makeupCost = 18;
            } else {
                $makeupCost = 38;
            }
        }
    }
    
} catch (PDOException $e) {
    error_log("获取签到信息错误: " . $e->getMessage());
    $error = '系统错误，请稍后再试';
}

// 处理签到请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signin'])) {
    if ($todaySignin) {
        $error = '今天已经签到过了';
    } else {
        try {
            // 开始事务
            $pdo->beginTransaction();
            
            // 获取用户等级奖励
            $userLevel = getUserLevel($currentUser['exp']);
            $rewards = getLevelRewards($userLevel);
            $pointsEarned = $rewards['sign_in'];
            
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
            
            $success = "签到成功！获得{$pointsEarned}积分和{$pointsEarned}经验值";
            
            // 刷新页面数据
            header("Location: " . BASE_PATH . "/signin.php?success=1&lottery=" . ($triggerLottery ? $lotteryType : ''));
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("签到错误: " . $e->getMessage());
            $error = '签到失败，请稍后再试';
        }
    }
}

// 处理补签请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['makeup_signin'])) {
    if (!$canMakeup) {
        $error = '当前无法补签';
    } elseif ($currentUser['points'] < $makeupCost) {
        $error = "积分不足，补签需要{$makeupCost}积分";
    } else {
        try {
            // 开始事务
            $pdo->beginTransaction();
            
            // 获取用户等级奖励
            $userLevel = getUserLevel($currentUser['exp']);
            $rewards = getLevelRewards($userLevel);
            $pointsEarned = $rewards['sign_in'];
            
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
            
            $success = "补签成功！获得{$pointsEarned}积分和{$pointsEarned}经验值，扣除补签费用{$makeupCost}积分，净获得{$netPoints}积分";
            
            // 刷新页面数据
            header("Location: " . BASE_PATH . "/signin.php?success=1");
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("补签错误: " . $e->getMessage());
            $error = '补签失败，请稍后再试';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-lg">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-calendar-check me-2"></i>每日签到</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success || isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $success ?: '操作成功！'; ?>
                </div>
                <?php endif; ?>

                <!-- 签到状态概览 -->
                <div class="row text-center mb-4">
                    <div class="col-md-4">
                        <div class="border rounded p-3 bg-light">
                            <div class="h4 text-primary mb-1"><?php echo $continuousDays; ?></div>
                            <div class="text-muted">连续签到</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 bg-light">
                            <div class="h4 text-success mb-1"><?php echo $totalDays; ?></div>
                            <div class="text-muted">总签到天数</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-3 bg-light">
                            <div class="h4 text-warning mb-1">LV<?php echo getUserLevel($currentUser['exp']); ?></div>
                            <div class="text-muted">当前等级</div>
                        </div>
                    </div>
                </div>

                <!-- 7天签到进度条 -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>7天签到进度</h5>
                    </div>
                    <div class="card-body">
                        <div class="progress signin-progress mb-3" style="height: 30px;">
                            <?php
                            $signedCount = 0;
                            foreach ($weekStatus as $day) {
                                if ($day['signed']) $signedCount++;
                            }
                            $progress = ($signedCount / 7) * 100;
                            ?>
                            <div class="progress-bar" style="width: <?php echo $progress; ?>%; background: linear-gradient(45deg, #ff6b6b, #ffa500);">
                                <span class="fw-bold"><?php echo round($progress); ?>%</span>
                            </div>
                        </div>
                        
                        <div class="row text-center">
                            <?php foreach ($weekStatus as $day): ?>
                            <div class="col">
                                <div class="day-item <?php echo $day['signed'] ? 'signed' : ''; ?>">
                                    <div class="day-name"><?php echo $day['weekday']; ?></div>
                                    <div class="day-number <?php echo $day['signed'] ? 'signed' : ''; ?>">
                                        <?php echo $day['day']; ?>
                                    </div>
                                    <?php if ($day['signed']): ?>
                                    <div class="day-points text-success small">+<?php echo $day['points']; ?></div>
                                    <?php else: ?>
                                    <div class="day-points text-muted small">+?</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- 签到操作区域 -->
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <?php if ($todaySignin): ?>
                        <div class="py-4">
                            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                            <h4 class="text-success">今日已签到</h4>
                            <p class="text-muted">您今天已经获得了 <?php echo $todaySignin['points_earned']; ?> 积分和经验值</p>
                        </div>
                        <?php else: ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="signin" value="1">
                            <button type="submit" class="btn btn-primary btn-lg px-5 py-3">
                                <i class="fas fa-calendar-check me-2"></i>
                                <span class="h4 mb-0">立即签到</span>
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <?php if ($canMakeup && !$todaySignin): ?>
                        <div class="mt-3">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="makeup_signin" value="1">
                                <button type="submit" class="btn btn-outline-warning">
                                    <i class="fas fa-history me-1"></i>补签昨日（消耗<?php echo $makeupCost; ?>积分）
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 签到规则说明 -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>签到规则</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>等级奖励</h6>
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>等级</th>
                                            <th>签到奖励</th>
                                            <th>发帖奖励</th>
                                            <th>回帖奖励</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <?php $rewards = getLevelRewards($i); ?>
                                        <tr>
                                            <td>LV<?php echo $i; ?></td>
                                            <td><?php echo $rewards['sign_in']; ?></td>
                                            <td><?php echo $rewards['post']; ?></td>
                                            <td><?php echo $rewards['reply']; ?></td>
                                        </tr>
                                        <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>特殊奖励</h6>
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="fas fa-gift text-success me-2"></i>
                                        连续签到7天：抽奖机会（10-50积分）
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-gift text-warning me-2"></i>
                                        连续签到30天：抽奖机会（10-500积分）
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-gift text-danger me-2"></i>
                                        连续签到365天：抽奖机会（500-3650积分）
                                    </li>
                                </ul>
                                
                                <h6 class="mt-3">补签规则</h6>
                                <ul class="list-unstyled">
                                    <li class="mb-1">
                                        <i class="fas fa-coins text-warning me-2"></i>
                                        小于7天：扣除5积分
                                    </li>
                                    <li class="mb-1">
                                        <i class="fas fa-coins text-warning me-2"></i>
                                        7-29天：扣除18积分
                                    </li>
                                    <li class="mb-1">
                                        <i class="fas fa-coins text-warning me-2"></i>
                                        30天以上：扣除38积分
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>提示：</strong> 签到以7天为一个小周期，第8天为新的一个周期的开始，同时也算作连续签到总天数的第8天！
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 抽奖弹窗 -->
<?php if (isset($_GET['lottery']) && $_GET['lottery']): ?>
<div class="modal fade show" id="lotteryModal" tabindex="-1" style="display: block; padding-right: 15px;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="fas fa-gift me-2"></i>
                    恭喜您！连续签到<?php echo $_GET['lottery']; ?>天奖励
                </h5>
                <button type="button" class="btn-close" onclick="closeLotteryModal()"></button>
            </div>
            <div class="modal-body text-center">
                <div class="py-4">
                    <i class="fas fa-trophy fa-4x text-warning mb-3"></i>
                    <h4 class="text-warning mb-3">获得抽奖机会！</h4>
                    <p class="lead">您已连续签到 <?php echo $_GET['lottery']; ?> 天，获得一次抽奖机会</p>
                    
                    <div class="mt-4">
                        <a href="/lottery/?type=<?php echo $_GET['lottery']; ?>&user_id=<?php echo $currentUser['user_id']; ?>" 
                           class="btn btn-warning btn-lg px-5">
                            <i class="fas fa-redo me-2"></i>前往抽奖
                        </a>
                        <button type="button" class="btn btn-outline-secondary btn-lg ms-2" onclick="closeLotteryModal()">
                            稍后再说
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<style>
.signin-progress .progress-bar {
    border-radius: 15px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
}

.day-item {
    padding: 10px 5px;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.day-item.signed {
    background: rgba(255, 107, 107, 0.1);
}

.day-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 5px auto;
    font-weight: bold;
    background: #e9ecef;
    color: #6c757d;
}

.day-number.signed {
    background: linear-gradient(45deg, #ff6b6b, #ffa500);
    color: white;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
}

.day-name {
    font-size: 0.875rem;
    color: #6c757d;
    font-weight: 500;
}

.day-points {
    font-size: 0.75rem;
    margin-top: 5px;
}

.btn-primary {
    background: linear-gradient(45deg, #ff6b6b, #ffa500);
    border: none;
    border-radius: 25px;
    padding: 15px 40px;
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
}

.table th {
    border-top: none;
    font-weight: 600;
}
</style>

<script>
function closeLotteryModal() {
    const modal = document.getElementById('lotteryModal');
    const backdrop = document.querySelector('.modal-backdrop');
    
    if (modal) {
        modal.style.display = 'none';
    }
    if (backdrop) {
        backdrop.style.display = 'none';
    }
    
    // 移除URL中的lottery参数
    const url = new URL(window.location);
    url.searchParams.delete('lottery');
    window.history.replaceState({}, '', url);
}

// 如果抽奖弹窗显示，点击背景也关闭
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-backdrop')) {
        closeLotteryModal();
    }
});

// 签到按钮动画
document.addEventListener('DOMContentLoaded', function() {
    const signinBtn = document.querySelector('button[type="submit"]');
    if (signinBtn && !<?php echo $todaySignin ? 'true' : 'false'; ?>) {
        signinBtn.addEventListener('mouseover', function() {
            this.style.transform = 'translateY(-2px)';
        });
        signinBtn.addEventListener('mouseout', function() {
            this.style.transform = 'translateY(0)';
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>