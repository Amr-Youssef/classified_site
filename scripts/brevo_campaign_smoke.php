<?php
// Minimal Brevo Email Campaigns API smoke test without Composer
// Usage (PowerShell):
//   C:\xampp\php\php.exe scripts\brevo_campaign_smoke.php
// Optional GET/CLI params: ?list=123&name=MyCamp&subject=Hello

require_once __DIR__ . '/../config.php';

$apiKey = getenv('BREVO_API_KEY') ?: (defined('BREVO_API_KEY') ? BREVO_API_KEY : '');
$senderEmail = defined('EMAIL_FROM') ? EMAIL_FROM : 'no-reply@example.com';
$senderName  = defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'Site';

// Allow passing args via CLI as key=value pairs
parse_str(implode('&', array_slice($argv ?? [], 1)), $cliArgs);

$listId = (int)($_GET['list'] ?? ($cliArgs['list'] ?? 0));
$name   = (string)($_GET['name'] ?? ($cliArgs['name'] ?? 'API Smoke Campaign'));
$subject= (string)($_GET['subject'] ?? ($cliArgs['subject'] ?? 'Hello from API'));

header('Content-Type: text/plain; charset=utf-8');
if (!$apiKey) {
    echo "BREVO_API_KEY is not set.\n";
    exit(1);
}
if ($listId <= 0) {
    echo "Please provide a valid list id via ?list=ID or CLI list=ID.\n";
    // proceed anyway to show 400 errors from API
}

$payload = [
    'name' => $name,
    'subject' => $subject,
    'sender' => [ 'name' => $senderName, 'email' => $senderEmail ],
    'type' => 'classic',
    'htmlContent' => '<p>Congratulations! You called Brevo Email Campaigns API.</p>',
    'recipients' => [ 'listIds' => $listId > 0 ? [$listId] : [] ],
    'scheduledAt' => gmdate('Y-m-d\TH:i:s\Z', time() + 3600)
];

$opts = [ 'http' => [
    'method' => 'POST',
    'header' => "api-key: $apiKey\r\nContent-Type: application/json\r\naccept: application/json\r\n",
    'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
    'timeout' => 15
]];
$ctx = stream_context_create($opts);
$resp = @file_get_contents('https://api.brevo.com/v3/emailCampaigns', false, $ctx);
$status = isset($http_response_header[0]) ? $http_response_header[0] : '(no response)';
echo "Status: $status\n";
echo "Response: \n";
echo $resp !== false ? $resp : '(no body)';
