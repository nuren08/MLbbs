<?php
// 获取当前规则设置
$settings = getSystemSetting(null, true); // 假设getSystemSetting支持null获取全部设置

// 处理规则更新（合并签到规则的POST处理）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updates = [];
    $success = false;
    
    // 通用/树洞/推广规则更新
    if (isset($_POST['general_rule_points']) || isset($_POST['treehole_rule_points']) || isset($_POST['promotion_rule_points'])) {
        if (isset($_POST['general_rule_points'])) $updates['general_rule_points'] = intval($_POST['general_rule_points']);
        if (isset($_POST['treehole_rule_points'])) $updates['treehole_rule_points'] = intval($_POST['treehole_rule_points']);
        if (isset($_POST['treehole_rule_days'])) $updates['treehole_rule_days'] = intval($_POST['treehole_rule_days']);
        if (isset($_POST['promotion_rule_points'])) $updates['promotion_rule_points'] = intval($_POST['promotion_rule_points']);
        if (isset($_POST['promotion_rule_days'])) $updates['promotion_rule_days'] = intval($_POST['promotion_rule_days']);
        
        $success = updateSystemSettings($updates); // 假设updateSystemSettings是批量更新函数
    }
    
    // 签到规则更新
    if (isset($_POST['update_signin_rule'])) {
        $signin_rule_text = escape($_POST['signin_rule_text']);
        $success = setSystemSetting('signin_rule_text', $signin_rule_text);
    }

    if ($success) {
        echo '<script>showMessage("规则设置更新成功"); setTimeout(() => window.location.reload(), 1000);</script>';
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo '<script>showMessage("更新失败", "error");</script>';
    }
}
?>

<div class="page-header">
    <h2>规则管理</h2>
    <p class="page-description">管理三种论坛规则的参数设置</p>
</div>

