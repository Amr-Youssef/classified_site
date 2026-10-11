<?php
// Webhook endpoint for payment providers (Moyasar, PayTabs)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Respond JSON helper
function json_resp($code, $data) {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$gateway = strtolower($_GET['gateway'] ?? '');
if (!in_array($gateway, ['moyasar', 'paytabs'], true)) {
    json_resp(400, ['ok' => false, 'error' => 'unsupported gateway']);
}

// Read raw input once; attempt JSON decode (used by both providers)
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if ($gateway === 'moyasar') {
    if (!is_array($payload)) {
        json_resp(400, ['ok' => false, 'error' => 'invalid json']);
    }
    // Expect payment id and our promo_id in metadata
    $paymentId = (string)($payload['id'] ?? '');
    $promo_id = (int)($payload['metadata']['promo_id'] ?? 0);
    if ($paymentId === '' || $promo_id <= 0) {
        json_resp(400, ['ok' => false, 'error' => 'missing fields']);
    }
    // Verify with Moyasar API using secret key (safer than trusting webhook blindly)
    if (!MOYASAR_SECRET_KEY) {
        json_resp(403, ['ok' => false, 'error' => 'secret not configured']);
    }
    $url = 'https://api.moyasar.com/v1/payments/' . rawurlencode($paymentId);
    $opts = [ 'http' => [
        'method' => 'GET',
        'header' => 'Authorization: Basic ' . base64_encode(MOYASAR_SECRET_KEY . ':') . "\r\n",
        'timeout' => 10
    ]];
    $ctx = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) {
        json_resp(502, ['ok' => false, 'error' => 'verification failed']);
    }
    $data = json_decode($resp, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'paid') {
        json_resp(200, ['ok' => true, 'processed' => false, 'status' => $data['status'] ?? 'unknown']);
    }
    // Mark promotion paid (idempotent)
    $ok = mark_promotion_paid($promo_id, $paymentId);
    json_resp(200, ['ok' => $ok, 'promo_id' => $promo_id]);
}

// PayTabs webhook handler
// Accept JSON (recommended by PayTabs) or form-encoded fallback
$tran_ref = '';
$cart_id = '';
if (is_array($payload)) {
    $tran_ref = (string)($payload['tran_ref'] ?? '');
    $cart_id  = (string)($payload['cart_id'] ?? '');
}
if ($tran_ref === '' && !empty($_POST)) {
    $tran_ref = isset($_POST['tran_ref']) ? (string)$_POST['tran_ref'] : '';
    $cart_id  = isset($_POST['cart_id']) ? (string)$_POST['cart_id'] : '';
}
if ($tran_ref === '') {
    json_resp(400, ['ok' => false, 'error' => 'missing tran_ref']);
}

// Verify PayTabs transaction via Query API
if (!PAYTABS_SERVER_KEY || !PAYTABS_PROFILE_ID) {
    json_resp(403, ['ok' => false, 'error' => 'paytabs keys not configured']);
}
$verify = paytabs_query_tran($tran_ref);
if (!$verify) {
    json_resp(502, ['ok' => false, 'error' => 'verification failed']);
}
$status = (string)($verify['payment_result']['response_status'] ?? '');
$success = ($status === 'A' || strtolower((string)($verify['success'] ?? '')) === 'true');
if (!$success) {
    json_resp(200, ['ok' => true, 'processed' => false, 'status' => $status]);
}

// Determine promo_id: try from cart_id (format: promo_{id}_timestamp) else via DB by tran_ref
$promo_id = 0;
if ($cart_id && preg_match('/^promo_(\d+)_/i', $cart_id, $m)) {
    $promo_id = (int)$m[1];
}
if ($promo_id <= 0) {
    // Fallback: find by provider_ref
    try {
        global $pdo;
        $stmt = $pdo->prepare('SELECT id FROM promotions WHERE provider = :prov AND provider_ref = :ref ORDER BY id DESC LIMIT 1');
        $stmt->execute(['prov' => 'paytabs', 'ref' => $tran_ref]);
        $promo_id = (int)($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        $promo_id = 0;
    }
}
if ($promo_id <= 0) {
    json_resp(404, ['ok' => false, 'error' => 'promotion not found for tran_ref', 'tran_ref' => $tran_ref]);
}

$ok = mark_promotion_paid($promo_id, $tran_ref);
json_resp(200, ['ok' => $ok, 'promo_id' => $promo_id]);
?>
