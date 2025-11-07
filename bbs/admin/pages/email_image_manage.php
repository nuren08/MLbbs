<?php
// 获取当前邮件图片配置
$settings = getAdminSettings();
$image1_url = $settings['email_image1_url'] ?? '';
$image2_url = $settings['email_image2_url'] ?? '';

// 处理图片配置更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_email_images'])) {
    $updates = [
        'email_image1_url' => escape($_POST['email_image1_url']),
        'email_image2_url' => escape($_POST['email_image2_url'])
    ];
    
    if (updateAdminSettings($updates)) {
        echo '<script>showMessage("邮件图片配置更新成功");</script>';
    } else {
        echo '<script>showMessage("更新失败", "error");</script>';
    }
}

// 处理图片上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['email_image'])) {
    $image_type = escape($_POST['image_type']); // image1 或 image2
    $upload_dir = __DIR__ . '/../../uploads/email_images/';
    
    // 创建上传目录
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file = $_FILES['email_image'];
    $file_name = 'email_' . $image_type . '_' . time() . '_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_path = $upload_dir . $file_name;
    
    // 检查文件类型
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        echo '<script>showMessage("只允许上传 JPG, PNG, GIF 格式的图片", "error");</script>';
    } elseif ($file['size'] > 2 * 1024 * 1024) { // 2MB
        echo '<script>showMessage("图片大小不能超过 2MB", "error");</script>';
    } elseif (move_uploaded_file($file['tmp_name'], $file_path)) {
        // 更新图片URL
        $web_path = BASE_PATH . '/uploads/email_images/' . $file_name;
        $setting_key = 'email_image' . ($image_type == 'image1' ? '1' : '2') . '_url';
        
        if (setSystemSetting($setting_key, $web_path)) {
            echo '<script>showMessage("图片上传成功"); setTimeout(() => window.location.reload(), 1000);</script>';
        } else {
            echo '<script>showMessage("配置更新失败", "error");</script>';
        }
    } else {
        echo '<script>showMessage("图片上传失败", "error");</script>';
    }
}
?>

<div class="page-header">
    <h2>邮件图片管理</h2>
    <p class="page-description">管理邮件中自动添加的图片，图片1显示在邮件正文前，图片2显示在邮件正文后</p>
</div>

<div class="email-images-container">
    <!-- 图片1配置 -->
    <div class="image-config-card">
        <div class="image-config-header">
            <h3><i class="fas fa-image"></i> 邮件头部图片</h3>
            <span class="badge badge-primary">图片1</span>
        </div>
        
        <div class="image-preview">
            <?php if (!empty($image1_url)): ?>
                <img src="<?= $image1_url ?>" alt="邮件头部图片" onerror="this.style.display='none'">
                <div class="image-actions">
                    <a href="<?= $image1_url ?>" target="_blank" class="btn btn-sm btn-info">
                        <i class="fas fa-external-link-alt"></i> 查看原图
                    </a>
                    <button onclick="removeImage('image1')" class="btn btn-sm btn-danger">
                        <i class="fas fa-trash"></i> 删除
                    </button>
                </div>
            <?php else: ?>
                <div class="no-image">
                    <i class="fas fa-image fa-3x"></i>
                    <p>未设置图片</p>
                </div>
            <?php endif; ?>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="image-upload-form">
            <input type="hidden" name="image_type" value="image1">
            <div class="form-group">
                <label>上传新图片</label>
                <input type="file" name="email_image" accept="image/*" required>
                <small class="form-text">建议尺寸：600x200像素，大小不超过2MB</small>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload"></i> 上传图片
            </button>
        </form>
        
        <form method="POST" class="image-url-form">
            <div class="form-group">
                <label>或使用网络图片URL</label>
                <input type="url" name="email_image1_url" value="<?= $image1_url ?>" 
                       placeholder="https://example.com/image.jpg">
            </div>
            <button type="submit" name="update_email_images" class="btn btn-secondary">
                <i class="fas fa-save"></i> 保存URL
            </button>
        </form>
    </div>

    <!-- 图片2配置 -->
    <div class="image-config-card">
        <div class="image-config-header">
            <h3><i class="fas fa-image"></i> 邮件尾部图片</h3>
            <span class="badge badge-secondary">图片2</span>
        </div>
        
        <div class="image-preview">
            <?php if (!empty($image2_url)): ?>
                <img src="<?= $image2_url ?>" alt="邮件尾部图片" onerror="this.style.display='none'">
                <div class="image-actions">
                    <a href="<?= $image2_url ?>" target="_blank" class="btn btn-sm btn-info">
                        <i class="fas fa-external-link-alt"></i> 查看原图
                    </a>
                    <button onclick="removeImage('image2')" class="btn btn-sm btn-danger">
                        <i class="fas fa-trash"></i> 删除
                    </button>
                </div>
            <?php else: ?>
                <div class="no-image">
                    <i class="fas fa-image fa-3x"></i>
                    <p>未设置图片</p>
                </div>
            <?php endif; ?>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="image-upload-form">
            <input type="hidden" name="image_type" value="image2">
            <div class="form-group">
                <label>上传新图片</label>
                <input type="file" name="email_image" accept="image/*" required>
                <small class="form-text">建议尺寸：600x200像素，大小不超过2MB</small>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload"></i> 上传图片
            </button>
        </form>
        
        <form method="POST" class="image-url-form">
            <div class="form-group">
                <label>或使用网络图片URL</label>
                <input type="url" name="email_image2_url" value="<?= $image2_url ?>" 
                       placeholder="https://example.com/image.jpg">
            </div>
            <button type="submit" name="update_email_images" class="btn btn-secondary">
                <i class="fas fa-save"></i> 保存URL
            </button>
        </form>
    </div>
