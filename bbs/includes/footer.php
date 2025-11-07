    </main>

    <!-- 底部广告区域 -->
    <?php if (getSystemSetting('ad_display', '1') == '1'): ?>
    <div class="fixed-bottom bg-light border-top" style="z-index: 999; height: 80px;">
        <div class="container h-100">
            <div id="adCarousel" class="carousel slide h-100" data-bs-ride="carousel">
                <div class="carousel-inner h-100">
                    <?php
                    try {
                        global $pdo;
                        $stmt = $pdo->query("SELECT * FROM ads WHERE status = 1 ORDER BY sort_order LIMIT 5");
                        $ads = $stmt->fetchAll();
                        
                        if (!empty($ads)) {
                            foreach ($ads as $index => $ad) {
                                echo '<div class="carousel-item h-100 ' . ($index === 0 ? 'active' : '') . '">';
                                echo '<a href="' . escape($ad['url']) . '" target="_blank" class="d-block h-100">';
                                echo '<img src="' . escape($ad['image_url']) . '" class="d-block w-100 h-100" style="object-fit: contain;" alt="广告">';
                                echo '</a>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="carousel-item active h-100 d-flex align-items-center justify-content-center">';
                            echo '<p class="text-muted mb-0">广告位招租</p>';
                            echo '</div>';
                        }
                    } catch (Exception $e) {
                        error_log("获取广告失败: " . $e->getMessage());
                    }
                    ?>
                </div>
                <?php if (!empty($ads) && count($ads) > 1): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#adCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">上一个</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#adCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">下一个</span>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 页脚 -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>ML论坛</h5>
                    <p>分享知识，交流思想，共建美好社区</p>
                </div>
                <div class="col-md-3">
                    <h6>快速链接</h6>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo BASE_PATH; ?>/index.php" class="text-light">论坛首页</a></li>
                        <li><a href="<?php echo BASE_PATH; ?="/"; ?>" class="text-light">网站首页</a></li>
                        <li><a href="<?php echo BASE_PATH; ?>/announcement.php" class="text-light">网站公告</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6>帮助</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-light">使用指南</a></li>
                        <li><a href="#" class="text-light">联系我们</a></li>
                        <li><a href="#" class="text-light">隐私政策</a></li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> ML论坛 版权所有</p>
            </div>
        </div>
    </footer>

    <!-- 悬浮签到按钮 -->
    <?php if (isLoggedIn()): ?>
    <button class="floating-signin" id="floatingSignin" title="点击签到">
        <i class="fas fa-calendar-check"></i>
    </button>
    <?php endif; ?>

    <!-- Bootstrap & jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- 自定义JavaScript -->
    <script src="<?php echo ASSETS_PATH; ?>/js/main.js"></script>
    
    <script>
        // 悬浮按钮拖拽功能
        $(document).ready(function() {
            const signinBtn = $('#floatingSignin');
            let isDragging = false;
            let currentX, currentY, initialX, initialY, xOffset = 0, yOffset = 0;
            
            signinBtn.on('mousedown', function(e) {
                initialX = e.clientX - xOffset;
                initialY = e.clientY - yOffset;
                
                if (e.target === this) {
                    isDragging = true;
                }
            });
            
            $(document).on('mousemove', function(e) {
                if (isDragging) {
                    e.preventDefault();
                    currentX = e.clientX - initialX;
                    currentY = e.clientY - initialY;
                    
                    xOffset = currentX;
                    yOffset = currentY;
                    
                    setTranslate(currentX, currentY, signinBtn[0]);
                }
            });
            
            $(document).on('mouseup', function() {
                initialX = currentX;
                initialY = currentY;
                isDragging = false;
                
                // 保存位置到本地存储
                localStorage.setItem('signinBtnX', currentX);
                localStorage.setItem('signinBtnY', currentY);
            });
            
            function setTranslate(xPos, yPos, el) {
                el.style.transform = `translate3d(${xPos}px, ${yPos}px, 0)`;
            }
            
            // 恢复之前的位置
            const savedX = localStorage.getItem('signinBtnX');
            const savedY = localStorage.getItem('signinBtnY');
            if (savedX !== null && savedY !== null) {
                setTranslate(parseInt(savedX), parseInt(savedY), signinBtn[0]);
            }
            
            // 点击签到
            signinBtn.on('click', function() {
                window.location.href = '<?php echo BASE_PATH; ?>/signin.php';
            });
        });
        
        // 公告弹窗检查
        $(document).ready(function() {
            <?php if (isLoggedIn() && getSystemSetting('announcement_display', '1') == '1'): ?>
            $.get('<?php echo BASE_PATH; ?>/ajax/check_announcement.php', function(response) {
                if (response.has_unread) {
                    $('#announcementModal').modal('show');
                }
            });
            <?php endif; ?>
        });
    </script>

    <!-- 公告弹窗 -->
    <div class="modal fade" id="announcementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">📢 最新公告</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="announcementContent">
                    <!-- 公告内容将通过AJAX加载 -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                    <button type="button" class="btn btn-primary" id="markAsRead">标记为已读</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>