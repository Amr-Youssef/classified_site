<?php
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/functions.php';

// تحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
// جلب الإشعارات
$stmt = $pdo->prepare('SELECT id, content, is_read, created_at FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC');
$stmt->execute(['user_id' => $user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>الإشعارات</h2>
<?php if (!$notifications): ?>
    <p>لا توجد إشعارات.</p>
<?php else: ?>
    <ul class="notifications-list">
        <?php foreach ($notifications as $note): ?>
            <!-- تمت إزالة class "card-modern" حتى تسمح الأنماط المخصصة بالتحكم فى المظهر -->
            <li class="<?php echo $note['is_read'] ? 'read' : 'unread'; ?>">
                <p><?php echo htmlspecialchars($note['content']); ?></p>
                <span class="timestamp">(<?php echo htmlspecialchars($note['created_at']); ?>)</span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>