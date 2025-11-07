<?php
// 创建默认管理员脚本
header('Content-Type: text/html; charset=utf-8');

// 包含配置文件
require_once '../includes/config.php';

// 检查是否已存在管理员
$adminCheck = $pdo->query("SELECT COUNT(*) FROM users WHERE email = '424300791@qq.com'")->fetchColumn();

if ($adminCheck > 0) {
    echo "<h2>管理员账号已存在</h2>";
    echo "<p>默认管理员账号：424300791@qq.com</p>";
    echo "<p>默认密码：jby858</p>";
    echo "<p><a href='index.php'>进入管理后台</a></p>";
    exit;
}

// 创建管理员
try {
    $adminPassword = password_hash('jby858', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (user_id, username, email, password, level, exp, points, nickname, register_time) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->execute([1000, 'admin', '424300791@qq.com', $adminPassword, 8, 5000000, 10000, '管理员']);
    
    echo "<!DOCTYPE html>
    <html lang='zh-CN'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>创建管理员成功 - ML论坛</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin: 0; 
                padding: 0; 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                min-height: 100vh; 
            }
            .success-card { 
                background: white; 
                border-radius: 15px; 
                padding: 40px; 
                box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
                text-align: center; 
                max-width: 500px; 
                width: 90%; 
            }
            .success-icon { 
                font-size: 64px; 
                color: #28a745; 
                margin-bottom: 20px; 
            }
            h1 { 
                color: #333; 
                margin-bottom: 20px; 
            }
            .admin-info { 
                background: #f8f9fa; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0; 
                text-align: left; 
            }
            .admin-info h3 { 
                margin-top: 0; 
                color: #495057; 
            }
            .btn { 
                display: inline-block; 
                padding: 12px 30px; 
                background: #007bff; 
                color: white; 
                text-decoration: none; 
                border-radius: 5px; 
                margin: 10px 5px; 
                transition: background 0.3s ease; 
            }
            .btn:hover { 
                background: #0056b3; 
            }
            .btn-danger { 
                background: #dc3545; 
            }
            .btn-danger:hover { 
                background: #c82333; 
            }
            .warning { 
                background: #fff3cd; 
                border: 1px solid #ffeaa7; 
                padding: 15px; 
                border-radius: 5px; 
                margin: 20px 0; 
                color: #856404; 
            }
        </style>
    </head>
    <body>
        <div class='success-card'>
            <div class='success-icon'>✓</div>
            <h1>管理员创建成功！</h1>
            
            <div class='admin-info'>
                <h3>管理员账号信息：</h3>
                <p><strong>邮箱：</strong>424300791@qq.com</p>
                <p><strong>密码：</strong>jby858</p>
                <p><strong>用户ID：</strong>1000</p>
                <p><strong>等级：</strong>LV8 (最高权限)</p>
            </div>
            
            <div class='warning'>
                <strong>重要提醒：</strong>
                <p>请立即登录管理后台修改默认密码，并配置邮箱SMTP设置以便发送验证码邮件。</p>
            </div>
            
            <div>
                <a href='index.php' class='btn'>进入管理后台</a>
                <a href='../index.php' class='btn'>访问论坛首页</a>
            </div>
            
            <p style='margin-top: 20px; color: #6c757d; font-size: 14px;'>
                提示：首次使用请先配置邮箱设置，否则用户无法接收验证码邮件。
            </p>
        </div>
    </body>
    </html>";
    
} catch (PDOException $e) {
    echo "<!DOCTYPE html>
    <html lang='zh-CN'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>创建管理员失败 - ML论坛</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                margin: 0; 
                padding: 0; 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                min-height: 100vh; 
            }
            .error-card { 
                background: white; 
                border-radius: 15px; 
                padding: 40px; 
                box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
                text-align: center; 
                max-width: 500px; 
                width: 90%; 
            }
            .error-icon { 
                font-size: 64px; 
                color: #dc3545; 
                margin-bottom: 20px; 
            }
            h1 { 
                color: #333; 
                margin-bottom: 20px; 
            }
            .btn { 
                display: inline-block; 
                padding: 12px 30px; 
                background: #007bff; 
                color: white; 
                text-decoration: none; 
                border-radius: 5px; 
                margin: 10px 5px; 
            }
        </style>
    </head>
    <body>
        <div class='error-card'>
            <div class='error-icon'>✗</div>
            <h1>创建管理员失败</h1>
            <p style='color: #dc3545; margin-bottom: 20px;'>错误信息: " . $e->getMessage() . "</p>
            <p>请检查数据库连接和用户表结构。</p>
            <a href='../index.php' class='btn'>返回首页</a>
        </div>
    </body>
    </html>";
}
?>