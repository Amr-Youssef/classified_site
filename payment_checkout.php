<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// تحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$provider = strtolower(trim($_GET['provider'] ?? PAYMENT_PROVIDER));
$promo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$promo = $promo_id ? get_promotion_by_id($promo_id) : null;
if (!$promo) { header('Location: index.php'); exit; }
// تأكد أن الطلب يعود لصاحبه
if ((int)$promo['user_id'] !== (int)$_SESSION['user_id']) { header('Location: index.php'); exit; }

// دعم مويصر عبر صفحة وسيطة تحتوي نموذج JS
if ($provider === 'moyasar') {
    require_once __DIR__ . '/header.php';
    $amountHalalas = (int)round(((float)$promo['price']) * 100);
    $desc = 'تمييز إعلان #' . (int)$promo['ad_id'] . ' لمدة ' . (int)$promo['days'] . ' يوم';
    $publishable = MOYASAR_PUBLISHABLE_KEY;
    $callbackUrl = build_absolute_url('payment_callback.php?gateway=moyasar&promo_id=' . $promo_id);
    echo '<div class="ad-details-wrapper"><div class="detail-card card-modern">';
    echo '<h3>إتمام الدفع</h3>';
    echo '<p>المبلغ: ' . number_format((float)$promo['price'], 2) . ' ر.س — ' . htmlspecialchars($desc) . '</p>';
    if ($publishable) {
        echo '<script src="https://cdn.moyasar.com/mpf/1.14.0/moyasar.js"></script>';
        echo '<link rel="stylesheet" href="https://cdn.moyasar.com/mpf/1.14.0/moyasar.css" />';
        echo '<div class="mysr-form" id="mysr-form"></div>';
    echo '<script> document.addEventListener("DOMContentLoaded",function(){ Moyasar.init({ element: "#mysr-form", amount: ' . $amountHalalas . ', currency: "' . htmlspecialchars(MOYASAR_CURRENCY) . '", description: "' . htmlspecialchars($desc) . '", publishable_api_key: "' . htmlspecialchars($publishable) . '", callback_url: "' . htmlspecialchars($callbackUrl) . '", methods: ["creditcard", "applepay", "stcpay"], metadata: { promo_id: ' . (int)$promo_id . ' } }); }); </script>';
    } else {
        // محاكاة فقط إذا لم يتم توفير مفتاح publishable
        echo '<p>بيئة تجريبية: لم يُضبط MOYASAR_PUBLISHABLE_KEY. استخدم المحاكاة:</p>';
        echo '<a class="btn btn-modern" href="' . htmlspecialchars($callbackUrl . '&status=paid') . '">محاكاة دفع مويصر (نجاح)</a>';
    }
    echo '</div></div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

if ($provider === 'paytabs') {
    require_once __DIR__ . '/header.php';
    echo '<div class="ad-details-wrapper"><div class="detail-card card-modern">';
    echo '<h3>إتمام الدفع (PayTabs)</h3>';
    if (!PAYTABS_SERVER_KEY || !PAYTABS_PROFILE_ID) {
        echo '<p>لم يتم ضبط مفاتيح PayTabs في .env. يرجى ضبط PAYTABS_PROFILE_ID وPAYTABS_SERVER_KEY.</p>';
        echo '</div></div>';
        require_once __DIR__ . '/footer.php';
        exit;
    }
    // إنشاء طلب دفع وإعادة توجيه إلى صفحة PayTabs
    $amount = number_format((float)$promo['price'], 2, '.', '');
    $return_url = build_absolute_url('payment_callback.php?gateway=paytabs&promo_id=' . $promo_id);
    $webhook_url = build_absolute_url('payment_webhook.php?gateway=paytabs');
    $tran_req = [
        'profile_id' => PAYTABS_PROFILE_ID,
        'tran_type'  => 'sale',
        'tran_class' => 'ecom',
        'cart_id'    => 'promo_' . $promo_id . '_' . time(),
        'cart_currency' => 'SAR',
        'cart_amount'   => $amount,
        'cart_description' => 'تمييز إعلان #' . (int)$promo['ad_id'] . ' لمدة ' . (int)$promo['days'] . ' يوم',
    'callback'     => $webhook_url, // إشعار الخادم (webhook)
        'return'       => $return_url, // عودة المتصفح
        'hide_shipping' => 1,
        'customer_details' => [ 'name' => 'User ' . (int)$promo['user_id'], 'email' => 'customer@example.com', 'phone' => '']
    ];
    $url = paytabs_api_base() . '/payment/request';
    $opts = [ 'http' => [
        'method' => 'POST',
        'header' => "Authorization: " . PAYTABS_SERVER_KEY . "\r\n" .
                    "Content-Type: application/json\r\n",
        'content' => json_encode($tran_req, JSON_UNESCAPED_UNICODE),
        'timeout' => 20
    ]];
    $ctx = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $ctx);
    $data = $resp ? json_decode($resp, true) : null;
    if (!$data || empty($data['redirect_url']) || empty($data['tran_ref'])) {
        echo '<p>تعذر إنشاء طلب PayTabs. الرجاء المحاولة لاحقاً.</p>';
        echo '</div></div>';
        require_once __DIR__ . '/footer.php';
        exit;
    }
    // خزّن مرجع المعاملة للمتابعة
    set_promotion_provider_ref($promo_id, (string)$data['tran_ref']);
    // إعادة التوجيه إلى بوابة PayTabs
    echo '<p>سيتم تحويلك الآن لإتمام الدفع...</p>';
    echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($data['redirect_url']) . '">';
    echo '</div></div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

// أي مزود آخر: استخدم mock
mark_promotion_paid($promo_id, 'mock:' . uniqid());
header('Location: promotion_success.php?id=' . $promo_id);
exit;
?>
