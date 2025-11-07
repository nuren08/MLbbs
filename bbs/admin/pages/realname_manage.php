<?php
// 获取实名认证配置
$realname_required = getSystemSetting('realname_required', '0');
$aliyun_appcode = getSystemSetting('aliyun_appcode', '');
$aliyun_appkey = getSystemSetting('aliyun_appkey', '');
$aliyun_appsecret = getSystemSetting('aliyun_appsecret', '');

// 处理实名认证开关
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_realname'])) {
    $new_value = $realname_required == '1' ? '0' : '1';
    if (setSystemSetting('realname_required', $new_value)) {
        echo '<script>showMessage("实名认证设置已更新"); setTimeout(() => window.location.reload(), 1000);</script>';
    }
}

// 处理阿里云配置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_aliyun_config'])) {
    $updates = [
        'aliyun_appcode' => escape($_POST['aliyun_appcode']),
        'aliyun_appkey' => escape($_POST['aliyun_appkey']),
        'aliyun_appsecret' => escape($_POST['aliyun_appsecret'])
    ];
    
    if (updateAdminSettings($updates)) {
        echo '<script>showMessage("阿里云配置更新成功");</script>';
    } else {
        echo '<script>showMessage("更新失败", "error");</script>';
    }
}

// 获取实名认证统计
$realname_stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'verified_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE realname_verified = 1")->fetchColumn(),
    'pending_verification' => $pdo->query("SELECT COUNT(*) FROM users WHERE realname_verified = 0 AND realname_surname IS NOT NULL")->fetchColumn()
];

