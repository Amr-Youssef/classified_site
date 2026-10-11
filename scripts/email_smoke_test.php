<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Allow passing recipient via CLI (first arg) or GET; default to EMAIL_FROM to increase success odds
$to = $_GET['to'] ?? ($argv[1] ?? EMAIL_FROM);
$ok = send_email($to, 'اختبار البريد من سوق الإعلانات', '<p>هذه رسالة اختبار.</p>', 'اختبار بريد');
header('Content-Type: text/plain; charset=utf-8');
if ($ok) {
    echo "تم إرسال رسالة الاختبار إلى: $to\n";
} else {
    echo "فشل إرسال رسالة الاختبار.\n";
    echo "EMAIL_PROVIDER=".(defined('EMAIL_PROVIDER')?EMAIL_PROVIDER:'(undefined)')."\n";
    echo "ENV: SMTP_HOST=".SMTP_HOST.", PORT=".SMTP_PORT.", ENC=".SMTP_ENCRYPTION."\n";
    echo "EMAIL_FROM=".EMAIL_FROM."\n";
    if (defined('BREVO_API_KEY')) {
        echo "BREVO_API_KEY=".(BREVO_API_KEY?('set (len='.strlen(BREVO_API_KEY).')'):'not set')."\n";
    }
    echo "openssl: ".(extension_loaded('openssl')?'yes':'no').", allow_url_fopen: ".(ini_get('allow_url_fopen')?'yes':'no')."\n";
    if (defined('EMAIL_PROVIDER') && EMAIL_PROVIDER === 'file') {
        echo "MAIL_STORE_DIR=".(defined('MAIL_STORE_DIR')?MAIL_STORE_DIR:'(undefined)')."\n";
    }
    // Optional: probe Brevo API connectivity when using brevo provider
    if (defined('EMAIL_PROVIDER') && EMAIL_PROVIDER === 'brevo' && BREVO_API_KEY) {
        $opts = [ 'http' => [
            'method' => 'GET',
            'header' => "api-key: ".BREVO_API_KEY."\r\n",
            'timeout' => 8
        ]];
        $ctx = stream_context_create($opts);
        $resp = @file_get_contents('https://api.brevo.com/v3/account', false, $ctx);
        $status = isset($http_response_header[0]) ? $http_response_header[0] : '(no response)';
        echo "Brevo /v3/account status: $status\n";
    }
}
