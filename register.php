<?php
// معالجة قبل أي إخراج لتجنب تحذير الرؤوس
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// إذا كان المستخدم مسجلاً بالفعل، إعادة توجيهه قبل أي إخراج
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($username && $email && $password && $confirm && $phone) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'البريد الإلكتروني غير صالح.';
        } elseif ($password !== $confirm) {
            $error = 'كلمات المرور غير متطابقة.';
        } else {
            // رفع صورة الملف الشخصي إن تم رفعها (قبل إنشاء المستخدم لتخزين المسار)
            $avatarPath = '';
            if (!empty($_FILES['avatar']['name'])) {
                $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $allowed = ['jpg','jpeg','png','gif','webp'];
                if (in_array(strtolower($ext), $allowed)) {
                    $avatarName = 'avatar_' . uniqid('', true) . '.' . $ext;
                    $target = __DIR__ . '/uploads/avatars';
                    if (!is_dir($target)) @mkdir($target, 0777, true);
                    $destFs = $target . '/' . $avatarName;
                    if (@move_uploaded_file($_FILES['avatar']['tmp_name'], $destFs)) {
                        // حفظ المسار النسبي للويب
                        $avatarPath = 'uploads/avatars/' . $avatarName;
                    }
                }
            }

            // إنشاء المستخدم
            $result = create_user($username, $email, $password, $phone, $avatarPath);
        if ($result === true) {
                // تسجيل الدخول آلياً ثم إرسال OTP وتحويل إلى صفحة التحقق
                $user = authenticate_user($email, $password);
                if ($user) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['is_admin'] = !empty($user['is_admin']) ? (int)$user['is_admin'] : 0;
                    // إنشاء وإرسال كود OTP
                    $code = create_phone_otp($user['id']);
                    $phoneNorm = normalize_phone($phone);
                    send_phone_otp($phoneNorm, $code);
            // إرسال تفعيل البريد
            send_email_verification($user['id'], $email);
                    // رسالة مؤقتة
            $_SESSION['flash_success'] = 'تم إنشاء الحساب. أرسلنا رمز التحقق إلى جوالك ورابط تفعيل إلى بريدك.';
                    // في بيئة التطوير فقط يمكن عرض الكود للتسهيل
                    if (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'])) {
                        $_SESSION['flash_info'] = 'رمز التحقق (للتجربة المحلية): ' . $code;
                    }
                    header('Location: verify_phone.php');
                    exit;
                }
                // في حال لم ينجح تسجيل الدخول مباشرة لسبب ما
                header('Location: login.php');
                exit;
            } else {
                $error = $result;
            }
        }
    } else {
        $error = 'يرجى تعبئة جميع الحقول (بما في ذلك رقم الجوال).';
    }
}
?>
<?php require_once __DIR__ . '/header.php'; ?>
<section class="auth-card card-modern">
    <h2>إنشاء حساب جديد</h2>
    <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])) { echo '<p class="success">' . htmlspecialchars($_SESSION['flash_success']) . '</p>'; unset($_SESSION['flash_success']); } ?>
    <?php if (!empty($_SESSION['flash_info'])) { echo '<p class="info">' . htmlspecialchars($_SESSION['flash_info']) . '</p>'; unset($_SESSION['flash_info']); } ?>

    <form method="POST" action="register.php" class="auth-form form-modern" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group">
                <label for="username">اسم المستخدم:</label>
                <input type="text" name="username" id="username" class="input-modern" autocomplete="username" required>
            </div>
            <div class="form-group">
                <label for="email">البريد الإلكتروني:</label>
                <input type="email" name="email" id="email" class="input-modern" autocomplete="email" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="phone">رقم الجوال:</label>
                <input type="text" name="phone" id="phone" class="input-modern" placeholder="مثال: 05XXXXXXXX أو 9665XXXXXXXX" autocomplete="tel" required>
                <small class="form-hint">سيتطلب التحقق عبر رمز OTP بعد التسجيل.</small>
            </div>
            <div class="form-group">
                <label for="avatar">صورتك (اختياري):</label>
                <input type="file" name="avatar" id="avatar" accept="image/*" class="input-modern">
                <div class="avatar-preview" id="avatarPreview" aria-hidden="true"></div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="password">كلمة المرور:</label>
                <div class="input-with-action">
                    <input type="password" name="password" id="password" class="input-modern" autocomplete="new-password" required>
                    <button type="button" class="toggle-visibility" data-target="password" aria-label="إظهار/إخفاء كلمة المرور">👁</button>
                </div>
            </div>
            <div class="form-group">
                <label for="confirm">تأكيد كلمة المرور:</label>
                <div class="input-with-action">
                    <input type="password" name="confirm" id="confirm" class="input-modern" autocomplete="new-password" required>
                    <button type="button" class="toggle-visibility" data-target="confirm" aria-label="إظهار/إخفاء كلمة المرور">👁</button>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-modern" style="margin-top:8px;">إنشاء الحساب</button>
        <p class="auth-note">لديك حساب بالفعل؟ <a href="login.php">تسجيل الدخول</a></p>
    </form>
</section>
<script>
(function(){
    // Toggle password visibility
    var toggles = document.querySelectorAll('.toggle-visibility');
    toggles.forEach(function(btn){
        btn.addEventListener('click', function(){
            var id = btn.getAttribute('data-target');
            var input = document.getElementById(id);
            if (!input) return;
            var isPwd = input.type === 'password';
            input.type = isPwd ? 'text' : 'password';
            btn.textContent = isPwd ? '🙈' : '👁';
        });
    });
    // Avatar preview
    var fileInput = document.getElementById('avatar');
    var preview = document.getElementById('avatarPreview');
    if (fileInput && preview) {
        fileInput.addEventListener('change', function(){
            var file = fileInput.files && fileInput.files[0];
            if (!file) { preview.innerHTML=''; preview.setAttribute('aria-hidden','true'); return; }
            var reader = new FileReader();
            reader.onload = function(e){
                preview.innerHTML = '<img src="'+ e.target.result +'" alt="معاينة الصورة" />';
                preview.setAttribute('aria-hidden','false');
            };
            reader.readAsDataURL(file);
        });
    }
})();
</script>
<?php
require_once __DIR__ . '/footer.php';