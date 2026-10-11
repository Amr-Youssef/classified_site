<?php
require_once __DIR__ . '/../lib/mailin-api/Mailin.php';

$mailin = new Mailin(getenv('SMTP_USERNAME') ?: SMTP_USERNAME, getenv('SMTP_PASSWORD') ?: SMTP_PASSWORD);
$mailin->addTo(EMAIL_FROM, 'Self Test')
       ->setFrom(EMAIL_FROM, EMAIL_FROM_NAME)
       ->setReplyTo(EMAIL_FROM, EMAIL_FROM_NAME)
       ->setSubject('Mailin smoke test')
       ->setText('Hello via Mailin wrapper')
       ->setHtml('<strong>Hello via Mailin wrapper</strong>');
$res = $mailin->send();
header('Content-Type: application/json; charset=utf-8');
print json_encode($res, JSON_UNESCAPED_UNICODE);
