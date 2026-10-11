<?php
require_once __DIR__ . '/config.php';
// إنهاء الجلسة وتسجيل الخروج
if (!empty($_SESSION['user_id'])) {
	// امسح رموز التذكر لهذا المستخدم أيضاً
	require_once __DIR__ . '/functions.php';
	forget_me((int)$_SESSION['user_id']);
}
session_destroy();
header('Location: index.php');
exit;