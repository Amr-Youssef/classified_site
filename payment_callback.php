<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$gateway = strtolower($_GET['gateway'] ?? '');
$result = verify_payment_callback($gateway, $_POST, $_GET);
// تعزيز تحقق مويصر عبر استعلام API بسيط إذا كان المفتاح السري متوفراً
if (!($result['ok'] ?? false) && $gateway === 'moyasar' && MOYASAR_SECRET_KEY) {
    $promo_id = isset($_GET['promo_id']) ? (int)$_GET['promo_id'] : (int)($_GET['id'] ?? 0);
    $paymentId = $_GET['id'] ?? ($_GET['payment_id'] ?? ''); // Moyasar عادة ترجع id للدفع
    if ($promo_id && $paymentId) {
        $url = 'https://api.moyasar.com/v1/payments/' . rawurlencode($paymentId);
        $opts = [ 'http' => [
            'method' => 'GET',
            'header' => 'Authorization: Basic ' . base64_encode(MOYASAR_SECRET_KEY . ':') . "\r\n",
            'timeout' => 10
        ]];
        $ctx = stream_context_create($opts);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp) {
            $data = json_decode($resp, true);
            if (($data['status'] ?? '') === 'paid') {
                mark_promotion_paid($promo_id, (string)$paymentId);
                $result = ['ok' => true];
            }
        }
    }
}
// PayTabs: لا حاجة لخطوة إضافية هنا لأن verify_payment_callback يستعلم API
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($result['ok'] ?? false) {
    header('Location: promotion_success.php?id=' . $id);
    exit;
}
// فشل
require_once __DIR__ . '/header.php';
echo '<div class="ad-details-wrapper"><div class="detail-card card-modern">';
echo '<h3>فشل الدفع</h3>';
echo '<p>تعذر تأكيد عملية الدفع. الرجاء المحاولة مرة أخرى.</p>';
echo '<p><a class="btn btn-modern" href="index.php">العودة للرئيسية</a></p>';
echo '</div></div>';
require_once __DIR__ . '/footer.php';
?>
