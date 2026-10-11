<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$ok = false;
$msg = '';
$redirectTarget = '';
$token = $_GET['token'] ?? '';
if ($token) {
    if (verify_email_token($token)) {
        $ok = true;
        $msg = 'تم تفعيل بريدك الإلكتروني بنجاح.';
        // تحديد وجهة التحويل: من البارامتر أو افتراضياً حسب حالة الدخول
        $reqRedirect = $_GET['redirect'] ?? '';
        $redirectTarget = $reqRedirect ?: (!empty($_SESSION['user_id']) ? 'profile.php' : 'index.php');
        // تأمين الوجهة لمنع open redirect
        if (preg_match('/^https?:/i', $redirectTarget) || strpos($redirectTarget, '//') === 0) {
            $redirectTarget = 'index.php';
        }
    } else {
        $msg = 'رابط التفعيل غير صالح أو منتهي.';
    }
} else {
    $msg = 'لا يوجد رمز تفعيل.';
}

require_once __DIR__ . '/header.php';
?>
<h2>تفعيل البريد الإلكتروني</h2>
<p class="<?php echo $ok ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($msg); ?></p>
<?php if ($ok): ?>
    <?php if ($redirectTarget): ?>
        <p>سيتم تحويلك تلقائياً خلال ثوانٍ قليلة...</p>
        <p><a href="<?php echo htmlspecialchars($redirectTarget); ?>">اضغط هنا إذا لم يتم التحويل</a></p>
        <script>
            setTimeout(function(){ window.location.href = <?php echo json_encode($redirectTarget, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>; }, 2500);
        </script>
    <?php else: ?>
        <p>يمكنك الآن متابعة استخدام حسابك بكامل الميزات.</p>
    <?php endif; ?>
<?php else: ?>
    <p>إذا كنت بحاجة إلى رابط جديد، يرجى طلبه من صفحة الملف الشخصي.</p>
<?php endif; ?>
<?php require_once __DIR__ . '/footer.php';
