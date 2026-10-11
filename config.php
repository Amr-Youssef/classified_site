<?php
// تحميل متغيرات من ملف .env إن وجد (للتسهيل محلياً)
if (!function_exists('cfg_load_env')) {
    function cfg_load_env(string $path): array {
        if (!is_file($path)) return [];
        $out = [];
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            $v = trim($v, "\"' ");
            $out[$k] = $v;
        }
        return $out;
    }
}

$__env = cfg_load_env(__DIR__ . '/.env');

// Database configuration (قابلة للتهيئة عبر .env)
$db_host = ($__env['DB_HOST'] ?? getenv('DB_HOST')) ?: 'localhost';
$db_name = ($__env['DB_NAME'] ?? getenv('DB_NAME')) ?: 'classified_db';
$db_user = ($__env['DB_USER'] ?? getenv('DB_USER')) ?: 'root';
$db_pass = ($__env['DB_PASS'] ?? getenv('DB_PASS')) ?: '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // تأكد من استخدام ترميز UTF-8 الكامل للمحتوى العربي والرموز التعبيرية
    // بعض إصدارات MySQL/MariaDB لا تضبط collation تلقائياً بالـ DSN، لذلك نحددها صراحةً
    try {
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET collation_connection = 'utf8mb4_unicode_ci'");
    } catch (Throwable $e2) {
        // تجاهل بصمت إذا لم يكن الأمر مدعوماً
    }
    // اضبط الترميز الداخلي لسلاسل PHP إذا توفّر امتداد mbstring
    if (function_exists('mb_internal_encoding')) { @mb_internal_encoding('UTF-8'); }
} catch (PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// البيئة والتعامل مع الأخطاء
$appEnv = strtolower((($__env['APP_ENV'] ?? getenv('APP_ENV')) ?: 'development'));
if ($appEnv === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    if (!ini_get('error_log')) { @ini_set('error_log', __DIR__ . '/storage/php-errors.log'); }
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
// المنطقة الزمنية الافتراضية
date_default_timezone_set(($__env['TZ'] ?? getenv('TZ')) ?: 'Asia/Riyadh');

// Start session for user authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تحميل Composer autoload إذا كان موجوداً (لـ PHPMailer وغيرها)
$autoload = __DIR__ . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
// إن لم يتوفر Composer، حاول تحميل PHPMailer المحلي من مجلد PHPMailer/
if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    $localPHPMailer = __DIR__ . '/PHPMailer/src/PHPMailer.php';
    $localSMTP = __DIR__ . '/PHPMailer/src/SMTP.php';
    $localException = __DIR__ . '/PHPMailer/src/Exception.php';
    if (is_file($localPHPMailer) && is_file($localSMTP) && is_file($localException)) {
        require_once $localException;
        require_once $localPHPMailer;
        require_once $localSMTP;
    }
}

// محاولة تسجيل الدخول تلقائياً عبر كوكيز "تذكرني" إذا كانت الجلسة فارغة
if (empty($_SESSION['user_id'])) {
    // حمّل الدوال دون التسبب في حلقات تحميل
    $fn = __DIR__ . '/functions.php';
    if (is_file($fn)) {
        // تجنب إعادة تحميل إذا تم تضمين functions من قبل
        if (!function_exists('try_auto_login_from_cookie')) {
            require_once $fn;
        }
        if (function_exists('try_auto_login_from_cookie')) {
            try_auto_login_from_cookie();
        }
    }
}

/*
 * ملاحظة: تم نقل منطق صلاحيات الإدارة إلى عمود is_admin ضمن جدول users.
 * يمكن تعيين is_admin = 1 للمستخدمين الذين لديهم صلاحيات خاصة في قاعدة البيانات.
 */

// $__env مهيأ أعلاه

// إعدادات نفاذ (اختيارية) - استخدم متغيرات البيئة أو اتركها فارغة في بيئة التطوير
if (!defined('NAFATH_BASE_URL')) {
    define('NAFATH_BASE_URL', ($__env['NAFATH_BASE_URL'] ?? getenv('NAFATH_BASE_URL')) ?: '');
}
if (!defined('NAFATH_CLIENT_ID')) {
    define('NAFATH_CLIENT_ID', ($__env['NAFATH_CLIENT_ID'] ?? getenv('NAFATH_CLIENT_ID')) ?: '');
}
if (!defined('NAFATH_CLIENT_SECRET')) {
    define('NAFATH_CLIENT_SECRET', ($__env['NAFATH_CLIENT_SECRET'] ?? getenv('NAFATH_CLIENT_SECRET')) ?: '');
}
if (!defined('NAFATH_BEARER_TOKEN')) {
    define('NAFATH_BEARER_TOKEN', ($__env['NAFATH_BEARER_TOKEN'] ?? getenv('NAFATH_BEARER_TOKEN')) ?: '');
}
if (!defined('NAFATH_CALLBACK_URL')) {
    define('NAFATH_CALLBACK_URL', ($__env['NAFATH_CALLBACK_URL'] ?? getenv('NAFATH_CALLBACK_URL')) ?: '');
}

// إعدادات موفّر رسائل SMS (اختياري): twilio | unifonic | mock (افتراضي)
if (!defined('SMS_PROVIDER')) {
    define('SMS_PROVIDER', strtolower((($__env['SMS_PROVIDER'] ?? getenv('SMS_PROVIDER')) ?: 'mock')));
}
// Twilio
if (!defined('TWILIO_ACCOUNT_SID')) {
    define('TWILIO_ACCOUNT_SID', ($__env['TWILIO_ACCOUNT_SID'] ?? getenv('TWILIO_ACCOUNT_SID')) ?: '');
}
if (!defined('TWILIO_AUTH_TOKEN')) {
    define('TWILIO_AUTH_TOKEN', ($__env['TWILIO_AUTH_TOKEN'] ?? getenv('TWILIO_AUTH_TOKEN')) ?: '');
}
if (!defined('TWILIO_FROM')) {
    define('TWILIO_FROM', ($__env['TWILIO_FROM'] ?? getenv('TWILIO_FROM')) ?: '');
}
// Unifonic (الواجهة التقليدية)
if (!defined('UNIFONIC_APP_SID')) {
    define('UNIFONIC_APP_SID', ($__env['UNIFONIC_APP_SID'] ?? getenv('UNIFONIC_APP_SID')) ?: '');
}
if (!defined('UNIFONIC_SENDER')) {
    define('UNIFONIC_SENDER', ($__env['UNIFONIC_SENDER'] ?? getenv('UNIFONIC_SENDER')) ?: '');
}

// إعدادات البريد الإلكتروني (للتحقق وغيره)
if (!defined('EMAIL_PROVIDER')) {
    define('EMAIL_PROVIDER', strtolower((($__env['EMAIL_PROVIDER'] ?? getenv('EMAIL_PROVIDER')) ?: 'mock')));
}
if (!defined('EMAIL_FROM')) {
    define('EMAIL_FROM', ($__env['EMAIL_FROM'] ?? getenv('EMAIL_FROM')) ?: 'no-reply@classified.local');
}
if (!defined('EMAIL_FROM_NAME')) {
    define('EMAIL_FROM_NAME', ($__env['EMAIL_FROM_NAME'] ?? getenv('EMAIL_FROM_NAME')) ?: 'سوق الإعلانات');
}
// عنوان الأساس للموقع (اختياري). إن لم يحدد، يُستنبط من الطلب الحالي.
if (!defined('SITE_BASE_URL')) {
    define('SITE_BASE_URL', ($__env['SITE_BASE_URL'] ?? getenv('SITE_BASE_URL')) ?: '');
}

// مسار تخزين الرسائل عند استخدام مزود 'file' للتطوير
if (!defined('MAIL_STORE_DIR')) {
    $defaultStore = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'mails';
    define('MAIL_STORE_DIR', ($__env['MAIL_STORE_DIR'] ?? getenv('MAIL_STORE_DIR')) ?: $defaultStore);
}

// إعدادات SMTP (لخيار EMAIL_PROVIDER=smtp)
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', ($__env['SMTP_HOST'] ?? getenv('SMTP_HOST')) ?: '');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', (int)(($__env['SMTP_PORT'] ?? getenv('SMTP_PORT')) ?: 0));
}
if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', ($__env['SMTP_USERNAME'] ?? getenv('SMTP_USERNAME')) ?: '');
}
if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', ($__env['SMTP_PASSWORD'] ?? getenv('SMTP_PASSWORD')) ?: '');
}
if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', strtolower((($__env['SMTP_ENCRYPTION'] ?? getenv('SMTP_ENCRYPTION')) ?: 'tls')));
}

