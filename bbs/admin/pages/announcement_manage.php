<?php
// 获取公告列表
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$per_page = 10;

$stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM announcements ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $per_page, PDO::PARAM_INT);
$stmt->bindValue(2, ($page - 1) * $per_page, PDO::PARAM_INT);
$stmt->execute();

$announcements = $stmt->fetchAll();

$total_stmt = $pdo->query("SELECT FOUND_ROWS()");
$total_announcements = $total_stmt->fetchColumn();
$total_pages = ceil($total_announcements / $per_page);

// 处理添加公告
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_announcement'])) {
    $title = escape($_POST['title']);
    $content = escape($_POST['content']);
    $status = isset($_POST['status']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, content, status) VALUES (?, ?, ?)");
        if ($stmt->execute([$title, $content, $status])) {
            // 清除所有用户的已读记录，确保新公告对所有用户显示为未读
            $new_id = $pdo->lastInsertId();
            echo '<script>showMessage("公告发布成功"); setTimeout(() => window.location.reload(), 1000);</script>';
        }
    } catch (PDOException $e) {
        echo '<script>showMessage("发布失败: ' . $e->getMessage() . '", "error");</script>';
    }
}

// 处理删除公告
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    try {
        // 先删除相关的阅读记录
        $stmt = $pdo->prepare("DELETE FROM announcement_reads WHERE announcement_id = ?");
        $stmt->execute([$delete_id]);
        
        // 再删除公告
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            echo '<script>showMessage("公告删除成功"); setTimeout(() => window.location.reload(), 1000);</script>';
        }
    } catch (PDOException $e) {
        echo '<script>showMessage("删除失败: ' . $e->getMessage() . '", "error");</script>';
    }
}

// 处理切换公告状态
if (isset($_GET['toggle_status'])) {
    $announcement_id = intval($_GET['toggle_status']);
    try {
        $stmt = $pdo->prepare("SELECT status FROM announcements WHERE id = ?");
        $stmt->execute([$announcement_id]);
        $announcement = $stmt->fetch();
        
        $new_status = $announcement['status'] == 1 ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE announcements SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $announcement_id])) {
            echo '<script>showMessage("状态更新成功"); setTimeout(() => window.location.reload(), 1000);</script>';
        }
    } catch (PDOException $e) {
        echo '<script>showMessage("操作失败: ' . $e->getMessage() . '", "error");</script>';
    }
}

// 获取公告显示设置
$announcement_display = getSystemSetting('announcement_display', '1');
?>

<div class="page-header">
    <h2>公告管理</h2>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="toggleAnnouncementForm()">
            <i class="fas fa-plus"></i> 发布公告
        </button>
        <form method="POST" class="toggle-form">
            <input type="hidden" name="toggle_announcement_display" value="1">
            <button type="submit" class="btn <?= $announcement_display == '1' ? 'btn-success' : 'btn-secondary' ?>">
                <i class="fas fa-bullhorn"></i>
                <?= $announcement_display == '1' ? '关闭公告' : '开启公告' ?>
            </button>
        </form>
    </div>
</div>

<!-- 发布公告表单 -->
<div id="announcementForm" class="form-card" style="display: none;">
    <h3>发布新公告</h3>
    <form method="POST">
        <div class="form-group">
            <label>公告标题 *</label>
            <input type="text" name="title" required maxlength="255" placeholder="请输入公告标题">
        </div>
        
        <div class="form-group">
            <label>公告内容 *</label>
            <textarea name="content" rows="8" required placeholder="请输入公告内容"></textarea>
            <small class="form-text">支持HTML格式</small>
        </div>
        
        <div class="form-check">
            <input type="checkbox" name="status" id="announcement_status" class="form-check-input" checked>
            <label for="announcement_status" class="form-check-label">立即发布</label>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="toggleAnnouncementForm()">取消</button>
            <button type="submit" name="add_announcement" class="btn btn-primary">发布公告</button>
        </div>
    </form>
</div>

<!-- 公告列表 -->
<div class="card">
    <h3>公告列表 (<?= $total_announcements ?>)</h3>
    
    <?php if (empty($announcements)): ?>
        <div class="no-data">暂无公告</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>标题</th>
                        <th>内容预览</th>
                        <th>发布时间</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($announcements as $announcement): ?>
                        <tr>
                            <td><?= $announcement['id'] ?></td>
                            <td>
                                <strong><?= $announcement['title'] ?></strong>
                            </td>
                            <td>
                                <?= mb_substr(strip_tags($announcement['content']), 0, 50) ?>
                                <?= mb_strlen(strip_tags($announcement['content'])) > 50 ? '...' : '' ?>
                            </td>
                            <td><?= date('Y-m-d H:i', strtotime($announcement['created_at'])) ?></td>
                            <td>
                                <?php if ($announcement['status'] == 1): ?>
                                    <span class="status status-active">已发布</span>
                                <?php else: ?>
                                    <span class="status status-inactive">未发布</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick="toggleAnnouncementStatus(<?= $announcement['id'] ?>)" 
                                            class="btn btn-sm <?= $announcement['status'] == 1 ? 'btn-warning' : 'btn-success' ?>"
                                            title="<?= $announcement['status'] == 1 ? '取消发布' : '发布' ?>">
                                        <i class="fas <?= $announcement['status'] == 1 ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                                    </button>
                                    <button onclick="editAnnouncement(<?= $announcement['id'] ?>)" 
                                            class="btn btn-sm btn-info" title="编辑">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteAnnouncement(<?= $announcement['id'] ?>, '<?= $announcement['title'] ?>')" 
                                            class="btn btn-sm btn-danger" title="删除">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 分页 -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=announcement_manage&p=<?= $i ?>" 
                       class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function toggleAnnouncementForm() {
    const form = document.getElementById('announcementForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function toggleAnnouncementStatus(id) {
    if (confirm('确定要切换这个公告的发布状态吗？')) {
        window.location.href = '?page=announcement_manage&toggle_status=' + id;
    }
}

function editAnnouncement(id) {
    // 这里可以跳转到编辑页面或打开编辑模态框
    alert('编辑功能开发中...');
}

function deleteAnnouncement(id, title) {
    if (confirm(`确定要删除公告 "${title}" 吗？此操作不可恢复！`)) {
        window.location.href = '?page=announcement_manage&delete_id=' + id;
    }
}
</script>

<?php
// 处理公告显示开关
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_announcement_display'])) {
    $new_value = $announcement_display == '1' ? '0' : '1';
    if (setSystemSetting('announcement_display', $new_value)) {
        echo '<script>showMessage("公告显示设置已更新"); setTimeout(() => window.location.reload(), 1000);</script>';
    }
}
?>