<?php
// 获取广告列表
$ads = $pdo->query("SELECT * FROM ads ORDER BY sort_order ASC")->fetchAll();

// 获取广告显示设置
$ad_display = getSystemSetting('ad_display', '1');

// 处理添加广告
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ad'])) {
    $image_url = escape($_POST['image_url']);
    $url = escape($_POST['url']);
    $sort_order = intval($_POST['sort_order']);
    $status = isset($_POST['status']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO ads (image_url, url, sort_order, status) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$image_url, $url, $sort_order, $status])) {
            echo '<script>showMessage("广告添加成功"); setTimeout(() => window.location.reload(), 1000);</script>';
        }
    } catch (PDOException $e) {
        echo '<script>showMessage("添加失败: ' . $e->getMessage() . '", "error");</script>';
    }
}

// 处理删除广告
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM ads WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            echo '<script>showMessage("广告删除成功"); setTimeout(() => window.location.reload(), 1000);</script>';
        }
    } catch (PDOException $e) {
        echo '<script>showMessage("删除失败: ' . $e->getMessage() . '", "error");</script>';
    }
}

// 处理切换广告状态
if (isset($_GET['toggle_ad_status'])) {
    $ad_id = intval($_GET['toggle_ad_status']);
    try {
        $stmt = $pdo->prepare("SELECT status FROM ads WHERE id = ?");
        $stmt->execute([$ad_id]);
        $ad = $stmt->fetch();
        
        $new_status = $ad['status'] == 1 ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE ads SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $ad_id])) {
            echo '<script>showMessage("状态更新成功"); setTimeout(() => window.location.reload(), 1000);</script>';
        }
    } catch (PDOException $e) {
        echo '<script>showMessage("操作失败: ' . $e->getMessage() . '", "error");</script>';
    }
}
?>

<div class="page-header">
    <h2>广告管理</h2>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="toggleAdForm()">
            <i class="fas fa-plus"></i> 添加广告
        </button>
        <form method="POST" class="toggle-form">
            <input type="hidden" name="toggle_ad_display" value="1">
            <button type="submit" class="btn <?= $ad_display == '1' ? 'btn-success' : 'btn-secondary' ?>">
                <i class="fas fa-ad"></i>
                <?= $ad_display == '1' ? '关闭广告' : '开启广告' ?>
            </button>
        </form>
    </div>
</div>

<!-- 添加广告表单 -->
<div id="adForm" class="form-card" style="display: none;">
    <h3>添加新广告</h3>
    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>广告图片URL *</label>
                <input type="url" name="image_url" required placeholder="https://example.com/image.jpg">
                <small class="form-text">建议图片尺寸：728x90 像素</small>
            </div>
            
            <div class="form-group">
                <label>跳转链接 *</label>
                <input type="url" name="url" required placeholder="https://example.com">
            </div>
            
            <div class="form-group">
                <label>排序序号</label>
                <input type="number" name="sort_order" value="0" min="0">
                <small class="form-text">数字越小排序越靠前</small>
            </div>
        </div>
        
        <div class="form-check">
            <input type="checkbox" name="status" id="ad_status" class="form-check-input" checked>
            <label for="ad_status" class="form-check-label">立即启用</label>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="toggleAdForm()">取消</button>
            <button type="submit" name="add_ad" class="btn btn-primary">添加广告</button>
        </div>
    </form>
</div>

