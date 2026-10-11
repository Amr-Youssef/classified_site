<?php
/*
 * تذييل الموقع (Footer)
 */
?>
    </main>
    <style>
    /* Sticky footer: ensure footer sits at the bottom on short pages */
    body.modern-body { min-height: 100vh; display: flex; flex-direction: column; }
    main.container { flex: 1 0 auto; }
    .footer-modern { margin-top: auto; flex-shrink: 0; }
    @media (max-width: 720px) {
        /* Give a bit more breathing room above the fixed bottom nav on phones */
        .footer-modern { padding-bottom: calc(10px + env(safe-area-inset-bottom)); }
    }
    </style>
    <!-- تم إضافة class "footer-modern" لإضفاء مظهر زجاجي على التذييل -->
    <footer class="footer-modern">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h4 class="footer-brand">حراج نجد الجزيرة</h4>
                    <p class="footer-desc">منصة سهلة للبحث ونشر الإعلانات في مختلف الأقسام مع تجربة سريعة ومتجاوبة.</p>
                    <ul class="social-links" aria-label="روابط التواصل">
                        <li><a href="#" aria-label="Twitter" title="Twitter" rel="noopener"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M22.46 6c-.77.35-1.6.58-2.46.69a4.28 4.28 0 0 0 1.88-2.37 8.56 8.56 0 0 1-2.71 1.04 4.27 4.27 0 0 0-7.27 3.89A12.12 12.12 0 0 1 3.16 4.9a4.26 4.26 0 0 0 1.32 5.7 4.23 4.23 0 0 1-1.93-.53v.05a4.27 4.27 0 0 0 3.43 4.19c-.47.13-.97.2-1.49.08a4.28 4.28 0 0 0 3.98 2.96A8.56 8.56 0 0 1 2 19.54a12.07 12.07 0 0 0 6.54 1.92c7.85 0 12.14-6.5 12.14-12.13 0-.18 0-.36-.01-.54A8.67 8.67 0 0 0 24 5.5a8.48 8.48 0 0 1-2.46.68z"/></svg></a></li>
                        <li><a href="#" aria-label="Facebook" title="Facebook" rel="noopener"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M22 12.06C22 6.48 17.52 2 11.94 2S2 6.48 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.9h2.54V9.41c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.47h-1.26c-1.24 0-1.62.77-1.62 1.56v1.87h2.77l-.44 2.9h-2.33V22c4.78-.76 8.44-4.92 8.44-9.94z"/></svg></a></li>
                        <li><a href="#" aria-label="Instagram" title="Instagram" rel="noopener"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm5 5a5 5 0 1 0 0 10a5 5 0 0 0 0-10zm0 2.5a2.5 2.5 0 1 1 0 5a2.5 2.5 0 0 1 0-5zM18 5.5a1.25 1.25 0 1 0 0 2.5a1.25 1.25 0 0 0 0-2.5z"/></svg></a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>روابط سريعة</h4>
                    <ul class="footer-links">
                        <li><a href="index.php">الرئيسية</a></li>
                        <li><a href="browse.php">تصفح الأقسام</a></li>
                        <li><a href="add_ad.php">إضافة إعلان</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>حسابي</h4>
                    <ul class="footer-links">
                        <?php if (!empty($_SESSION['user_id'])): ?>
                            <li><a href="profile.php">الملف الشخصي</a></li>
                            <li><a href="messages.php">المحادثات</a></li>
                            <li><a href="notifications.php">الإشعارات</a></li>
                            <?php if (function_exists('is_admin_user') && is_admin_user($_SESSION['user_id'])): ?>
                                <li><a href="admin.php">لوحة الإدارة</a></li>
                            <?php endif; ?>
                            <li><a href="logout.php">تسجيل الخروج</a></li>
                        <?php else: ?>
                            <li><a href="login.php">تسجيل الدخول</a></li>
                            <li><a href="register.php">إنشاء حساب</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>
                    &copy; <?php echo date('Y'); ?> حراج نجد الجزيرة. جميع الحقوق محفوظة.
                    <?php if (defined('DESIGNER_NAME') && DESIGNER_NAME): ?>
                        <span class="designer-credit" style="margin-inline-start:10px;color:#888;">
                            تصميم:
                            <?php if (defined('DESIGNER_URL') && DESIGNER_URL): ?>
                                <a href="<?php echo htmlspecialchars(DESIGNER_URL); ?>" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline;">
                                    <?php echo htmlspecialchars(DESIGNER_NAME); ?>
                                </a>
                            <?php else: ?>
                                <?php echo htmlspecialchars(DESIGNER_NAME); ?>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </p>
                <a href="#" id="backToTop" class="back-to-top" aria-label="العودة للأعلى">▲</a>
            </div>
        </div>
    </footer>
    <script>
    (function(){
        var btn = document.getElementById('backToTop');
        if (btn) {
            btn.addEventListener('click', function(e){
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }
    })();
    </script>
</body>
</html>