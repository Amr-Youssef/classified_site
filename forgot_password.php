<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$step = $_GET['step'] ?? 'request';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'request') {
        $identifier = trim($_POST['identifier'] ?? '');
        if ($identifier === '') { $error = 'يرجى إدخال البريد أو رقم الجوال.'; }
        else {
            // ابحث عن المستخدم بالبريد أو الهاتف
            if (strpos($identifier, '@') !== false) {
                $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = :e LIMIT 1');
                $stmt->execute(['e' => $identifier]);
            } else {
                $phone = normalize_phone($identifier);
                $stmt = $pdo->prepare('SELECT id, phone FROM users WHERE phone = :p LIMIT 1');
                $stmt->execute(['p' => $phone]);
            }
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                // أنشئ كود OTP وأرسله للجوال (أو بريد لاحقاً)
                $code = create_phone_otp((int)$user['id'], 600);
                // لمحلي: اعتبر الإرسال ناجحاً
                send_phone_otp($user['phone'] ?? '', $code);
                $_SESSION['reset_user_id'] = (int)$user['id'];
                $_SESSION['flash_success'] = 'تم إرسال كود التحقق. يرجى إدخاله.';
                header('Location: forgot_password.php?step=verify');
                exit;
            } else {
                $error = 'لم يتم العثور على مستخدم بهذه البيانات.';
            }
        }
    } elseif ($step === 'verify') {
        $code = trim($_POST['code'] ?? '');
        $uid = $_SESSION['reset_user_id'] ?? 0;
        if (!$uid) { $error = 'انتهت الجلسة. أعد المحاولة.'; $step='request'; }
        elseif ($code === '') { $error = 'يرجى إدخال كود التحقق.'; }
        else {
            if (verify_phone_otp((int)$uid, $code)) {
                $_SESSION['reset_verified'] = 1;
                header('Location: forgot_password.php?step=reset');
                exit;
            } else {
                $error = 'كود غير صحيح أو منتهي.';
            }
        }
    } elseif ($step === 'reset') {
        $uid = $_SESSION['reset_user_id'] ?? 0;
        if (!$uid || empty($_SESSION['reset_verified'])) { $error = 'انتهت الجلسة. أعد المحاولة.'; $step='request'; }
        else {
            $p1 = trim($_POST['password'] ?? '');
            $p2 = trim($_POST['password2'] ?? '');
            if ($p1 === '' || $p2 === '') { $error = 'يرجى إدخال كلمة المرور الجديدة.'; }
            elseif ($p1 !== $p2) { $error = 'كلمتا المرور غير متطابقتين.'; }
            else {
                $hash = password_hash($p1, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('UPDATE users SET password = :pw WHERE id = :id');
                $stmt->execute(['pw' => $hash, 'id' => (int)$uid]);
                unset($_SESSION['reset_user_id'], $_SESSION['reset_verified']);
                $_SESSION['flash_success'] = 'تم تحديث كلمة المرور. يمكنك تسجيل الدخول الآن.';
                header('Location: login.php');
                exit;
            }
        }
    }
}

require_once __DIR__ . '/header.php';
?>
<section class="card-modern" style="max-width:520px;margin:24px auto;padding:22px;">
    <h2 style="margin-top:0;">استعادة كلمة المرور</h2>
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <p class="success"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></p>
    <?php endif; ?>
    <?php if ($error): ?><p class="error"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

    <?php if ($step === 'request'): ?>
        <form method="POST" class="form-modern">
            <label>البريد الإلكتروني أو رقم الجوال</label>
            <input type="text" name="identifier" class="input-modern" required>
            <button type="submit" class="btn-modern" style="margin-top:10px;">إرسال كود التحقق</button>
        </form>
    <?php elseif ($step === 'verify'): ?>
        <form method="POST" class="form-modern">
            <label>أدخل كود التحقق المكون من 6 أرقام</label>
            <input type="text" name="code" class="input-modern" maxlength="6" pattern="[0-9]{6}" required>
            <button type="submit" class="btn-modern" style="margin-top:10px;">تحقق</button>
        </form>
    <?php elseif ($step === 'reset'): ?>
        <form method="POST" class="form-modern">
            <label>كلمة المرور الجديدة</label>
            <input type="password" name="password" class="input-modern" required>
            <label>تأكيد كلمة المرور</label>
            <input type="password" name="password2" class="input-modern" required>
            <button type="submit" class="btn-modern" style="margin-top:10px;">تحديث كلمة المرور</button>
        </form>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/footer.php';
