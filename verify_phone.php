<?php
// منطق التحقق من الجوال عبر OTP
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user = get_user_by_id($_SESSION['user_id']);
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$info = '';
$error = '';
$success = '';

// إذا كان موثق مسبقاً، ارجاعه للصفحة الرئيسية
if (!empty($user['phone_verified'])) {
    header('Location: index.php');
    exit;
}

// إنشاء تلقائي لأول زيارة إذا لا يوجد رمز حديث، وإلا تطبيق تبريد عند إعادة الإرسال
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $last = get_last_phone_otp($user['id']);
    $needIssue = true;
    if ($last) {
        $notExpired = strtotime($last['expires_at']) > time();
        $needIssue = !$notExpired;
    }
    if ($needIssue) {
        $code = create_phone_otp($user['id']);
        send_phone_otp($user['phone'], $code);
        $info = 'أرسلنا رمز تحقق جديد إلى جوالك.';
        if (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'])) {
            $info .= ' (رمز التجربة المحلية: ' . htmlspecialchars($code) . ')';
        }
    }
}

// إعادة إرسال الرمز مع تبريد 60 ثانية
if (isset($_POST['resend'])) {
    if (!can_resend_phone_otp($user['id'], 60)) {
        $error = 'يرجى الانتظار قليلاً قبل طلب رمز جديد.';
    } else {
        $code = create_phone_otp($user['id']);
        send_phone_otp($user['phone'], $code);
        $info = 'تم إرسال رمز جديد إلى جوالك.';
        if (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'])) {
            $info .= ' (رمز التجربة المحلية: ' . htmlspecialchars($code) . ')';
        }
    }
}

// تحقق من الرمز المُدخل
if (isset($_POST['verify']) && isset($_POST['otp'])) {
    $otp = trim($_POST['otp']);
    if (preg_match('/^\d{4,8}$/', $otp)) {
        if (verify_phone_otp($user['id'], $otp)) {
            $success = 'تم توثيق رقم الجوال بنجاح.';
            // تحديث الكائن المحلي
            $user = get_user_by_id($_SESSION['user_id']);
            header('Location: index.php');
            exit;
        } else {
            $error = 'رمز غير صحيح أو منتهي الصلاحية.';
        }
    } else {
        $error = 'يرجى إدخال رمز صحيح.';
    }
}

require_once __DIR__ . '/header.php';
?>
<h2>التحقق من رقم الجوال</h2>
<?php if (!empty($_SESSION['flash_success'])) { echo '<p class="success">' . htmlspecialchars($_SESSION['flash_success']) . '</p>'; unset($_SESSION['flash_success']); } ?>
<?php if ($info): ?><p class="info"><?php echo $info; ?></p><?php endif; ?>
<?php if ($error): ?><p class="error"><?php echo $error; ?></p><?php endif; ?>
<p>أدخل الرمز المكوّن من 6 أرقام الذي أرسلناه إلى: <strong><?php echo htmlspecialchars(format_phone_display($user['phone'])); ?></strong></p>
<form method="POST" action="verify_phone.php" class="auth-form form-modern" style="max-width:420px">
    <label for="otp">رمز التحقق (OTP):</label>
    <input type="text" id="otp" name="otp" class="input-modern" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="123456" required>
    <div style="display:flex; gap:10px; margin-top:10px">
        <button type="submit" name="verify" class="btn-modern" style="flex:1">تحقق</button>
        <button type="submit" name="resend" value="1" class="btn-modern" style="flex:1; background:linear-gradient(135deg,#5a6,#3a3)">أعد إرسال الرمز</button>
    </div>
</form>
<?php require_once __DIR__ . '/footer.php';
