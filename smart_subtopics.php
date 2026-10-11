<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
header('Content-Type: application/json; charset=utf-8');

$cid = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
if ($cid <= 0) {
    echo json_encode([]);
    exit;
}
// اجلب أفضل 25 موضوعاً فرعياً مع حد أدنى 1
$rows = get_smart_subtopics($cid, 25, 1);
// تأكد من الإخراج الآمن
$out = [];
foreach ($rows as $r) {
    $out[] = [
        'name' => (string)$r['name'],
        'cnt'  => (int)$r['cnt']
    ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
