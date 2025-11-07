<?php
// 定义默认等级配置
$default_levels = [
    1 => ['exp' => 0, 'sign_in' => 3, 'post' => 5, 'reply' => 1, 'post_limit' => 5, 'reply_limit' => 20],
    2 => ['exp' => 1000, 'sign_in' => 4, 'post' => 6, 'reply' => 1, 'post_limit' => 5, 'reply_limit' => 30],
    3 => ['exp' => 10000, 'sign_in' => 5, 'post' => 7, 'reply' => 1, 'post_limit' => 6, 'reply_limit' => 50],
    4 => ['exp' => 50000, 'sign_in' => 6, 'post' => 8, 'reply' => 1, 'post_limit' => 6, 'reply_limit' => 80],
    5 => ['exp' => 150000, 'sign_in' => 7, 'post' => 9, 'reply' => 1, 'post_limit' => 7, 'reply_limit' => 1000],
    6 => ['exp' => 500000, 'sign_in' => 8, 'post' => 10, 'reply' => 2, 'post_limit' => 8, 'reply_limit' => 100],
    7 => ['exp' => 1000000, 'sign_in' => 9, 'post' => 11, 'reply' => 2, 'post_limit' => 9, 'reply_limit' => 150],
    8 => ['exp' => 5000000, 'sign_in' => 10, 'post' => 12, 'reply' => 3, 'post_limit' => 10, 'reply_limit' => 188]
];

// 从数据库获取等级配置
$level_config = [];
for ($i = 1; $i <= 8; $i++) {
    $level_config[$i] = [
        'exp' => getSystemSetting("level_{$i}_exp", $default_levels[$i]['exp']),
        'sign_in' => getSystemSetting("level_{$i}_sign_in", $default_levels[$i]['sign_in']),
        'post' => getSystemSetting("level_{$i}_post", $default_levels[$i]['post']),
        'reply' => getSystemSetting("level_{$i}_reply", $default_levels[$i]['reply']),
        'post_limit' => getSystemSetting("level_{$i}_post_limit", $default_levels[$i]['post_limit']),
        'reply_limit' => getSystemSetting("level_{$i}_reply_limit", $default_levels[$i]['reply_limit'])
    ];
}

// 处理等级配置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_levels'])) {
    $updates = [];
    
    for ($i = 1; $i <= 8; $i++) {
        $updates["level_{$i}_exp"] = intval($_POST["level_{$i}_exp"]);
        $updates["level_{$i}_sign_in"] = intval($_POST["level_{$i}_sign_in"]);
        $updates["level_{$i}_post"] = intval($_POST["level_{$i}_post"]);
        $updates["level_{$i}_reply"] = intval($_POST["level_{$i}_reply"]);
        $updates["level_{$i}_post_limit"] = intval($_POST["level_{$i}_post_limit"]);
        $updates["level_{$i}_reply_limit"] = intval($_POST["level_{$i}_reply_limit"]);
    }
    
    if (updateAdminSettings($updates)) {
        echo '<script>showMessage("等级配置更新成功"); setTimeout(() => window.location.reload(), 1000);</script>';
    } else {
        echo '<script>showMessage("更新失败", "error");</script>';
    }
}

// 处理添加新等级
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_level'])) {
    $level_number = intval($_POST['level_number']);
    $exp = intval($_POST['exp']);
    $sign_in = intval($_POST['sign_in']);
    $post = intval($_POST['post']);
    $reply = intval($_POST['reply']);
    $post_limit = intval($_POST['post_limit']);
    $reply_limit = intval($_POST['reply_limit']);
    
    $updates = [
        "level_{$level_number}_exp" => $exp,
        "level_{$level_number}_sign_in" => $sign_in,
        "level_{$level_number}_post" => $post,
        "level_{$level_number}_reply" => $reply,
        "level_{$level_number}_post_limit" => $post_limit,
        "level_{$level_number}_reply_limit" => $reply_limit
    ];
    
    if (updateAdminSettings($updates)) {
        echo '<script>showMessage("等级添加成功"); setTimeout(() => window.location.reload(), 1000);</script>';
    } else {
        echo '<script>showMessage("添加失败", "error");</script>';
    }
}

// 获取当前最大等级
$max_level = 8;
for ($i = 9; $i <= 20; $i++) {
    if (getSystemSetting("level_{$i}_exp", '') !== '') {
        $max_level = $i;
    } else {
        break;
    }
}
?>

<div class="page-header">
    <h2>等级管理</h2>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="toggleAddLevelForm()">
            <i class="fas fa-plus"></i> 添加等级
        </button>
        <button class="btn btn-secondary" onclick="resetToDefault()">
            <i class="fas fa-undo"></i> 恢复默认
        </button>
    </div>
</div>