<div class="rules-container">
    <!-- 通用规则 -->
    <div class="rule-card">
        <div class="rule-header">
            <h3><<i class="fas fa-cog"></</i> 通用规则管理</h3>
            <span class="rule-badge general">通用规则</span>
        </div>
        
        <form method="POST" class="rule-form">
            <div class="form-group">
                <label>下载附件扣取积分</label>
                <div class="input-group">
                    <input type="number" name="general_rule_points" 
                           value="<?= $settings['general_rule_points'] ?? 30 ?>" 
                           min="0" max="1000" required>
                    <span class="input-suffix">积分</span>
                </div>
                <small class="form-text">用户下载资源附件时需要扣取的积分数</small>
            </div>
            
            <div class="rule-description">
                <h4>通用规则说明：</h4>
                <ul>
                    <li>发帖时分类框仅能选择当前板块下的二级导航分类</li>
                    <li>支持独立标题框和正文框</li>
                    <li>正文支持字体样式、颜色、表情、超链接和图片</li>
                    <li>可添加资源文件附件，下载扣取积分</li>
                    <li>评论区支持字体样式、颜色、超链接、表情和图片</li>
                </ul>
            </div>
            
            <button type="submit" class="btn btn-primary">更新通用规则</button>
        </form>
    </div>

    <!-- 树洞规则 -->
    <div class="rule-card">
        <div class="rule-header">
            <h3><<i class="fas fa-question-circle"></</i> 树洞规则管理</h3>
            <span class="rule-badge treehole">树洞规则</span>
        </div>
        
        <form method="POST" class="rule-form">
            <div class="form-grid">
                <div class="form-group">
                    <label>发帖提问扣取积分</label>
                    <div class="input-group">
                        <input type="number" name="treehole_rule_points" 
                               value="<?= $settings['treehole_rule_points'] ?? 100 ?>" 
                               min="0" max="1000" required>
                        <span class="input-suffix">积分</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>帖子有效天数</label>
                    <div class="input-group">
                        <input type="number" name="treehole_rule_days" 
                               value="<?= $settings['treehole_rule_days'] ?? 7 ?>" 
                               min="1" max="30" required>
                        <span class="input-suffix">天</span>
                    </div>
                </div>
            </div>
            
            <div class="rule-description">
                <h4>树洞规则说明：</h4>
                <ul>
                    <li>发帖人称为苦主，可匿名提问</li>
                    <li>发帖无积分奖励，需扣除积分作为回答人奖励</li>
                    <li>7天倒计时，可采纳1-2个回答</li>
                    <li>倒计时结束自动挑选回答字数最多的奖励</li>
                    <li>最后24小时可申请关闭帖子</li>
                    <li>管理员审核关闭申请</li>
                    <li>附件下载免积分（仅限提问和回答双方）</li>
                </ul>
            </div>
            
            <button type="submit" class="btn btn-primary">更新树洞规则</button>
        </form>
    </div>

    <!-- 推广规则 -->
    <div class="rule-card">
        <div class="rule-header">
            <h3><<i class="fas fa-ad"></</i> 推广规则管理</h3>
            <span class="rule-badge promotion">推广规则</span>
        </div>
        
        <form method="POST" class="rule-form">
            <div class="form-grid">
                <div class="form-group">
                    <label>发帖扣取积分</label>
                    <div class="input-group">
                        <input type="number" name="promotion_rule_points" 
                               value="<?= $settings['promotion_rule_points'] ?? 300 ?>" 
                               min="0" max="1000" required>
                        <span class="input-suffix">积分</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>帖子有效天数</label>
                    <div class="input-group">
                        <input type="number" name="promotion_rule_days" 
                               value="<?= $settings['promotion_rule_days'] ?? 15 ?>" 
                               min="1" max="30" required>
                        <span class="input-suffix">天</span>
                    </div>
                </div>
            </div>
            
            <div class="rule-description">
                <h4>推广规则说明：</h4>
                <ul>
                    <li>专门用于外部应用拉新广告</li>
                    <li>发帖无积分奖励，需扣除积分</li>
                    <li>审核通过后15天倒计时，结束后自动删除</li>
                    <li>正文支持字体样式、颜色、超链接、表情和图片</li>
                    <li>不支持添加其他附件</li>
                    <li>评论区仅支持文字和图片</li>
                    <li>浏览广告奖励5积分/天，评论额外奖励3积分/天</li>
                </ul>
            </div>
            
            <button type="submit" class="btn btn-primary">更新推广规则</button>
        </form>
    </div>

    <!-- 签到规则管理（添加到此处） -->
    <div class="rule-card">
        <div class="rule-header">
            <h3><<i class="fas fa-calendar-check"></</i> 签到规则管理</h3>
            <span class="rule-badge general">签到规则</span>
        </div>
        
        <form method="POST" class="rule-form">
            <div class="form-group">
                <label>签到规则文本</label>
                <textarea name="signin_rule_text" rows="8" required placeholder="请输入签到规则文本"><?= getSystemSetting('signin_rule_text', '默认签到规则文本...') ?></textarea>
                <small class="form-text">支持HTML格式，将在签到页面展示给所有用户</small>
            </div>
            
            <div class="rule-description">
                <h4>当前签到规则包含：</h4>
                <ul>
                    <li>会员等级系统（8个等级）</li>
                    <li>每日签到获取积分和经验值</li>
                    <li>连续签到7/30/365天可抽奖</li>
                    <li>断签补签机制</li>
                    <li>发帖回帖奖励规则</li>
                    <li>智能审核系统</li>
                </ul>
            </div>
            
            <button type="submit" name="update_signin_rule" class="btn btn-primary">更新签到规则</button>
        </form>
    </div>
</div>

<style>
.rules-container {
    display: grid;
    gap: 20px;
}

.rule-card {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.rule-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.rule-header h3 {
    color: #333;
    margin: 0;
}

.rule-badge {
    padding: 5px 10px;
    border-radius: 15px;
    color: white;
    font-size: 12px;
    font-weight: bold;
}

.rule-badge.general { background: #28a745; }
.rule-badge.treehole { background: #ffc107; color: #000; }
.rule-badge.promotion { background: #dc3545; }

.rule-form {
    margin-top: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.input-group {
    display: flex;
    align-items: center;
}

.input-suffix {
    background: #f8f9fa;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-left: none;
    border-radius: 0 4px 4px 0;
}

.rule-description {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin: 20px 0;
}

.rule-description h4 {
    margin-top: 0;
    color: #555;
}

.rule-description ul {
    margin-bottom: 0;
    color: #666;
}

.form-text {
    color: #6c757d;
    font-size: 12px;
    margin-top: 5px;
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .rule-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}
</style>
