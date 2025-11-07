// 管理后台JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // 侧边栏切换
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
    }
    
    // 确认对话框
    function confirmAction(message) {
        return confirm(message || '确定要执行此操作吗？');
    }
    
    // 显示消息提示
    function showMessage(message, type = 'success') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message message-${type}`;
        messageDiv.innerHTML = `
            <div class="message-content">
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}"></i>
                <span>${message}</span>
            </div>
            <button class="message-close">&times;</button>
        `;
        
        document.body.appendChild(messageDiv);
        
        // 自动消失
        setTimeout(() => {
            messageDiv.classList.add('fade-out');
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
            }, 300);
        }, 3000);
        
        // 手动关闭
        messageDiv.querySelector('.message-close').addEventListener('click', function() {
            messageDiv.classList.add('fade-out');
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
            }, 300);
        });
    }
    
    // 全局函数
    window.confirmAction = confirmAction;
    window.showMessage = showMessage;
    
    // 表单提交处理
    const forms = document.querySelectorAll('form[data-confirm]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!confirmAction(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
    
    // 表格行点击效果
    const tableRows = document.querySelectorAll('table tbody tr[data-href]');
    tableRows.forEach(row => {
        row.addEventListener('click', function() {
            window.location.href = this.getAttribute('data-href');
        });
        
        // 防止按钮点击触发跳转
        row.querySelectorAll('button, a').forEach(element => {
            element.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    });
});

// AJAX表单提交
function submitForm(form, options = {}) {
    const formData = new FormData(form);
    const url = form.getAttribute('action') || window.location.href;
    const method = form.getAttribute('method') || 'POST';
    
    return fetch(url, {
        method: method,
        body: formData,
        ...options
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (options.success) {
                options.success(data);
            } else {
                showMessage(data.message || '操作成功');
                if (data.redirect) {
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                }
            }
        } else {
            if (options.error) {
                options.error(data);
            } else {
                showMessage(data.message || '操作失败', 'error');
            }
        }
        return data;
    })
    .catch(error => {
        console.error('Error:', error);
        if (options.error) {
            options.error({success: false, message: '网络错误'});
        } else {
            showMessage('网络错误，请重试', 'error');
        }
    });
}

// 加载更多功能
function initLoadMore(container, url, params = {}) {
    let page = 1;
    const loadMoreBtn = container.querySelector('.load-more-btn');
    const loadingText = container.querySelector('.loading-text');
    
    if (!loadMoreBtn) return;
    
    loadMoreBtn.addEventListener('click', function() {
        page++;
        const button = this;
        const originalText = button.innerHTML;
        
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 加载中...';
        
        fetch(url + '?page=' + page + '&' + new URLSearchParams(params))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.html) {
                    container.querySelector('.load-more-container').insertAdjacentHTML('beforebegin', data.html);
                    
                    if (!data.hasMore) {
                        button.style.display = 'none';
                        if (loadingText) {
                            loadingText.textContent = '已加载全部内容';
                        }
                    }
                } else {
                    showMessage('加载失败', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('加载失败', 'error');
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalText;
            });
    });
}