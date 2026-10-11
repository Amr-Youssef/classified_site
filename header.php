<?php
/*
 * صفحة رأس الموقع (Header)
 * تحتوي هذه الصفحة على ترويسة الموقع وعناصر الملاحة الرئيسية.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Helper function to check if the current user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// تحديد ما إذا كانت الصفحة الحالية هي الصفحة الرئيسية
$__is_homepage = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php';

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سوق الإعلانات المبوبة</title>
    <script>
    // Apply saved theme early to avoid flash
    (function(){
        try {
            var saved = localStorage.getItem('theme');
            var prefersLight = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches;
            var theme = saved === 'light' || saved === 'dark' ? saved : (prefersLight ? 'light' : 'dark');
            var root = document.documentElement;
            root.classList.add(theme === 'light' ? 'theme-light' : 'theme-dark');
            // Set theme-color for mobile UI
            var meta = document.querySelector('meta[name="theme-color"]');
            if (!meta) {
                meta = document.createElement('meta');
                meta.setAttribute('name','theme-color');
                document.head.appendChild(meta);
            }
            meta.setAttribute('content', theme === 'light' ? '#ffffff' : '#0b1a2a');
        } catch (e) {}
    })();
    </script>
    <!-- App icons & PWA manifest -->
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/icon-32.png?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/icon-16.png?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/apple-touch-icon-180.png?v=3">
    <link rel="icon" href="favicon.ico?v=3" sizes="any">
    <!-- Fallback to site logo (PNG) if icons missing -->
    <link rel="alternate icon" href="assets/brand/brand-logo.png?v=4" type="image/png">
    <link rel="manifest" href="manifest.webmanifest">
    <meta name="theme-color" content="#0b1a2a">
    <!-- تم استبدال ملف الأنماط القديم بملف التصميم الحديث. يحتوي
         style-modern.css على import لملف styles.css في داخله لضمان
         استمرار عمل الشبكات والتخطيطات الأساسية، بالإضافة إلى
         اللمسات العصرية. -->
    <link rel="stylesheet" href="style-modern.css">
    <style>
    /* Header redesign inspired by the mockup */
    .header-modern { position: sticky; top: 0; z-index: 1000; background: radial-gradient(900px 700px at 20% 0%, rgba(22,36,58,.70), rgba(11,26,42,.78)); border-bottom: 1px solid rgba(255,255,255,.08); transition: box-shadow .25s ease; backdrop-filter: blur(12px) saturate(160%); -webkit-backdrop-filter: blur(12px) saturate(160%); background-clip: padding-box; }
    .header-modern.scrolled { box-shadow: 0 8px 24px rgba(0,0,0,.25); }
    .header-neo-wrapper { max-width: 1100px; margin: 0 auto; padding: 18px 16px 8px; }
    /* Top bar */
    .topbar-neo { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .brand-neo { color: #ffffff; text-decoration: none; font-weight: 800; font-size: clamp(18px, 3vw, 24px); letter-spacing: .4px; text-shadow: 0 2px 10px rgba(0,0,0,.18); display: inline-flex; align-items: center; }
    .brand-neo .brand-logo { display: block; height: clamp(60px, 10vw, 120px); width: auto; object-fit: contain; }
    .nav-neo { transition: all .25s ease; }
    .nav-neo ul { list-style: none; padding: 0; margin: 0; display: flex; gap: clamp(16px, 4vw, 36px); flex-wrap: wrap; }
    .nav-neo a { color: #cfe0f5; text-decoration: none; font-weight: 600; letter-spacing: .3px; opacity: .9; }
    .nav-neo a:hover { opacity: 1; color: #ffffff; }
    .cta-neo { display: inline-flex; align-items: center; justify-content: center; min-width: 64px; height: 40px; padding: 0 14px; border-radius: 999px; background: linear-gradient(135deg, #1a58b3, #0e6bd8); color: #fff; font-weight: 700; text-decoration: none; border: 1px solid rgba(255,255,255,.18); box-shadow: 0 6px 16px rgba(14,107,216,.25); }
    .cta-neo svg { margin-inline-start: 6px; }
    /* Auth actions shown on mobile header */
    .auth-actions { display: none; align-items: center; gap: 8px; }
    .auth-actions a { display:inline-flex; align-items:center; justify-content:center; height:36px; padding:0 12px; border-radius:999px; border:1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.08); color:#fff; text-decoration:none; font-weight:700; }
    .auth-actions a.primary { background: linear-gradient(135deg, #1a58b3, #0e6bd8); border-color: rgba(255,255,255,.25); }
    /* Bottom mobile nav */
    .bottom-nav-neo { display:none; }
    .bottom-nav-neo a svg { width: 26px; height: 26px; stroke: currentColor; }
    /* Hero */
    .hero-neo { text-align: center; padding: clamp(16px, 4vw, 28px) 0 10px; }
    .hero-title-neo { color: #fff; font-weight: 800; letter-spacing: .4px; font-size: clamp(28px, 6vw, 56px); margin: 6px 0 18px; text-shadow: 0 2px 12px rgba(0,0,0,.25); }
    .hero-search-neo { max-width: 900px; margin: 0 auto; display: grid; grid-template-columns: 1fr auto auto; gap: 10px; align-items: center; }
    .hero-search-neo input[type="text"] { width: 100%; height: 60px; padding: 0 18px; border-radius: 20px; border: 1px solid rgba(255,255,255,.14); background: rgba(15,32,50,.85); color: #e6f1ff; outline: none; font-size: 18px; }
    .hero-search-neo input::placeholder { color: rgba(230,241,255,.6); }
    .hero-search-btn, .hero-plus-btn { width: 60px; height: 60px; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,.18); background: linear-gradient(135deg,#11468f,#0e6bd8); color: #fff; text-decoration: none; font-size: 26px; cursor: pointer; }
    .hero-search-btn { background: linear-gradient(135deg,#1a4fb3,#0e6bd8); }
    .hero-search-btn svg { width: 22px; height: 22px; }
        /* Mobile menu button */
        .mobile-menu-btn { display: none; width: 42px; height: 40px; border-radius: 12px; border: 1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.08); color: #fff; align-items: center; justify-content: center; cursor: pointer; font-size: 20px; }
        /* Theme toggle button: always visible */
        .theme-toggle-btn { display: inline-flex; width: 40px; height: 40px; border-radius: 12px; border: 1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.08); color: #fff; align-items: center; justify-content: center; cursor: pointer; font-size: 18px; }
        :root.theme-light .theme-toggle-btn { border-color: rgba(0,0,0,.08); background: rgba(0,0,0,.04); color: #0b1a2a; }

        @media (max-width: 720px) {
            .topbar-neo { gap: 10px; }
            /* Remove mobile dropdown completely */
            .mobile-menu-btn { display: none; }
            .theme-toggle-btn { width: 34px; height: 34px; font-size: 16px; }
            .nav-neo, .nav-neo.open { display: none; }
            /* Optionally keep style block for reference but nav stays hidden on phones */
            .nav-neo ul { flex-direction: column; gap: 10px; }
            /* Hide auth items from the collapsible nav on phones (auth buttons live on the topbar) */
            .nav-neo li.auth-only { display: none; }
            .cta-neo { height: 38px; padding: 0 12px; }
            /* Keep the header clean on phones; use the big + button under the hero for posting */
            .cta-neo { display: none; }
            /* Ensure auth action buttons match mobile sizing and don't inflate */
            .auth-actions a { font-size: 13px; height: 34px; padding: 0 10px; white-space: nowrap; }
            /* Make input + buttons on one row with compact sizes */
            .hero-search-neo { grid-template-columns: 1fr 44px 44px; gap: 8px; }
            .hero-search-neo input[type="text"] { height: 44px; padding: 0 12px; border-radius: 12px; font-size: 16px; }
            .hero-search-btn, .hero-plus-btn { width: 44px; height: 44px; border-radius: 12px; font-size: 20px; }
            .hero-search-btn svg { width: 18px; height: 18px; }
            /* Show auth quick actions on the top bar for small screens */
            .auth-actions { display:inline-flex; }
            /* Fixed bottom navigation for primary items */
            .bottom-nav-neo { display:grid; grid-auto-flow:column; position:fixed; inset-inline:0; bottom:0; z-index:1000; background: rgba(15,32,50,.85); border-top:1px solid rgba(255,255,255,.12); backdrop-filter: blur(10px) saturate(140%); -webkit-backdrop-filter: blur(10px) saturate(140%); padding-bottom: max(0px, env(safe-area-inset-bottom)); }
            .bottom-nav-neo a { display:flex; align-items:center; justify-content:center; height:56px; padding: 8px 6px; color:#cfe0f5; font-weight:700; text-decoration:none; }
            .bottom-nav-neo a:hover { color:#fff; }
            body.modern-body { padding-bottom: calc(64px + env(safe-area-inset-bottom)); }
        }

        /* Dismissible warning banner */
        .alert-modern-warning { background:#fff7e6; color:#8a5200; border-bottom:1px solid #ffd591; padding:10px 16px; text-align:center; font-weight:600; position: relative; }
        .alert-modern-warning a { color:#ad6800; text-decoration: underline; }
        .alert-modern-warning .alert-close { position:absolute; top:8px; inset-inline-start:8px; background:transparent; border:none; color:#8a5200; font-size:20px; line-height:1; cursor:pointer; }

    /* Light mode header text overrides */
    :root.theme-light .brand-neo { color: #0b1a2a; }
    :root.theme-light .nav-neo a { color: #375a8c; }
    :root.theme-light .nav-neo a:hover { color: #0b1a2a; }
    </style>
</head>
<body class="modern-body">
    <!-- تم إضافة class "header-modern" لإضفاء مظهر زجاجي على رأس الصفحة -->
    <header class="header-modern">
        <div class="header-neo-wrapper">
            <div class="topbar-neo">
                <a href="index.php" class="brand-neo" aria-label="الذهاب إلى الرئيسية">
                    <!-- Use PNG logo when available with JPG fallback; update v param after replacing file -->
                    <picture>
                        <source type="image/png" srcset="assets/brand/brand-logo.png?v=4">
                        <img src="logo-najd.jpg?v=4" alt="حراج نجد الجزيرة" class="brand-logo">
                    </picture>
                </a>
                <button class="mobile-menu-btn" id="menuToggle" aria-label="قائمة">☰</button>
                <!-- Theme toggle (always visible) -->
                <button id="themeToggle" class="theme-toggle-btn" aria-label="تبديل الوضع" title="تبديل الوضع">🌓</button>
                <nav class="nav-neo" id="mainNav" aria-label="التنقل الرئيسي">
                    <ul>
                        <li><a href="index.php">الرئيسية</a></li>
                        <li><a href="browse.php">تصفح الأقسام</a></li>
                        <?php if (is_logged_in()): ?>
                            <li><a href="messages.php">المحادثات</a></li>
                            <li><a href="notifications.php">الإشعارات</a></li>
                            <li><a href="profile.php">حسابي</a></li>
                            <?php $uid = $_SESSION['user_id'] ?? 0; if (is_admin_user($uid)) { echo '<li><a href="admin.php">لوحة الإدارة</a></li>'; } ?>
                            <li class="auth-only"><a href="logout.php">خروج</a></li>
                        <?php else: ?>
                            <li class="auth-only"><a href="login.php">تسجيل الدخول</a></li>
                            <li class="auth-only"><a href="register.php">إنشاء حساب</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <!-- Mobile-only auth quick actions -->
                <div class="auth-actions" aria-label="إجراءات الحساب على الجوال">
                    <?php if (is_logged_in()): ?>
                        <a href="logout.php" class="primary">خروج</a>
                    <?php else: ?>
                        <a href="login.php">تسجيل الدخول</a>
                        <a href="register.php" class="primary">إنشاء حساب</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="hero-neo">
                <form class="hero-search-neo" action="search.php" method="GET">
                    <input type="text" name="keyword" placeholder="ابحث عن المنتجات أو الأقسام" aria-label="ابحث" />
                    <button type="submit" class="hero-search-btn" aria-label="بدء البحث">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>
                    <a href="add_ad.php" class="hero-plus-btn" title="إضافة إعلان">+</a>
                </form>
            </div>
        </div>
    </header>
    <!-- Bottom navigation for small screens -->
    <nav class="bottom-nav-neo" aria-label="التنقل السفلي للجوال">
        <?php if (is_logged_in()): ?>
            <a href="index.php" title="الرئيسية" aria-label="الرئيسية">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 10.5L12 3l9 7.5"></path>
                    <path d="M5 21V9.5l7-5 7 5V21"></path>
                </svg>
            </a>
            <a href="browse.php" title="الأقسام" aria-label="الأقسام">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                </svg>
            </a>
            <a href="messages.php" title="المحادثات" aria-label="المحادثات">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path>
                </svg>
            </a>
            <a href="notifications.php" title="الإشعارات" aria-label="الإشعارات">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
            </a>
            <a href="profile.php" title="حسابي" aria-label="حسابي">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </a>
            <?php $uid = $_SESSION['user_id'] ?? 0; if (is_admin_user($uid)) { echo '<a href="admin.php" title="الإدارة" aria-label="الإدارة"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V22a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H2a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0A1.65 1.65 0 0 0 9 4.09V4a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82h0A1.65 1.65 0 0 0 22 12a2 2 0 1 1-2.6 3z"></path></svg></a>'; } ?>
        <?php else: ?>
            <a href="index.php" title="الرئيسية" aria-label="الرئيسية">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 10.5L12 3l9 7.5"></path>
                    <path d="M5 21V9.5l7-5 7 5V21"></path>
                </svg>
            </a>
            <a href="browse.php" title="الأقسام" aria-label="الأقسام">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                    <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                    <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                </svg>
            </a>
        <?php endif; ?>
    </nav>
        <?php if (isset($_SESSION['user_id'])): ?>
        <?php
            $__u = get_user_by_id($_SESSION['user_id']);
            if ($__u && empty($__u['phone_verified'])):
        ?>
        <div class="alert-modern-warning" role="status">
            لم يتم توثيق رقم جوالك بعد. <a href="verify_phone.php">اضغط هنا للتحقق الآن</a>.
            <button class="alert-close" aria-label="إغلاق التنبيه">×</button>
        </div>
        <?php endif; ?>
        <?php if ($__u && empty($__u['email_verified'])): ?>
        <div class="alert-modern-warning" role="status">
            بريدك غير مفعّل بعد. تفقد بريدك أو <a href="profile.php">اطلب رابط تفعيل جديد</a>.
            <button class="alert-close" aria-label="إغلاق التنبيه">×</button>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <script>
        // Mobile nav toggle
        (function(){
            var btn = document.getElementById('menuToggle');
            var nav = document.getElementById('mainNav');
            if (btn && nav) {
                btn.addEventListener('click', function(){
                    nav.classList.toggle('open');
                });
            }
        })();
        // Header shadow on scroll
        (function(){
            var header = document.querySelector('header.header-modern');
            if (!header) return;
            var onScroll = function(){
                if (window.scrollY > 8) header.classList.add('scrolled');
                else header.classList.remove('scrolled');
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        })();
        // Dismiss alert banner
        (function(){
            var closeBtn = document.querySelector('.alert-modern-warning .alert-close');
            var alertBar = document.querySelector('.alert-modern-warning');
            if (closeBtn && alertBar) {
                closeBtn.addEventListener('click', function(){
                    alertBar.style.display = 'none';
                });
            }
        })();
        </script>
        <script>
        // Theme toggle behavior
        (function(){
            var btn = document.getElementById('themeToggle');
            if (!btn) return;
            var root = document.documentElement;
            function currentTheme(){ return root.classList.contains('theme-light') ? 'light' : 'dark'; }
            function setTheme(t){
                root.classList.remove('theme-light','theme-dark');
                root.classList.add(t === 'light' ? 'theme-light' : 'theme-dark');
                try { localStorage.setItem('theme', t); } catch(e) {}
                var meta = document.querySelector('meta[name="theme-color"]');
                if (meta) meta.setAttribute('content', t === 'light' ? '#ffffff' : '#0b1a2a');
                btn.setAttribute('aria-pressed', t === 'light' ? 'true' : 'false');
                btn.textContent = t === 'light' ? '🌙' : '☀️';
                btn.title = t === 'light' ? 'وضع داكن' : 'وضع فاتح';
            }
            // Initialize icon
            setTheme(currentTheme());
            btn.addEventListener('click', function(){
                var next = currentTheme() === 'light' ? 'dark' : 'light';
                setTheme(next);
            });
        })();
        </script>
    <main class="container">