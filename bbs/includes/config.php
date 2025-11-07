<?php
// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', 0); // 生产环境设为0
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 会话设置
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'ser9y838ug2i3jx');
define('DB_USER', 'ser9y838ug2i3jx');
define('DB_PASS', 'jby858');
define('DB_CHARSET', 'utf8mb4');

// 网站配置
define('SITE_URL', 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('BASE_PATH', '/bbs');
define('UPLOAD_PATH', __DIR__ . '/../uploads');
define('ASSETS_PATH', BASE_PATH . '/assets');

// 创建数据库连接
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log("数据库连接失败: " . $e->getMessage());
    die("系统维护中，请稍后再试");
}

// 自动加载函数
spl_autoload_register(function ($className) {
    $classFile = __DIR__ . '/../classes/' . $className . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

// 常用函数
function getSystemSetting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        error_log("获取系统设置失败: " . $e->getMessage());
        return $default;
    }
}

// 新增：获取所有邮箱相关配置（替代原代码的getAdminSettings）
function getEmailSettings() {
    global $pdo;
    $keys = [
        'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
        'from_email', 'from_name', 'email_image1_url', 'email_image2_url',
        'email_template'
    ];
    $settings = [];
    foreach ($keys as $key) {
        $settings[$key] = getSystemSetting($key);
    }
    return $settings;
}

