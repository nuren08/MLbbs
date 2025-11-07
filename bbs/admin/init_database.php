<?php
// 数据库初始化脚本
header('Content-Type: text/html; charset=utf-8');

$host = 'localhost';
$dbname = 'ser9y838ug2i3jx';
$username = 'ser9y838ug2i3jx';
$password = 'jby858';
$port = 3306;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // 首先检查是否已经存在表，如果存在则先删除（仅用于初始化）
    $tables = [
        'attachments', 'replies', 'posts', 'treehole_close_requests',
        'user_follows', 'user_blocks', 'messages', 'announcement_reads',
        'lottery_records', 'search_logs', 'favorites', 'points_log',
        'user_sign_ins', 'site_messages', 'sign_ins', 'users',
        'categories', 'verification_codes', 'system_settings', 'ads',
        'announcements', 'visit_stats', 'email_logs'
    ];

    // 禁用外键检查
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 删除现有表
    foreach ($tables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS $table");
        } catch (PDOException $e) {
            // 忽略删除表时的错误，继续执行
        }
    }

    // 重新启用外键检查
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // === 用户表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNIQUE NOT NULL,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        level INT DEFAULT 1,
        exp BIGINT DEFAULT 0,
        points INT DEFAULT 0,
        avatar VARCHAR(255) DEFAULT 'tx_ml.png',
        background VARCHAR(255) DEFAULT 'bj_ml.png',
        nickname VARCHAR(50),
        location_province VARCHAR(50),
        location_city VARCHAR(50),
        location_county VARCHAR(50),
        signature TEXT,
        realname_verified TINYINT DEFAULT 0,
        realname_surname VARCHAR(10),
        realname_idcard VARCHAR(50),
        security_question1 VARCHAR(255),
        security_question2 VARCHAR(255),
        allow_follow TINYINT DEFAULT 1,
        register_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME,
        ip_address VARCHAR(45),
        status TINYINT DEFAULT 1
    )");

    // === 验证码表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS verification_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) NOT NULL,
        code VARCHAR(10) NOT NULL,
        type ENUM('register', 'login', 'forgot', 'change_email', 'delete_account') NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        used TINYINT DEFAULT 0
    )");

    // === 导航分类表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        parent_id INT DEFAULT 0,
        url VARCHAR(255),
        rule_type ENUM('general', 'treehole', 'promotion') DEFAULT 'general',
        sort_order INT DEFAULT 0,
        status TINYINT DEFAULT 1
    )");

    // === 帖子表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content LONGTEXT NOT NULL,
        author_id INT NOT NULL,
        category_id INT NOT NULL,
        subcategory_id INT NOT NULL,
        rule_type ENUM('general', 'treehole', 'promotion') NOT NULL,
        is_anonymous TINYINT DEFAULT 0,
        points_required INT DEFAULT 0,
        expiry_date DATETIME,
        close_request TINYINT DEFAULT 0,
        close_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        attachments TEXT,
        views INT DEFAULT 0,
        likes INT DEFAULT 0,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        ip_address VARCHAR(45)
    )");

    // === 签到表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS sign_ins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        sign_date DATE NOT NULL,
        continuous_days INT NOT NULL,
        total_days INT NOT NULL,
        points_earned INT NOT NULL,
        is_makeup TINYINT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // === 系统配置表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        description TEXT
    )");

    // === 用户关注关系表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_follows (
        id INT AUTO_INCREMENT PRIMARY KEY,
        follower_id INT NOT NULL,
        following_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_follow (follower_id, following_id)
    )");

    // === 用户拉黑关系表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_blocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        blocked_user_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_block (user_id, blocked_user_id)
    )");

    // === 私信表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_user_id INT NOT NULL,
        to_user_id INT NOT NULL,
        content TEXT NOT NULL,
        is_read TINYINT DEFAULT 0,
        read_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_conversation (from_user_id, to_user_id, created_at),
        INDEX idx_unread (to_user_id, is_read, created_at)
    )");

    // === 公告表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        status TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // === 广告表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS ads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_url VARCHAR(255) NOT NULL,
        url VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        status TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // === 用户阅读公告记录表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcement_reads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        announcement_id INT NOT NULL,
        read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_read (user_id, announcement_id)
    )");

    // === 抽奖记录表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS lottery_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        lottery_type ENUM('7', '30', '365') NOT NULL,
        prize_points INT NOT NULL,
        prize_name VARCHAR(100) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // === 回复表（用于帖子评论）===
    $pdo->exec("CREATE TABLE IF NOT EXISTS replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        likes INT DEFAULT 0,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        INDEX idx_post (post_id, created_at),
        INDEX idx_user (user_id, created_at)
    )");

    // === 附件表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT,
        reply_id INT,
        user_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        filepath VARCHAR(500) NOT NULL,
        filetype VARCHAR(100) NOT NULL,
        filesize INT NOT NULL,
        download_count INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_post (post_id),
        INDEX idx_reply (reply_id),
        INDEX idx_user (user_id)
    )");

    // === 搜索记录表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS search_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        keyword VARCHAR(255) NOT NULL,
        results_count INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_keyword (keyword),
        INDEX idx_user (user_id)
    )");

    // === 收藏表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        post_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_favorite (user_id, post_id),
        INDEX idx_user (user_id),
        INDEX idx_post (post_id)
    )");

    // === 积分记录表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS points_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        points INT NOT NULL,
        exp INT NOT NULL,
        type ENUM('sign_in', 'post', 'reply', 'download', 'lottery', 'admin_adjust') NOT NULL,
        description VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_type (type),
        INDEX idx_created (created_at)
    )");

    // === 用户签到记录表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_sign_ins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        sign_date DATE NOT NULL,
        continuous_days INT NOT NULL DEFAULT 1,
        total_days INT NOT NULL DEFAULT 1,
        points_earned INT NOT NULL,
        is_makeup TINYINT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_date (user_id, sign_date),
        INDEX idx_user (user_id),
        INDEX idx_date (sign_date)
    )");

    // === 新增：树洞关闭申请记录表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS treehole_close_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        user_id INT NOT NULL,
        reason TEXT,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_post (post_id),
        INDEX idx_user (user_id),
        INDEX idx_status (status)
    )");

    // === 新增：邮件发送记录表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        to_email VARCHAR(100) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        type ENUM('verification', 'notification', 'admin_broadcast') NOT NULL,
        status ENUM('sent', 'failed') NOT NULL,
        error_message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (to_email),
        INDEX idx_type (type),
        INDEX idx_created (created_at)
    )");

    // === 新增：站内信表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_user_id INT,
        to_user_id INT,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        is_read TINYINT DEFAULT 0,
        message_type ENUM('system', 'user', 'admin') DEFAULT 'system',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        read_at DATETIME NULL,
        INDEX idx_to_user (to_user_id, is_read),
        INDEX idx_type (message_type),
        INDEX idx_created (created_at)
    )");

    // === 新增：访问统计表 ===
    $pdo->exec("CREATE TABLE IF NOT EXISTS visit_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visit_date DATE NOT NULL,
        page_views INT DEFAULT 0,
        unique_visitors INT DEFAULT 0,
        new_registrations INT DEFAULT 0,
        new_posts INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_date (visit_date),
        INDEX idx_date (visit_date)
    )");

    // 插入默认管理员 - 先检查表是否存在再插入
    try {
        // 检查users表是否存在
        $tableExists = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if ($tableExists) {
            $adminCheck = $pdo->query("SELECT COUNT(*) FROM users WHERE email = '424300791@qq.com'")->fetchColumn();
            if ($adminCheck == 0) {
                $adminPassword = password_hash('jby858', PASSWORD_DEFAULT);
                $pdo->exec("INSERT INTO users (user_id, username, email, password, level, exp, points, nickname, register_time) 
                VALUES (1000, 'admin', '424300791@qq.com', '$adminPassword', 8, 5000000, 10000, '管理员', NOW())");
            }
        }
    } catch (PDOException $e) {
        // 忽略插入管理员时的错误
    }

    // 插入默认导航
    try {
        $pdo->exec("INSERT IGNORE INTO categories (name, parent_id, url, rule_type, sort_order) VALUES
        ('论坛首页', 0, '/bbs/', 'general', 1),
        ('生活', 0, '', 'general', 2),
        ('科技码农', 0, '', 'general', 3),
        ('树洞求答', 0, '', 'treehole', 4),
        ('推广乐园', 0, '', 'promotion', 5),
        ('网站首页', 1, '/', 'general', 1),
        ('趣味', 2, '', 'general', 1),
        ('美食', 2, '', 'general', 2),
        ('美妆护肤', 2, '', 'general', 3),
        ('职场', 2, '', 'general', 4),
        ('运动', 2, '', 'general', 5),
        ('科技最前沿', 3, '', 'general', 1),
        ('互联网开发', 3, '', 'general', 2),
        ('四大运营商', 3, '', 'general', 3),
        ('手机数码', 3, '', 'general', 4)");
    } catch (PDOException $e) {
        // 忽略插入导航时的错误
    }

    // 插入默认系统设置（已添加签到规则）
    $defaultSettings = [
        ['site_name', 'ML论坛', '网站名称'],
        ['email_template', '亲爱的ML论坛会员，您本次的验证码为{code}，5分钟内有效，如非本人操作，请您忽略。[ML论坛]', '邮件验证码模板'],
        ['realname_required', '0', '是否开启实名认证'],
        ['ad_display', '1', '是否显示广告'],
        ['announcement_display', '1', '是否显示公告'],
        ['smtp_host', '', 'SMTP服务器'],
        ['smtp_port', '587', 'SMTP端口'],
        ['smtp_username', '', 'SMTP用户名'],
        ['smtp_password', '', 'SMTP密码'],
        ['from_email', '', '发件人邮箱'],
        ['from_name', 'ML论坛', '发件人名称'],
        ['email_image1_url', '', '邮件图片1URL'],
        ['email_image2_url', '', '邮件图片2URL'],
        ['general_rule_points', '30', '通用规则下载附件扣积分'],
        ['treehole_rule_points', '100', '树洞规则发帖扣积分'],
        ['treehole_rule_days', '7', '树洞规则帖子有效期(天)'],
        ['promotion_rule_points', '300', '推广规则发帖扣积分'],
        ['promotion_rule_days', '15', '推广规则帖子有效期(天)'],
        ['aliyun_appcode', '', '阿里云实名认证AppCode'],
        ['aliyun_appkey', '', '阿里云实名认证AppKey'],
        ['aliyun_appsecret', '', '阿里云实名认证AppSecret'],
        // 等级配置
        ['level_1_exp', '0', '等级1所需经验'],
        ['level_1_sign_in', '3', '等级1签到奖励'],
        ['level_1_post', '5', '等级1发帖奖励'],
        ['level_1_reply', '1', '等级1回帖奖励'],
        ['level_1_post_limit', '5', '等级1发帖上限'],
        ['level_1_reply_limit', '20', '等级1回帖上限'],
        ['level_2_exp', '1000', '等级2所需经验'],
        ['level_2_sign_in', '4', '等级2签到奖励'],
        ['level_2_post', '6', '等级2发帖奖励'],
        ['level_2_reply', '1', '等级2回帖奖励'],
        ['level_2_post_limit', '5', '等级2发帖上限'],
        ['level_2_reply_limit', '30', '等级2回帖上限'],
        ['level_3_exp', '10000', '等级3所需经验'],
        ['level_3_sign_in', '5', '等级3签到奖励'],
        ['level_3_post', '7', '等级3发帖奖励'],
        ['level_3_reply', '1', '等级3回帖奖励'],
        ['level_3_post_limit', '6', '等级3发帖上限'],
        ['level_3_reply_limit', '50', '等级3回帖上限'],
        ['level_4_exp', '50000', '等级4所需经验'],
        ['level_4_sign_in', '6', '等级4签到奖励'],
        ['level_4_post', '8', '等级4发帖奖励'],
        ['level_4_reply', '1', '等级4回帖奖励'],
        ['level_4_post_limit', '6', '等级4发帖上限'],
        ['level_4_reply_limit', '80', '等级4回帖上限'],
        ['level_5_exp', '150000', '等级5所需经验'],
        ['level_5_sign_in', '7', '等级5签到奖励'],
        ['level_5_post', '9', '等级5发帖奖励'],
        ['level_5_reply', '1', '等级5回帖奖励'],
        ['level_5_post_limit', '7', '等级5发帖上限'],
        ['level_5_reply_limit', '1000', '等级5回帖上限'],
        ['level_6_exp', '500000', '等级6所需经验'],
        ['level_6_sign_in', '8', '等级6签到奖励'],
        ['level_6_post', '10', '等级6发帖奖励'],
        ['level_6_reply', '2', '等级6回帖奖励'],
        ['level_6_post_limit', '8', '等级6发帖上限'],
        ['level_6_reply_limit', '100', '等级6回帖上限'],
        ['level_7_exp', '1000000', '等级7所需经验'],
        ['level_7_sign_in', '9', '等级7签到奖励'],
        ['level_7_post', '11', '等级7发帖奖励'],
        ['level_7_reply', '2', '等级7回帖奖励'],
        ['level_7_post_limit', '9', '等级7发帖上限'],
        ['level_7_reply_limit', '150', '等级7回帖上限'],
        ['level_8_exp', '5000000', '等级8所需经验'],
        ['level_8_sign_in', '10', '等级8签到奖励'],
        ['level_8_post', '12', '等级8发帖奖励'],
        ['level_8_reply', '3', '等级8回帖奖励'],
        ['level_8_post_limit', '10', '等级8发帖上限'],
        ['level_8_reply_limit', '188', '等级8回帖上限'],
        // 新增：签到规则文本
        ['signin_rule_text', '<h3>ML论坛签到规则</h3>

<p>欢迎参与每日签到，获取积分和经验值奖励！</p>

<h4>🎯 会员等级系统</h4>

<ul>

<li>LV1 (0经验)</li>

<li>LV2 (1000经验)</li>

<li>LV3 (10000经验)</li>

<li>LV4 (50000经验)</li>

<li>LV5 (150000经验)</li>

<li>LV6 (500000经验)</li>

<li>LV7 (1000000经验)</li>

<li>LV8 (5000000经验)</li>

</ul>

<h4>💰 签到奖励</h4>

<ul>

<li>LV1签到(3积分) 发帖(5积分) 回帖(1积分)</li>

<li>LV2签到(4积分) 发帖(6积分) 回帖(1积分)</li>

<li>LV3签到(5积分) 发帖(7积分) 回帖(1积分)</li>

<li>LV4签到(6积分) 发帖(8积分) 回帖(1积分)</li>

<li>LV5签到(7积分) 发帖(9积分) 回帖(1积分)</li>

<li>LV6签到(8积分) 发帖(10积分) 回帖(2积分)</li>

<li>LV7签到(9积分) 发帖(11积分) 回帖(2积分)</li>

<li>LV8签到(10积分) 发帖(12积分) 回帖(3积分)</li>

</ul>

<h4>🎁 连续签到奖励</h4>

<ul>

<li>连续签到7天：抽奖机会（10-50积分）</li>

<li>连续签到30天：抽奖机会（10-100积分）</li>

<li>连续签到365天：抽奖机会（500-3650积分）</li>

</ul>

<h4>⏰ 补签规则</h4>

<ul>

<li>断签后72小时内可补签</li>

<li>小于7天：扣5积分</li>

<li>7-30天：扣18积分</li>

<li>30天以上：扣38积分</li>

</ul>

<p>💡 提示：签到以7天为周期，第8天开始新周期！</p>', '签到页面规则文本']
    ];

    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
        foreach ($defaultSettings as $setting) {
            $stmt->execute($setting);
        }
    } catch (PDOException $e) {
        // 忽略插入系统设置时的错误
    }

    // 插入示例公告
    try {
        $pdo->exec("INSERT IGNORE INTO announcements (title, content, status) VALUES 
        ('欢迎来到ML论坛！', '欢迎各位会员加入ML论坛！这是一个分享知识、交流思想的平台。请遵守社区规则，共同营造良好的交流环境。', 1),
        ('论坛使用指南', '新用户请阅读论坛使用指南，了解各版块规则和功能使用方法。', 1)");
    } catch (PDOException $e) {
        // 忽略插入公告时的错误
    }

    // 插入示例广告
    try {
        $pdo->exec("INSERT IGNORE INTO ads (image_url, url, sort_order, status) VALUES 
        ('/bbs/assets/images/logo_ml.png', '/bbs/', 1, 1),
        ('/bbs/assets/images/bj_ml.png', '/bbs/', 2, 1)");
    } catch (PDOException $e) {
        // 忽略插入广告时的错误
    }

    // 插入今日访问统计
    try {
        $today = date('Y-m-d');
        $pdo->exec("INSERT IGNORE INTO visit_stats (visit_date, page_views, unique_visitors, new_registrations, new_posts) 
        VALUES ('$today', 0, 0, 0, 0)");
    } catch (PDOException $e) {
        // 忽略插入访问统计时的错误
    }

    echo "<h2>数据库初始化成功！</h2>";
    echo "<p>以下数据表已创建/更新：</p>";
    echo "<ul>";
    echo "<li>users - 用户表</li>";
    echo "<li>verification_codes - 验证码表</li>";
    echo "<li>categories - 导航分类表</li>";
    echo "<li>posts - 帖子表</li>";
    echo "<li>sign_ins - 签到表</li>";
    echo "<li>system_settings - 系统配置表</li>";
    echo "<li>user_follows - 用户关注关系表</li>";
    echo "<li>user_blocks - 用户拉黑关系表</li>";
    echo "<li>messages - 私信表</li>";
    echo "<li>announcements - 公告表</li>";
    echo "<li>ads - 广告表</li>";
    echo "<li>announcement_reads - 用户阅读公告记录表</li>";
    echo "<li>lottery_records - 抽奖记录表</li>";
    echo "<li>replies - 回复表</li>";
    echo "<li>attachments - 附件表</li>";
    echo "<li>search_logs - 搜索记录表</li>";
    echo "<li>favorites - 收藏表</li>";
    echo "<li>points_log - 积分记录表</li>";
    echo "<li>user_sign_ins - 用户签到记录表</li>";
    echo "<li>treehole_close_requests - 树洞关闭申请记录表</li>";
    echo "<li>email_logs - 邮件发送记录表</li>";
    echo "<li>site_messages - 站内信表</li>";
    echo "<li>visit_stats - 访问统计表</li>";
    echo "</ul>";
    echo "<p>默认数据已插入：</p>";
    echo "<ul>";
    echo "<li>默认管理员账号：424300791@qq.com / jby858</li>";
    echo "<li>默认导航分类</li>";
    echo "<li>默认系统设置（包含等级配置、阿里云配置及签到规则）</li>";
    echo "<li>示例公告和广告</li>";
    echo "<li>今日访问统计记录</li>";
    echo "</ul>";
    echo "<p style='color: green; font-weight: bold;'>初始化完成！您现在可以开始使用论坛系统。</p>";

} catch(PDOException $e) {
    echo "<h2 style='color: red;'>数据库初始化失败</h2>";
    echo "<p>错误信息: " . $e->getMessage() . "</p>";
    echo "<p>请检查数据库配置信息是否正确。</p>";
    echo "<p>如果问题持续存在，请联系虚拟主机提供商检查数据库权限。</p>";
}
?>