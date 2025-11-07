<?php
require_once __DIR__ . '/includes/config.php';

// 检查是否有新用户注册数据
if (!isset($_SESSION['new_user_rank']) || !isset($_SESSION['new_user_email'])) {
    redirect(BASE_PATH . '/register.php');
}

$userRank = $_SESSION['new_user_rank'];
$userEmail = $_SESSION['new_user_email'];

// 清除session数据
unset($_SESSION['new_user_rank']);
unset($_SESSION['new_user_email']);

include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-lg border-0">
            <div class="card-body text-center p-5">
                <!-- 成功图标 -->
                <div class="mb-4">
                    <div style="font-size: 4rem; color: #28a745;">
                        <svg width="1em" height="1em" viewBox="0 0 16 16" class="bi bi-check-circle-fill" fill="currentColor">
                            <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
                        </svg>
                    </div>
                </div>

                <!-- 第一行：恭喜注册成功 -->
                <h1 class="rainbow-text mb-4">
                    <span style="color: #28a745; -webkit-text-fill-color: #28a745;">✓</span>
                    恭喜您，注册成功！
                </h1>

                <!-- 第二行：会员排名 -->
                <h2 class="rainbow-text mb-4">
                    恭喜您成为本站第<?php echo $userRank; ?>名会员，请记得常回家看看！
                </h2>

                <!-- 第三行：邮箱提示 -->
                <div class="alert alert-info mb-4">
                    <i class="fas fa-envelope me-2"></i>
                    注册确认邮件已发送至：<strong><?php echo escape($userEmail); ?></strong>
                    <br>请查收邮件完成账号验证（如未收到，请检查垃圾邮件）
                </div>

                <!-- 第四行：温馨提示标题 -->
                <h3 class="rainbow-text mb-3">温馨提示</h3>

                <!-- 第五行：存储空间提示 -->
                <div class="alert alert-warning" style="font-size: 1.2rem; line-height: 1.6;">
                    <p class="mb-0 rainbow-text" style="font-size: 1.1rem;">
                        "因为存储空间有限，私信消息，若对方15天未接收，将从服务器删除，已接收的消息将立即从服务器删除，仅保存在用户设备本地，请知悉！"
                    </p>
                </div>

                <!-- 操作按钮 -->
                <div class="mt-5">
                    <a href="<?php echo BASE_PATH; ?>/login.php" class="btn btn-primary btn-lg me-3">
                        <i class="fas fa-sign-in-alt me-2"></i>立即登录
                    </a>
                    <a href="<?php echo BASE_PATH; ?>/index.php" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-home me-2"></i>返回首页
                    </a>
                </div>

                <!-- 额外提示 -->
                <div class="mt-4 text-muted">
                    <p class="mb-1">💡 <strong>小贴士：</strong></p>
                    <p class="mb-0">• 每日签到可获取积分和经验值奖励</p>
                    <p class="mb-0">• 完善个人资料可以获得更多积分</p>
                    <p class="mb-0">• 参与论坛互动可以快速提升等级</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.rainbow-text {
    background: linear-gradient(45deg, #ff0000, #ff8000, #ffff00, #00ff00, #00ffff, #0000ff, #8000ff, #ff00ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: bold;
}

.card {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    border: none;
    border-radius: 20px;
}

.alert-warning {
    background: rgba(255, 193, 7, 0.1);
    border: 2px solid #ffc107;
    border-radius: 15px;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>