</div>

<!-- 邮件预览 -->
<div class="card">
    <h3>邮件预览效果</h3>
    <div class="email-preview">
        <div class="email-header">
            <strong>发件人：</strong>ML论坛 &lt;noreply@mlbbs.com&gt;<br>
            <strong>收件人：</strong>user@example.com<br>
            <strong>主题：</strong>ML论坛验证码
        </div>
        
        <div class="email-body">
            <?php if (!empty($image1_url)): ?>
                <div class="email-image">
                    <img src="<?= $image1_url ?>" alt="邮件头部图片" style="max-width: 100%;">
                </div>
            <?php endif; ?>
            
            <div class="email-content">
                <p>亲爱的ML论坛会员，</p>
                <p>您本次的验证码为：<strong style="color: #e74c3c; font-size: 18px;">1234</strong></p>
                <p>5分钟内有效，如非本人操作，请您忽略。</p>
                <p>[ML论坛]</p>
            </div>
            
            <?php if (!empty($image2_url)): ?>
                <div class="email-image">
                    <img src="<?= $image2_url ?>" alt="邮件尾部图片" style="max-width: 100%;">
                </div>
            <?php endif; ?>
        </div>
        
        <div class="email-footer">
            <p><small>这是一封系统自动发送的邮件，请勿回复。</small></p>
        </div>
    </div>
</div>

<!-- 使用说明 -->
<div class="card">
    <h3>使用说明</h3>
    <div class="usage-guide">
        <div class="guide-item">
            <h4><i class="fas fa-info-circle"></i> 图片用途</h4>
            <ul>
                <li><strong>图片1</strong>：显示在邮件正文内容之前，通常用于品牌标识或横幅广告</li>
                <li><strong>图片2</strong>：显示在邮件正文内容之后，通常用于页脚信息或推广内容</li>
            </ul>
        </div>
        
        <div class="guide-item">
            <h4><i class="fas fa-cog"></i> 配置说明</h4>
            <ul>
                <li>支持上传本地图片或使用网络图片URL</li>
                <li>建议图片宽度为600像素，高度自适应</li>
                <li>图片格式支持：JPG、PNG、GIF</li>
                <li>图片大小限制：2MB</li>
                <li>上传的图片将保存在 <code>/bbs/uploads/email_images/</code> 目录</li>
            </ul>
        </div>
        
        <div class="guide-item">
            <h4><i class="fas fa-envelope"></i> 应用范围</h4>
            <ul>
                <li>系统自动发送的验证码邮件</li>
                <li>管理员群发的通知邮件</li>
                <li>密码重置邮件</li>
                <li>其他系统通知邮件</li>
            </ul>
        </div>
    </div>
</div>

<script>
function removeImage(imageType) {
    if (confirm('确定要删除这个图片吗？')) {
        const settingKey = 'email_image' + (imageType === 'image1' ? '1' : '2') + '_url';
        
        fetch('<?= BASE_PATH ?>/ajax/update_setting.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `setting_key=${settingKey}&setting_value=`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('图片删除成功');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showMessage('删除失败: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showMessage('网络错误: ' + error, 'error');
        });
    }
}

// 图片上传预览
document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = input.closest('.image-config-card').querySelector('.image-preview');
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="预览图片" style="max-width: 100%; max-height: 150px;">
                    <div class="image-actions">
                        <span class="file-info">${file.name} (${(file.size / 1024).toFixed(1)}KB)</span>
                    </div>
                `;
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>

<style>
.email-images-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.image-config-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.image-config-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
}

.image-config-header h3 {
    margin: 0;
    color: #333;
}

.image-preview {
    text-align: center;
    margin-bottom: 15px;
    padding: 15px;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    min-height: 150px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.image-preview img {
    max-width: 100%;
    max-height: 150px;
    margin-bottom: 10px;
}

.image-actions {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.no-image {
    color: #6c757d;
    text-align: center;
}

.no-image i {
    margin-bottom: 10px;
    color: #dee2e6;
}

.image-upload-form, .image-url-form {
    margin-bottom: 15px;
}

.file-info {
    font-size: 12px;
    color: #6c757d;
}

.email-preview {
    border: 1px solid #dee2e6;
    border-radius: 5px;
    overflow: hidden;
}

.email-header {
    background: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid #dee2e6;
    font-size: 14px;
}

.email-body {
    padding: 20px;
}

.email-image {
    margin: 15px 0;
    text-align: center;
}

.email-content {
    line-height: 1.6;
    color: #333;
}

.email-footer {
    background: #f8f9fa;
    padding: 10px 15px;
    border-top: 1px solid #dee2e6;
    text-align: center;
    color: #6c757d;
}

.usage-guide {
    display: grid;
    gap: 20px;
}

.guide-item h4 {
    color: #495057;
    margin-bottom: 10px;
}

.guide-item ul {
    margin: 0;
    color: #555;
}

.guide-item li {
    margin-bottom: 5px;
}

@media (max-width: 768px) {
    .email-images-container {
        grid-template-columns: 1fr;
    }
}
</style>