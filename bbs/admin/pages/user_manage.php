<?php
// 获取用户列表
$page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$per_page = 20;
$search = isset($_GET['search']) ? escape($_GET['search']) : '';

$users_data = getAllUsers($page, $per_page, $search);
$users = $users_data['users'];
$total_users = $users_data['total'];
$total_pages = $users_data['pages'];

// 处理用户状态切换
if (isset($_GET['toggle_status'])) {
    $user_id = intval($_GET['toggle_status']);
    try {
        $user = $pdo->prepare("SELECT status FROM users WHERE id = ?");
        $user->execute([$user_id]);
        $user_data = $user->fetch();
        
        $new_status = $user_data['status'] == 1 ? 0 : 1;
        if (toggleUserStatus($user_id, $new_status)) {
            echo '<script>showMessage("操作成功"); setTimeout(() => window.location.reload(), 1000);</script>';
        }
    } catch (PDOException $e) {
        echo '<script>showMessage("操作失败: ' . $e->getMessage() . '", "error");</script>';
    }
}

// 处理重置密码
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $user_id = intval($_POST['user_id']);
    $new_password = escape($_POST['new_password']);
    
    if (resetUserPassword($user_id, $new_password)) {
        echo '<script>showMessage("密码重置成功");</script>';
    } else {
        echo '<script>showMessage("密码重置失败", "error");</script>';
    }
}

// 处理调整积分
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_points'])) {
    $user_id = intval($_POST['user_id']);
    $points = intval($_POST['points']);
    $add_exp = isset($_POST['add_exp']) ? 1 : 0;
    
    if (adjustUserPoints($user_id, $points, $add_exp)) {
        echo '<script>showMessage("积分调整成功");</script>';
    } else {
        echo '<script>showMessage("积分调整失败", "error");</script>';
    }
}
?>

<div class="page-header">
    <h2>用户管理</h2>
    <div class="header-actions">
        <form method="GET" class="search-form">
            <input type="hidden" name="page" value="user_manage">
            <input type="text" name="search" placeholder="搜索用户名、邮箱或昵称..." value="<?= $search ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
</div>

<div class="card">
    <h3>用户列表 (<?= $total_users ?>)</h3>
    
    <?php if (empty($users)): ?>
        <div class="no-data">暂无用户数据</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>用户名</th>
                        <th>昵称</th>
                        <th>邮箱</th>
                        <th>等级</th>
                        <th>积分</th>
                        <th>经验</th>
                        <th>注册时间</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['user_id'] ?></td>
                            <td><?= $user['username'] ?></td>
                            <td><?= $user['nickname'] ?></td>
                            <td><?= $user['email'] ?></td>
                            <td>LV<?= $user['level'] ?></td>
                            <td><?= $user['points'] ?></td>
                            <td><?= $user['exp'] ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($user['register_time'])) ?></td>
                            <td>
                                <?php if ($user['status'] == 1): ?>
                                    <span class="status status-active">正常</span>
                                <?php else: ?>
                                    <span class="status status-inactive">封禁</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick="toggleUserStatus(<?= $user['id'] ?>)" 
                                            class="btn btn-sm <?= $user['status'] == 1 ? 'btn-warning' : 'btn-success' ?>"
                                            title="<?= $user['status'] == 1 ? '封禁' : '解封' ?>">
                                        <i class="fas <?= $user['status'] == 1 ? 'fa-lock' : 'fa-unlock' ?>"></i>
                                    </button>
                                    <button onclick="showResetPassword(<?= $user['id'] ?>, '<?= $user['username'] ?>')" 
                                            class="btn btn-sm btn-info" title="重置密码">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <button onclick="showAdjustPoints(<?= $user['id'] ?>, '<?= $user['username'] ?>')" 
                                            class="btn btn-sm btn-primary" title="调整积分">
                                        <i class="fas fa-coins"></i>
                                    </button>
                                    <a href="?page=user_detail&id=<?= $user['id'] ?>" 
                                       class="btn btn-sm btn-secondary" title="详情">
                                        <i class="fas fa-info-circle"></i>
                                    </a>
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
                    <a href="?page=user_manage&p=<?= $i ?>&search=<?= urlencode($search) ?>" 
                       class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- 重置密码模态框 -->
<div id="resetPasswordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>重置密码</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST" id="resetPasswordForm">
            <input type="hidden" name="user_id" id="reset_user_id">
            <div class="modal-body">
                <p>为用户 <strong id="reset_username"></strong> 重置密码：</p>
                <div class="form-group">
                    <label>新密码</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>确认新密码</label>
                    <input type="password" name="confirm_password" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('resetPasswordModal')">取消</button>
                <button type="submit" name="reset_password" class="btn btn-primary">重置密码</button>
            </div>
        </form>
    </div>
</div>

<!-- 调整积分模态框 -->
<div id="adjustPointsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>调整积分</h3>
            <button class="modal-close">&times;</button>
        </div>
        <form method="POST" id="adjustPointsForm">
            <input type="hidden" name="user_id" id="adjust_user_id">
            <div class="modal-body">
                <p>为用户 <strong id="adjust_username"></strong> 调整积分：</p>
                <div class="form-group">
                    <label>积分调整</label>
                    <input type="number" name="points" required>
                    <small class="form-text">正数表示增加，负数表示减少</small>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="add_exp" id="add_exp" class="form-check-input">
                    <label for="add_exp" class="form-check-label">同步赠送等额经验值</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('adjustPointsModal')">取消</button>
                <button type="submit" name="adjust_points" class="btn btn-primary">确认调整</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleUserStatus(userId) {
    if (confirm('确定要切换这个用户的状态吗？')) {
        window.location.href = '?page=user_manage&toggle_status=' + userId;
    }
}

function showResetPassword(userId, username) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_username').textContent = username;
    document.getElementById('resetPasswordForm').reset();
    openModal('resetPasswordModal');
}

function showAdjustPoints(userId, username) {
    document.getElementById('adjust_user_id').value = userId;
    document.getElementById('adjust_username').textContent = username;
    document.getElementById('adjustPointsForm').reset();
    openModal('adjustPointsModal');
}

function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// 关闭模态框
document.querySelectorAll('.modal-close').forEach(button => {
    button.addEventListener('click', function() {
        this.closest('.modal').style.display = 'none';
    });
});

// 点击模态框外部关闭
window.addEventListener('click', function(event) {
    document.querySelectorAll('.modal').forEach(modal => {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    });
});

// 表单验证：重置密码确认
document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
    const password = this.querySelector('input[name="new_password"]').value;
    const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('两次输入的密码不一致！');
    }
});
</script>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 500px;
    max-width: 90%;
    animation: modalFadeIn 0.3s;
}

@keyframes modalFadeIn {
    from { opacity: 0; transform: translateY(-50px); }
    to { opacity: 1; transform: translateY(0); }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6c757d;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.form-check {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 15px;
}

.form-check-input {
    margin: 0;
}
</style>