function setSystemSetting($key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("REPLACE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        return $stmt->execute([$key, $value]);
    } catch (PDOException $e) {
        error_log("设置系统设置失败: " . $e->getMessage());
        return false;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("获取用户信息失败: " . $e->getMessage());
        return null;
    }
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function escape($data) {
    if (is_array($data)) {
        return array_map('escape', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function generateVerificationCode($length = 4) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function getUserLevel($exp) {
    $levels = [
        1 => 0,
        2 => 1000,
        3 => 10000,
        4 => 50000,
        5 => 150000,
        6 => 500000,
        7 => 1000000,
        8 => 5000000
    ];
    $level = 1;
    foreach ($levels as $lvl => $minExp) {
        if ($exp >= $minExp) {
            $level = $lvl;
        } else {
            break;
        }
    }
    return $level;
}

function getLevelRewards($level) {
    $rewards = [
        1 => ['sign_in' => 3, 'post' => 5, 'reply' => 1, 'post_limit' => 5, 'reply_limit' => 20],
        2 => ['sign_in' => 4, 'post' => 6, 'reply' => 1, 'post_limit' => 5, 'reply_limit' => 30],
        3 => ['sign_in' => 5, 'post' => 7, 'reply' => 1, 'post_limit' => 6, 'reply_limit' => 50],
        4 => ['sign_in' => 6, 'post' => 8, 'reply' => 1, 'post_limit' => 6, 'reply_limit' => 80],
        5 => ['sign_in' => 7, 'post' => 9, 'reply' => 1, 'post_limit' => 7, 'reply_limit' => 1000],
        6 => ['sign_in' => 8, 'post' => 10, 'reply' => 2, 'post_limit' => 8, 'reply_limit' => 100],
        7 => ['sign_in' => 9, 'post' => 11, 'reply' => 2, 'post_limit' => 9, 'reply_limit' => 150],
        8 => ['sign_in' => 10, 'post' => 12, 'reply' => 3, 'post_limit' => 10, 'reply_limit' => 188]
    ];
    return isset($rewards[$level]) ? $rewards[$level] : $rewards[1];
}

// 检查表是否存在
function checkTableExists($tableName) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// 收藏相关函数
function isPostFavorited($user_id, $post_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$user_id, $post_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("检查收藏状态失败: " . $e->getMessage());
        return false;
    }
}

function addToFavorites($user_id, $post_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO favorites (user_id, post_id) VALUES (?, ?)");
        return $stmt->execute([$user_id, $post_id]);
    } catch (PDOException $e) {
        error_log("添加收藏失败: " . $e->getMessage());
        return false;
    }
}

function removeFromFavorites($user_id, $post_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND post_id = ?");
        return $stmt->execute([$user_id, $post_id]);
    } catch (PDOException $e) {
        error_log("取消收藏失败: " . $e->getMessage());
        return false;
    }
}

function getUserFavorites($user_id, $page = 1, $per_page = 10) {
    global $pdo;
    $offset = ($page - 1) * $per_page;
    
    try {
        $stmt = $pdo->prepare("
            SELECT SQL_CALC_FOUND_ROWS 
                   p.*, u.username, u.nickname, c.name as category_name,
                   f.created_at as favorited_at
            FROM favorites f
            JOIN posts p ON f.post_id = p.id
            LEFT JOIN users u ON p.author_id = u.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE f.user_id = ? AND p.status = 'approved'
            ORDER BY f.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $favorites = $stmt->fetchAll();
        
        $total_stmt = $pdo->query("SELECT FOUND_ROWS()");
        $total = $total_stmt->fetchColumn();
        
        return [
            'favorites' => $favorites,
            'total' => $total,
            'pages' => ceil($total / $per_page)
        ];
    } catch (PDOException $e) {
        error_log("获取用户收藏失败: " . $e->getMessage());
        return ['favorites' => [], 'total' => 0, 'pages' => 0];
    }
}

// 新增：邮件发送函数
function sendEmail($to_email, $subject, $content) {
    global $pdo;
    
    // 获取邮箱配置（使用新增的getEmailSettings）
    $settings = getEmailSettings();
    
    $smtp_host = $settings['smtp_host'] ?? '';
    $smtp_port = $settings['smtp_port'] ?? '587';
    $smtp_username = $settings['smtp_username'] ?? '';
    $smtp_password = $settings['smtp_password'] ?? '';
    $from_email = $settings['from_email'] ?? '';
    $from_name = $settings['from_name'] ?? 'ML论坛';
    
    // 检查配置是否完整
    if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
        error_log("邮箱配置不完整，无法发送邮件");
        return false;
    }
    
    // 添加邮件图片
    $image1_url = $settings['email_image1_url'] ?? '';
    $image2_url = $settings['email_image2_url'] ?? '';
    
    $full_content = '';
    if (!empty($image1_url)) {
        $full_content .= "<div style='text-align: center; margin-bottom: 20px;'><img src='{$image1_url}' alt='' style='max-width: 600px;'></div>";
    }
    $full_content .= "<div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>{$content}</div>";
    if (!empty($image2_url)) {
        $full_content .= "<div style='text-align: center; margin-top: 20px;'><img src='{$image2_url}' alt='' style='max-width: 600px;'></div>";
    }
    
    // 构造mail()函数的headers
    $headers = [
        'From: ' . $from_name . ' <' . $from_email . '>',
        'Reply-To: ' . $from_email,
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
        'MIME-Version: 1.0'
    ];
    
    // 记录邮件发送日志
    try {
        $log_stmt = $pdo->prepare("INSERT INTO email_logs (to_email, subject, content, type, status) VALUES (?, ?, ?, 'system', 'sent')");
        $log_stmt->execute([$to_email, $subject, $content]);
    } catch (PDOException $e) {
        error_log("记录邮件日志失败: " . $e->getMessage());
    }
    
    return mail($to_email, $subject, $full_content, implode("\r\n", $headers));
}

// 新增：发送验证码邮件
function sendVerificationEmail($email, $code, $type = 'register') {
    $settings = getEmailSettings();
    $template = $settings['email_template'] ?? '亲爱的ML论坛会员，您本次的验证码为{code}，5分钟内有效，如非本人操作，请您忽略。[ML论坛]';
    
    $message = str_replace('{code}', $code, $template);
    $subject = "ML论坛验证码";
    
    return sendEmail($email, $subject, $message);
}

// 论坛核心功能类
class ForumCore {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // 获取导航分类
    public function getCategories() {
        $stmt = $this->pdo->query("SELECT * FROM categories WHERE status = 1 ORDER BY sort_order ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($categories as $category) {
            if ($category['parent_id'] == 0) {
                $result[$category['id']] = $category;
                $result[$category['id']]['children'] = [];
            } else {
                if (isset($result[$category['parent_id']])) {
                    $result[$category['parent_id']]['children'][] = $category;
                }
            }
        }
        return $result;
    }
    
    // 获取板块规则
    public function getCategoryRules($category_id) {
        $stmt = $this->pdo->prepare("SELECT rule_type FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($category) {
            switch ($category['rule_type']) {
                case 'general':
                    $points = getSystemSetting('general_rule_points', 30);
                    return [
                        'type' => 'general',
                        'download_points' => $points,
                        'description' => '通用板块规则：下载附件扣取积分'
                    ];
                case 'treehole':
                    $points = getSystemSetting('treehole_rule_points', 100);
                    $days = getSystemSetting('treehole_rule_days', 7);
                    return [
                        'type' => 'treehole',
                        'post_points' => $points,
                        'expiry_days' => $days,
                        'description' => "树洞规则：发帖扣{$points}积分，有效期{$days}天"
                    ];
                case 'promotion':
                    $points = getSystemSetting('promotion_rule_points', 300);
                    $days = getSystemSetting('promotion_rule_days', 15);
                    return [
                        'type' => 'promotion',
                        'post_points' => $points,
                        'expiry_days' => $days,
                        'description' => "推广规则：发帖扣{$points}积分，有效期{$days}天"
                    ];
            }
        }
        return null;
    }
    
    // 检查用户权限
    public function checkUserPermission($user_id, $action) {
        $user = $this->getUserInfo($user_id);
        if (!$user) return ['success' => false, 'message' => '用户不存在'];
        
        // 检查是否实名认证
        if ($action === 'post' || $action === 'reply') {
            $realname_required = getSystemSetting('realname_required', '0');
            if ($realname_required && !$user['realname_verified']) {
                return ['success' => false, 'message' => '请先完成实名认证'];
            }
        }
        
        // 检查等级限制
        $level = $user['level'];
        $limits = getLevelRewards($level);
        
        // 检查今日发帖/回帖数量
        $today = date('Y-m-d');
        if ($action === 'post') {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM posts WHERE author_id = ? AND DATE(created_at) = ?");
            $stmt->execute([$user_id, $today]);
            $today_posts = $stmt->fetchColumn();
            
            if ($today_posts >= $limits['post_limit']) {
                return ['success' => false, 'message' => '今日发帖数量已达上限'];
            }
        }
        
        if ($action === 'reply') {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM replies WHERE user_id = ? AND DATE(created_at) = ?");
            $stmt->execute([$user_id, $today]);
            $today_replies = $stmt->fetchColumn();
            
            if ($today_replies >= $limits['reply_limit']) {
                return ['success' => false, 'message' => '今日回帖数量已达上限'];
            }
        }
        
        return ['success' => true];
    }
    
    // 获取用户信息
    public function getUserInfo($user_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // 更新用户积分和经验
    public function updateUserPoints($user_id, $points, $exp) {
        $stmt = $this->pdo->prepare("UPDATE users SET points = points + ?, exp = exp + ? WHERE id = ?");
        $stmt->execute([$points, $exp, $user_id]);
        
        // 检查升级
        $this->checkLevelUp($user_id);
    }
    
    // 检查用户升级
    public function checkLevelUp($user_id) {
        $user = $this->getUserInfo($user_id);
        $exp = $user['exp'];
        $new_level = getUserLevel($exp);
        
        if ($new_level > $user['level']) {
            $stmt = $this->pdo->prepare("UPDATE users SET level = ? WHERE id = ?");
            $stmt->execute([$new_level, $user_id]);
            return $new_level;
        }
        
        return $user['level'];
    }
}

// 创建论坛核心实例
$forum = new ForumCore($pdo);

// 安全头
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// 创建必要的目录
$directories = [
    __DIR__ . '/../logs',
    __DIR__ . '/../uploads',
    __DIR__ . '/../uploads/images',
    __DIR__ . '/../uploads/attachments',
    __DIR__ . '/../uploads/avatars',
    __DIR__ . '/../uploads/backgrounds'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>