<!-- 广告列表 -->
<div class="card">
    <h3>广告轮播列表 (<?= count($ads) ?>)</h3>
    
    <?php if (empty($ads)): ?>
        <div class="no-data">暂无广告</div>
    <?php else: ?>
        <div class="ads-grid">
            <?php foreach ($ads as $ad): ?>
                <div class="ad-card <?= $ad['status'] == 0 ? 'ad-disabled' : '' ?>">
                    <div class="ad-image">
                        <img src="<?= $ad['image_url'] ?>" alt="广告图片" onerror="this.src='<?= BASE_PATH ?>/assets/images/logo_ml.png'">
                    </div>
                    <div class="ad-info">
                        <div class="ad-url">
                            <strong>跳转链接：</strong>
                            <a href="<?= $ad['url'] ?>" target="_blank" title="<?= $ad['url'] ?>">
                                <?= mb_substr($ad['url'], 0, 30) ?>
                                <?= mb_strlen($ad['url']) > 30 ? '...' : '' ?>
                            </a>
                        </div>
                        <div class="ad-meta">
                            <span class="sort-order">排序: <?= $ad['sort_order'] ?></span>
                            <span class="status <?= $ad['status'] == 1 ? 'status-active' : 'status-inactive' ?>">
                                <?= $ad['status'] == 1 ? '启用' : '禁用' ?>
                            </span>
                        </div>
                    </div>
                    <div class="ad-actions">
                        <button onclick="toggleAdStatus(<?= $ad['id'] ?>)" 
                                class="btn btn-sm <?= $ad['status'] == 1 ? 'btn-warning' : 'btn-success' ?>"
                                title="<?= $ad['status'] == 1 ? '禁用' : '启用' ?>">
                            <i class="fas <?= $ad['status'] == 1 ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                        </button>
                        <button onclick="editAd(<?= $ad['id'] ?>)" 
                                class="btn btn-sm btn-info" title="编辑">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteAd(<?= $ad['id'] ?>)" 
                                class="btn btn-sm btn-danger" title="删除">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- 广告预览 -->
<div class="card">
    <h3>广告预览</h3>
    <div class="ad-preview">
        <div class="ad-banner">
            <?php 
            $active_ads = array_filter($ads, function($ad) {
                return $ad['status'] == 1;
            });
            
            if (!empty($active_ads)): 
                usort($active_ads, function($a, $b) {
                    return $a['sort_order'] - $b['sort_order'];
                });
                $first_ad = $active_ads[0];
            ?>
                <a href="<?= $first_ad['url'] ?>" target="_blank">
                    <img src="<?= $first_ad['image_url'] ?>" alt="广告预览" onerror="this.src='<?= BASE_PATH ?>/assets/images/logo_ml.png'">
                </a>
                <div class="ad-preview-info">
                    <span>横幅广告预览 (728x90)</span>
                </div>
            <?php else: ?>
                <div class="no-ad-preview">
                    <p>暂无启用的广告</p>
                    <small>添加并启用广告后，这里会显示预览效果</small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleAdForm() {
    const form = document.getElementById('adForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function toggleAdStatus(id) {
    if (confirm('确定要切换这个广告的启用状态吗？')) {
        window.location.href = '?page=ad_manage&toggle_ad_status=' + id;
    }
}

function editAd(id) {
    // 这里可以跳转到编辑页面或打开编辑模态框
    alert('编辑功能开发中...');
}

function deleteAd(id) {
    if (confirm('确定要删除这个广告吗？此操作不可恢复！')) {
        window.location.href = '?page=ad_manage&delete_id=' + id;
    }
}
</script>

<style>
.ads-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.ad-card {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    background: white;
}

.ad-disabled {
    opacity: 0.6;
    background: #f8f9fa;
}

.ad-image {
    height: 120px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
}

.ad-image img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.ad-info {
    padding: 15px;
}

.ad-url {
    margin-bottom: 10px;
    word-break: break-all;
}

.ad-url a {
    color: #007bff;
    text-decoration: none;
}

.ad-url a:hover {
    text-decoration: underline;
}

.ad-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: #6c757d;
}

.ad-actions {
    padding: 10px 15px;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    display: flex;
    gap: 5px;
    justify-content: flex-end;
}

.ad-preview {
    text-align: center;
}

.ad-banner {
    display: inline-block;
    border: 2px dashed #dee2e6;
    padding: 20px;
    background: #f8f9fa;
    margin-bottom: 15px;
}

.ad-banner img {
    max-width: 728px;
    max-height: 90px;
    border: 1px solid #ddd;
}

.ad-preview-info {
    margin-top: 10px;
    color: #6c757d;
    font-size: 14px;
}

.no-ad-preview {
    padding: 40px;
    color: #6c757d;
    text-align: center;
}

.toggle-form {
    display: inline;
}

@media (max-width: 768px) {
    .ads-grid {
        grid-template-columns: 1fr;
    }
    
    .ad-banner img {
        max-width: 100%;
    }
}
</style>

<?php
// 处理广告显示开关
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_ad_display'])) {
    $new_value = $ad_display == '1' ? '0' : '1';
    if (setSystemSetting('ad_display', $new_value)) {
        echo '<script>showMessage("广告显示设置已更新"); setTimeout(() => window.location.reload(), 1000);</script>';
    }
}
?>