// Brevo API (اختياري لاستخدام REST بدلاً من SMTP)
if (!defined('BREVO_API_KEY')) {
    define('BREVO_API_KEY', ($__env['BREVO_API_KEY'] ?? getenv('BREVO_API_KEY')) ?: '');
}

// بيانات اعتماد المصمم لعرض تنويه تذييل اختياري
if (!defined('DESIGNER_NAME')) {
    define('DESIGNER_NAME', ($__env['DESIGNER_NAME'] ?? getenv('DESIGNER_NAME')) ?: '');
}
if (!defined('DESIGNER_URL')) {
    define('DESIGNER_URL', ($__env['DESIGNER_URL'] ?? getenv('DESIGNER_URL')) ?: '');
}

// معلومات التواصل العامة للموقع (تُستخدم في التذييل وأماكن أخرى)
if (!defined('CONTACT_EMAIL')) {
    define('CONTACT_EMAIL', ($__env['CONTACT_EMAIL'] ?? getenv('CONTACT_EMAIL')) ?: '');
}
if (!defined('CONTACT_PHONE')) {
    define('CONTACT_PHONE', ($__env['CONTACT_PHONE'] ?? getenv('CONTACT_PHONE')) ?: '');
}

// إعدادات بوابة الدفع
if (!defined('PAYMENT_PROVIDER')) {
    // mock | moyasar | paytabs | tabby (حالياً: تم تنفيذ mock + moyasar)
    define('PAYMENT_PROVIDER', strtolower((($__env['PAYMENT_PROVIDER'] ?? getenv('PAYMENT_PROVIDER')) ?: 'mock')));
}
// Moyasar
// مفاتيح Moyasar (publishable للواجهة، secret للتحقق بالخادم)
if (!defined('MOYASAR_PUBLISHABLE_KEY')) {
    $pub = ($__env['MOYASAR_PUBLISHABLE_KEY'] ?? getenv('MOYASAR_PUBLISHABLE_KEY')) ?: (($__env['MOYASAR_API_KEY'] ?? getenv('MOYASAR_API_KEY')) ?: '');
    define('MOYASAR_PUBLISHABLE_KEY', $pub);
}
if (!defined('MOYASAR_SECRET_KEY')) {
    define('MOYASAR_SECRET_KEY', ($__env['MOYASAR_SECRET_KEY'] ?? getenv('MOYASAR_SECRET_KEY')) ?: '');
}
if (!defined('MOYASAR_CURRENCY')) {
    define('MOYASAR_CURRENCY', ($__env['MOYASAR_CURRENCY'] ?? getenv('MOYASAR_CURRENCY')) ?: 'SAR');
}
// PayTabs (قيد التحضير)
if (!defined('PAYTABS_PROFILE_ID')) {
    define('PAYTABS_PROFILE_ID', ($__env['PAYTABS_PROFILE_ID'] ?? getenv('PAYTABS_PROFILE_ID')) ?: '');
}
if (!defined('PAYTABS_SERVER_KEY')) {
    define('PAYTABS_SERVER_KEY', ($__env['PAYTABS_SERVER_KEY'] ?? getenv('PAYTABS_SERVER_KEY')) ?: '');
}
if (!defined('PAYTABS_REGION')) {
    // مثال: SA, AE ... يستخدم لاختيار عنوان API
    define('PAYTABS_REGION', ($__env['PAYTABS_REGION'] ?? getenv('PAYTABS_REGION')) ?: 'SA');
}
// تسعير مرن للتمييز (قابل للتهيئة عبر .env)
if (!defined('PROMO_PRICE_7')) {
    define('PROMO_PRICE_7', (float)(($__env['PROMO_PRICE_7'] ?? getenv('PROMO_PRICE_7')) ?: 20.0));
}
if (!defined('PROMO_PRICE_14')) {
    define('PROMO_PRICE_14', (float)(($__env['PROMO_PRICE_14'] ?? getenv('PROMO_PRICE_14')) ?: 35.0));
}
if (!defined('PROMO_PRICE_30')) {
    define('PROMO_PRICE_30', (float)(($__env['PROMO_PRICE_30'] ?? getenv('PROMO_PRICE_30')) ?: 70.0));
}