<!-- 添加等级表单 -->
<div id="addLevelForm" class="form-card" style="display: none;">
    <h3>添加新等级</h3>
    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>等级编号 *</label>
                <input type="number" name="level_number" min="9" max="20" value="<?= $max_level + 1 ?>" required>
                <small class="form-text">从9开始添加新等级</small>
            </div>
            
            <div class="form-group">
                <label>所需经验值 *</label>
                <input type="number" name="exp" min="0" required>
            </div>
            
            <div class="form-group">
                <label>签到奖励</label>
                <input type="number" name="sign_in" min="0" value="0">
            </div>
            
            <div class="form-group">
                <label>发帖奖励</label>
                <input type="number" name="post" min="0" value="0">
            </div>
            
            <div class="form-group">
                <label>回帖奖励</label>
                <input type="number" name="reply" min="0" value="0">
            </div>
            
            <div class="form-group">
                <label>每日发帖上限</label>
                <input type="number" name="post_limit" min="0" value="0">
            </div>
            
            <div class="form-group">
                <label>每日回帖上限</label>
                <input type="number" name="reply_limit" min="0" value="0">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="toggleAddLevelForm()">取消</button>
            <button type="submit" name="add_level" class="btn btn-primary">添加等级</button>
        </div>
    </form>
</div>

<!-- 等级配置表格 -->
<div class="card">
    <h3>等级配置</h3>
    <form method="POST" id="levelConfigForm">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>等级</th>
                        <th>所需经验</th>
                        <th>签到奖励</th>
                        <th>发帖奖励</th>
                        <th>回帖奖励</th>
                        <th>发帖上限</th>
                        <th>回帖上限</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 1; $i <= $max_level; $i++): ?>
                        <tr>
                            <td>
                                <strong>LV<?= $i ?></strong>
                                <?php if ($i > 8): ?>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteLevel(<?= $i ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="number" name="level_<?= $i ?>_exp" 
                                       value="<?= $level_config[$i]['exp'] ?>" min="0" required>
                            </td>
                            <td>
                                <input type="number" name="level_<?= $i ?>_sign_in" 
                                       value="<?= $level_config[$i]['sign_in'] ?>" min="0" required>
                            </td>
                            <td>
                                <input type="number" name="level_<?= $i ?>_post" 
                                       value="<?= $level_config[$i]['post'] ?>" min="0" required>
                            </td>
                            <td>
                                <input type="number" name="level_<?= $i ?>_reply" 
                                       value="<?= $level_config[$i]['reply'] ?>" min="0" required>
                            </td>
                            <td>
                                <input type="number" name="level_<?= $i ?>_post_limit" 
                                       value="<?= $level_config[$i]['post_limit'] ?>" min="0" required>
                            </td>
                            <td>
                                <input type="number" name="level_<?= $i ?>_reply_limit" 
                                       value="<?= $level_config[$i]['reply_limit'] ?>" min="0" required>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="update_levels" class="btn btn-primary">
                <i class="fas fa-save"></i> 保存所有配置
            </button>
        </div>
    </form>
</div>

<!-- 等级统计 -->
<div class="card">
    <h3>等级分布统计</h3>
    <div class="stats-grid">
        <?php
        // 获取各等级用户数量
        $level_stats = [];
        for ($i = 1; $i <= $max_level; $i++) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE level = ?");
            $stmt->execute([$i]);
            $count = $stmt->fetchColumn();
            $level_stats[$i] = $count;
        }
        
        // 获取总用户数
        $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        ?>
        
        <?php foreach ($level_stats as $level => $count): ?>
            <div class="stat-card">
                <div class="stat-icon level-<?= $level ?>">
                    LV<?= $level ?>
                </div>
                <div class="stat-info">
                    <h3><?= $count ?></h3>
                    <p>用户数量</p>
                    <small><?= $total_users > 0 ? round(($count / $total_users) * 100, 1) : 0 ?>%</small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function toggleAddLevelForm() {
    const form = document.getElementById('addLevelForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function resetToDefault() {
    if (confirm('确定要恢复默认等级配置吗？当前配置将会丢失！')) {
        // 这里可以添加恢复默认配置的逻辑
        alert('恢复默认功能开发中...');
    }
}

function deleteLevel(level) {
    if (confirm(`确定要删除 LV${level} 等级配置吗？`)) {
        // 这里可以添加删除等级配置的逻辑
        alert('删除等级功能开发中...');
    }
}

// 自动计算经验值递增
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('levelConfigForm');
    const expInputs = form.querySelectorAll('input[name$="_exp"]');
    
    expInputs.forEach((input, index) => {
        input.addEventListener('change', function() {
            // 确保经验值递增
            const currentValue = parseInt(this.value);
            const prevInput = expInputs[index - 1];
            
            if (prevInput && currentValue <= parseInt(prevInput.value)) {
                alert('经验值必须大于前一级别的经验值！');
                this.value = parseInt(prevInput.value) + 1000;
            }
        });
    });
});
</script>

<style>
.level-1 { background: #6c757d; }
.level-2 { background: #28a745; }
.level-3 { background: #17a2b8; }
.level-4 { background: #007bff; }
.level-5 { background: #6f42c1; }
.level-6 { background: #e83e8c; }
.level-7 { background: #fd7e14; }
.level-8 { background: #dc3545; }

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 14px;
    font-weight: bold;
    color: white;
}

.data-table input {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.data-table input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}
</style>