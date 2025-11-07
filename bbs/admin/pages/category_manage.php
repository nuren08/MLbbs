<?php
// 获取所有分类
$categories = $forum->getCategories();

// 处理添加分类
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = escape($_POST['name']);
    $parent_id = intval($_POST['parent_id']);
    $url = escape($_POST['url']);
    $rule_type = escape($_POST['rule_type']);
    $sort_order = intval($_POST['sort_order']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO categories (name, parent_id, url, rule_type, sort_order) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $parent_id, $url, $rule_type, $sort_order])) {
            echo '<script>showMessage("分类添加成功"); setTimeout(() => window.location.reload(), 1000);</script>';
        }
    } catch (PDOException $e) {
        echo '<script>showMessage("添加失败: ' . $e->getMessage() . '", "error");</script>';
    }
}

// 处理删除分类
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    try {
        // 检查是否有子分类
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
        $stmt->execute([$delete_id]);
        $child_count = $stmt->fetchColumn();
        
        if ($child_count > 0) {
            echo '<script>showMessage("请先删除该分类下的子分类", "error");</script>';
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            if ($stmt->execute([$delete_id])) {
                echo '<script>showMessage("分类删除成功"); setTimeout(() => window.location.reload(), 1000);</script>';
            }
        }
    } catch (PDOException $e) {
        echo '<script>showMessage("删除失败: ' . $e->getMessage() . '", "error");</script>';
    }
}
?>

<div class="page-header">
    <h2>导航板块管理</h2>
    <button class="btn btn-primary" onclick="toggleAddForm()">
        <i class="fas fa-plus"></i> 添加分类
    </button>
</div>

<!-- 添加分类表单 -->
<div id="addCategoryForm" class="form-card" style="display: none;">
    <h3>添加新分类</h3>
    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>分类名称 *</label>
                <input type="text" name="name" required>
            </div>
            
            <div class="form-group">
                <label>父级分类</label>
                <select name="parent_id">
                    <option value="0">作为一级分类</option>
                    <?php foreach ($categories as $cat): ?>
                        <?php if ($cat['parent_id'] == 0): ?>
                            <option value="<?= $cat['id'] ?>"><?= $cat['name'] ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>链接地址</label>
                <input type="text" name="url" placeholder="可选，如：/bbs/">
            </div>
            
            <div class="form-group">
                <label>规则类型 *</label>
                <select name="rule_type" required>
                    <option value="general">通用规则</option>
                    <option value="treehole">树洞规则</option>
                    <option value="promotion">推广规则</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>排序序号</label>
                <input type="number" name="sort_order" value="0">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="toggleAddForm()">取消</button>
            <button type="submit" name="add_category" class="btn btn-primary">添加分类</button>
        </div>
    </form>
</div>

<!-- 分类列表 -->
<div class="card">
    <h3>分类列表</h3>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>分类名称</th>
                    <th>父级分类</th>
                    <th>规则类型</th>
                    <th>排序</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <?php if ($cat['parent_id'] == 0): ?>
                        <!-- 一级分类 -->
                        <tr class="parent-category">
                            <td><?= $cat['id'] ?></td>
                            <td>
                                <strong><?= $cat['name'] ?></strong>
                                <?php if ($cat['url']): ?>
                                    <br><small class="text-muted"><?= $cat['url'] ?></small>
                                <?php endif; ?>
                            </td>
                            <td>-</td>
                            <td>
                                <span class="badge badge-<?= $cat['rule_type'] ?>">
                                    <?= $cat['rule_type'] ?>
                                </span>
                            </td>
                            <td><?= $cat['sort_order'] ?></td>
                            <td>
                                <span class="status status-active">启用</span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?page=category_edit&id=<?= $cat['id'] ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="deleteCategory(<?= $cat['id'] ?>, '<?= $cat['name'] ?>')" 
                                            class="btn btn-sm btn-danger"
                                            <?= !empty($cat['children']) ? 'disabled' : '' ?>>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- 二级分类 -->
                        <?php if (!empty($cat['children'])): ?>
                            <?php foreach ($cat['children'] as $child): ?>
                                <tr class="child-category">
                                    <td><?= $child['id'] ?></td>
                                    <td style="padding-left: 30px;">
                                        ↳ <?= $child['name'] ?>
                                        <?php if ($child['url']): ?>
                                            <br><small class="text-muted"><?= $child['url'] ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $cat['name'] ?></td>
                                    <td>
                                        <span class="badge badge-<?= $child['rule_type'] ?>">
                                            <?= $child['rule_type'] ?>
                                        </span>
                                    </td>
                                    <td><?= $child['sort_order'] ?></td>
                                    <td>
                                        <span class="status status-active">启用</span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?page=category_edit&id=<?= $child['id'] ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button onclick="deleteCategory(<?= $child['id'] ?>, '<?= $child['name'] ?>')" 
                                                    class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleAddForm() {
    const form = document.getElementById('addCategoryForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function deleteCategory(id, name) {
    if (confirm(`确定要删除分类 "${name}" 吗？此操作不可恢复！`)) {
        window.location.href = `?page=category_manage&delete_id=${id}`;
    }
}
</script>

<style>
.parent-category {
    background-color: #f8f9fa;
    font-weight: bold;
}

.child-category {
    background-color: white;
}

.child-category td:first-child {
    border-left: 3px solid #007bff;
}

.badge-general { background: #28a745; }
.badge-treehole { background: #ffc107; color: #000; }
.badge-promotion { background: #dc3545; }

.action-buttons {
    display: flex;
    gap: 5px;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>