<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized']);
    exit;
}

$rid = trim($_GET['rid'] ?? '');
if ($rid === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'missing_request']);
    exit;
}

$res = nafath_check_status($pdo, $rid);
echo json_encode(['ok'=>!empty($res['ok']), 'status'=>$res['status'] ?? 'pending']);
?>