// 获取最近实名认证的用户
$recent_verified = $pdo->query("SELECT username, nickname, realname_surname, realname_idcard, register_time 
                               FROM users 
                               WHERE realname_verified = 1 
                               ORDER BY register_time DESC 
                               LIMIT 10")->fetchAll();
?>

<div class="page-header">
    <h2>实名认证管理</h2>
    <div class="header-actions">
        <form method="POST" class="toggle-form">
            <button type="submit" name="toggle_realname" 
                    class="btn <?= $realname_required == '1' ? 'btn-success' : 'btn-secondary' ?>">
                <i class="fas fa-id-card"></i>
                <?= $realname_required == '1' ? '关闭实名认证' : '开启实名认证' ?>
            </button>
        </form>
    </div>
</div>

<!-- 实名认证状态 -->
<div class="card">
    <h3>实名认证状态</h3>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3><?= $realname_stats['total_users'] ?></h3>
                <p>总用户数</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?= $realname_stats['verified_users'] ?></h3>
                <p>已实名用户</p>
                <small><?= $realname_stats['total_users'] > 0 ? round(($realname_stats['verified_users'] / $realname_stats['total_users']) * 100, 1) : 0 ?>%</small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?= $realname_stats['pending_verification'] ?></h3>
                <p>待认证用户</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon <?= $realname_required == '1' ? 'success' : 'secondary' ?>">
                <i class="fas fa-cog"></i>
            </div>
            <div class="stat-info">
                <h3><?= $realname_required == '1' ? '开启' : '关闭' ?></h3>
                <p>认证状态</p>
            </div>
        </div>
    </div>
</div>

<!-- 阿里云实名认证配置 -->
<div class="card">
    <h3>阿里云实名认证配置</h3>
    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>AppCode</label>
                <input type="text" name="aliyun_appcode" 
                       value="<?= $aliyun_appcode ?>" 
                       placeholder="阿里云API的AppCode">
                <small class="form-text">在阿里云API市场购买身份证二要素验证服务后获取</small>
            </div>
            
            <div class="form-group">
                <label>AppKey</label>
                <input type="text" name="aliyun_appkey" 
                       value="<?= $aliyun_appkey ?>" 
                       placeholder="阿里云API的AppKey">
            </div>
            
            <div class="form-group">
                <label>AppSecret</label>
                <input type="password" name="aliyun_appsecret" 
                       value="<?= $aliyun_appsecret ?>" 
                       placeholder="阿里云API的AppSecret">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="update_aliyun_config" class="btn btn-primary">
                <i class="fas fa-save"></i> 保存配置
            </button>
            <a href="https://market.aliyun.com/products/57000002/cmapi031827.html" 
               target="_blank" class="btn btn-info">
                <i class="fas fa-external-link-alt"></i> 购买API服务
            </a>
        </div>
    </form>
</div>

<!-- 配置说明 -->
<div class="card">
    <h3>配置说明</h3>
    <div class="config-guide">
        <h4><i class="fas fa-info-circle"></i> 阿里云实名认证接口配置步骤：</h4>
        <ol>
            <li>访问 <a href="https://market.aliyun.com/products/57000002/cmapi031827.html" target="_blank">阿里云API市场</a></li>
            <li>搜索"身份证二要素验证"服务</li>
            <li>选择合适的服务商并购买套餐</li>
            <li>在控制台获取 AppCode、AppKey 和 AppSecret</li>
            <li>将获取的凭证填写到上面的配置中</li>
            <li>保存配置后开启实名认证功能</li>
        </ol>
        
        <div class="alert alert-info">
            <h5><i class="fas fa-shield-alt"></i> 隐私保护说明：</h5>
            <p>本站严格遵守隐私保护原则，不会存储用户的完整身份证信息。实名认证成功后，仅保存从阿里云返回的脱敏信息：</p>
            <ul>
                <li>姓名：仅显示姓氏（如：张*）</li>
                <li>身份证号：仅显示首尾数字（如：1**************1）</li>
                <li>完整信息由阿里云处理，本站不存储</li>
            </ul>
        </div>
    </div>
</div>

<!-- 最近实名认证用户 -->
<div class="card">
    <h3>最近实名认证用户</h3>
    <?php if (empty($recent_verified)): ?>
        <div class="no-data">暂无实名认证用户</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>用户名</th>
                        <th>昵称</th>
                        <th>脱敏姓名</th>
                        <th>脱敏身份证</th>
                        <th>注册时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_verified as $user): ?>
                        <tr>
                            <td><?= $user['username'] ?></td>
                            <td><?= $user['nickname'] ?></td>
                            <td>
                                <?php if ($user['realname_surname']): ?>
                                    <?= $user['realname_surname'] ?>**
                                <?php else: ?>
                                    <span class="text-muted">未记录</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['realname_idcard']): ?>
                                    <?= substr($user['realname_idcard'], 0, 1) . '****************' . substr($user['realname_idcard'], -1) ?>
                                <?php else: ?>
                                    <span class="text-muted">未记录</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('Y-m-d H:i', strtotime($user['register_time'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- 实名认证测试 -->
<div class="card">
    <h3>接口测试</h3>
    <form id="testRealnameForm">
        <div class="form-grid">
            <div class="form-group">
                <label>测试姓名</label>
                <input type="text" name="test_name" placeholder="请输入测试姓名">
            </div>
            <div class="form-group">
                <label>测试身份证号</label>
                <input type="text" name="test_idcard" placeholder="请输入测试身份证号">
            </div>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-primary" onclick="testRealnameAPI()">
                <i class="fas fa-vial"></i> 测试接口
            </button>
        </div>
    </form>
    <div id="testResult" style="display: none; margin-top: 15px; padding: 15px; border-radius: 5px;"></div>
</div>

<script>
function testRealnameAPI() {
    const form = document.getElementById('testRealnameForm');
    const resultDiv = document.getElementById('testResult');
    const formData = new FormData(form);
    
    // 显示加载中
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> 测试中...</div>';
    
    // 发送测试请求
    fetch('<?= BASE_PATH ?>/ajax/test_realname_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultDiv.innerHTML = `<div class="alert alert-success">
                <i class="fas fa-check-circle"></i> 接口测试成功！<br>
                <small>响应信息：${data.message}</small>
            </div>`;
        } else {
            resultDiv.innerHTML = `<div class="alert alert-danger">
                <i class="fas fa-times-circle"></i> 接口测试失败！<br>
                <small>错误信息：${data.message}</small>
            </div>`;
        }
    })
    .catch(error => {
        resultDiv.innerHTML = `<div class="alert alert-danger">
            <i class="fas fa-times-circle"></i> 网络错误！<br>
            <small>${error}</small>
        </div>`;
    });
}
</script>

<style>
.config-guide ol {
    margin-bottom: 20px;
}

.config-guide li {
    margin-bottom: 8px;
}

.alert {
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 0;
}

.alert-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

.alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.alert-danger {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.toggle-form {
    display: inline;
}
</style>