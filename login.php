<?php
// تضمين الإعدادات والدوال فقط (بدون إخراج HTML) قبل أي تحويل للرؤوس
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// إذا كان المستخدم مسجلاً بالفعل، إعادة توجيهه إلى الصفحة الرئيسية قبل أي إخراج
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$identifier = '';
$password = '';

// معالجة نموذج تسجيل الدخول
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($identifier && $password) {
        // authenticate_user سيتولى تحديد ما إذا كان البريد أم الجوال
        $user = authenticate_user($identifier, $password);
        if ($user) {
            // حفظ معرّف المستخدم وصلاحياته في الجلسة
            $_SESSION['user_id'] = $user['id'];
            // تخزين متغير is_admin في الجلسة لسهولة الوصول إليه
            $_SESSION['is_admin'] = !empty($user['is_admin']) ? (int)$user['is_admin'] : 0;
            // إذا الهاتف غير موثق، حوّل إلى صفحة التحقق
            $fullUser = get_user_by_id($user['id']);
            if ($fullUser && empty($fullUser['phone_verified'])) {
                $_SESSION['flash_success'] = 'يرجى توثيق رقم جوالك لإكمال الدخول.';
                header('Location: verify_phone.php');
                exit;
            }
            // تذكرني
            if (!empty($_POST['remember'])) {
                $cookieVal = remember_me_issue($user['id']);
                set_remember_cookie($cookieVal);
            }
            header('Location: index.php');
            exit;
        } else {
            $error = 'بيانات الدخول غير صحيحة.';
        }
    } else {
        $error = 'يرجى إدخال البريد الإلكتروني وكلمة المرور.';
    }
}

// بعد الانتهاء من التحويلات المحتملة، قم بعرض الترويسة والواجهة
require_once __DIR__ . '/header.php';
?>
<section class="auth-card card-modern" style="max-width: 460px; margin: 24px auto; padding: 22px;">
    <h2 style="margin-top:0;margin-bottom:6px;">تسجيل الدخول</h2>
    <p style="margin:0 0 14px; color:#eaf2ff;">أدخل بريدك أو رقم جوالك وكلمة المرور للمتابعة.</p>
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <p class="success"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form method="POST" action="login.php" class="auth-form form-modern" style="max-width:100%; margin:0;">
        <label for="identifier">البريد الإلكتروني أو رقم الجوال:</label>
        <input type="text" name="identifier" id="identifier" class="input-modern" autocomplete="username" value="<?php echo htmlspecialchars($identifier); ?>" required>

        <label for="password">كلمة المرور:</label>
        <div style="position:relative;">
            <input type="password" name="password" id="password" class="input-modern" autocomplete="current-password" value="<?php echo htmlspecialchars($password); ?>" required>
            <button type="button" id="togglePass" style="position:absolute; inset-inline-end:10px; top:50%; transform:translateY(-50%); background:transparent; border:none; color:#eaf2ff; cursor:pointer;">إظهار</button>
        </div>

        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin:8px 0 4px;">
            <label style="display:inline-flex; align-items:center; gap:6px; color:#eaf2ff; font-size:14px;">
                <input type="checkbox" name="remember" value="1" style="accent-color:#0a3e80;">
                تذكرني
            </label>
            <span style="font-size:12px; color:#d3ddf5;">قد يُطلب منك توثيق الجوال عند الدخول لأول مرة</span>
        </div>

        <button type="submit" class="btn-modern" style="width:100%; margin-top:10px;">تسجيل الدخول</button>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
            <a href="forgot_password.php" style="font-size:14px; color:#cfe0ff;">نسيت كلمة المرور؟</a>
            <p style="margin:0; color:#eaf2ff;">ليس لديك حساب؟ <a href="register.php">إنشاء حساب جديد</a></p>
        </div>
    </form>
</section>
<script>
// تبديل إظهار/إخفاء كلمة المرور
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('togglePass');
    var input = document.getElementById('password');
    if (btn && input) {
        btn.addEventListener('click', function(){
            var isPw = input.type === 'password';
            input.type = isPw ? 'text' : 'password';
            btn.textContent = isPw ? 'إخفاء' : 'إظهار';
        });
    }
});
</script>
<?php
require_once __DIR__ . '/footer.php';