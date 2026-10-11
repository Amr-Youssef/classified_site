<?php
/**
 * صفحة لعرض قائمة المحادثات الخاصة بالمستخدم.
 * تظهر فيها كل المحادثات بين المستخدم الحالي والآخرين عبر الإعلانات المختلفة.
 */
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/functions.php';

// يجب التأكد من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// جلب المحادثات كمستخدمين آخرين بدون تمييز بالإعلانات
$conversations = get_user_conversations($user_id);
?>

<h2>المحادثات</h2>

<?php if (!$conversations): ?>
    <p>لا توجد محادثات بعد.</p>
<?php else: ?>
    <ul class="messages-list conversations-list">
        <?php foreach ($conversations as $conv): ?>
            <?php
                $otherId = (int)$conv['other_user_id'];
                // الحصول على صورة المستخدم الآخر أو تحديد صورة افتراضية
                $stmtAvatar = $pdo->prepare('SELECT avatar FROM users WHERE id = :id');
                $stmtAvatar->execute(['id' => $otherId]);
                $avatarFile = $stmtAvatar->fetchColumn();
                $avatarUrl = '';
                if ($avatarFile && file_exists(__DIR__ . '/uploads/' . $avatarFile)) {
                    $avatarUrl = 'uploads/' . $avatarFile;
                } else {
                    $avatarUrl = 'placeholder.png';
                }
            ?>
            <li class="conversation-item card-modern">
                <a href="conversation.php?user_id=<?php echo $otherId; ?>">
                    <div class="conv-avatar">
                        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar of <?php echo htmlspecialchars($conv['other_username']); ?>">
                    </div>
                    <div class="conv-content">
                        <strong class="conv-name"><?php echo htmlspecialchars($conv['other_username']); ?></strong>
                        <p class="last-message"><?php echo htmlspecialchars(mb_strimwidth($conv['last_message'], 0, 80, '...')); ?></p>
                    </div>
                    <div class="conv-meta">
                        <span class="timestamp"><?php echo htmlspecialchars($conv['last_time']); ?></span>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>