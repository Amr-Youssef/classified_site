<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// تحقق من دخول المستخدم قبل أي مخرجات
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// معالجة التحديث قبل أي مخرجات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile'])) {
    $stmt0 = $pdo->prepare('SELECT username, phone FROM users WHERE id = :id');
    $stmt0->execute([':id' => $user_id]);
    $curr = $stmt0->fetch(PDO::FETCH_ASSOC) ?: [];
    $newName = trim($_POST['username'] ?? ($curr['username'] ?? ''));
    $newPhone = trim($_POST['phone'] ?? ($curr['phone'] ?? ''));
    $avatarPath = '';
    if (!empty($_FILES['avatar']['name'])) {
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (in_array($ext, $allowed, true)) {
            $avatarName = 'avatar_' . uniqid('', true) . '.' . $ext;
            $target = __DIR__ . '/uploads/avatars';
            if (!is_dir($target)) @mkdir($target, 0777, true);
            $destFs = $target . '/' . $avatarName;
            if (@move_uploaded_file($_FILES['avatar']['tmp_name'], $destFs)) {
                // مسار نسبي للتخزين في القاعدة
                $avatarPath = 'uploads/avatars/' . $avatarName;
            }
        }
    }
    if (update_user_profile($user_id, $newName, $newPhone, $avatarPath)) {
        header('Location: profile.php');
        exit;
    }
}

// إعادة إرسال رابط تفعيل البريد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_email_ver'])) {
    $stmtE = $pdo->prepare('SELECT email FROM users WHERE id = :id');
    $stmtE->execute([':id' => $user_id]);
    $em = $stmtE->fetchColumn();
    if ($em) {
        send_email_verification($user_id, $em, 'profile.php');
        header('Location: profile.php');
        exit;
    }
}

// جلب معلومات المستخدم
try {
    $stmt = $pdo->prepare('SELECT username, email, phone, avatar, created_at, is_verified, email_verified FROM users WHERE id = :id');
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // إذا كان العمود غير موجود (ترقية قديمة)، أضفه ثم أعد المحاولة
    if ($e->getCode() === '42S22' || stripos($e->getMessage(), 'Unknown column') !== false) {
        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 0');
            $stmt = $pdo->prepare('SELECT username, email, phone, avatar, created_at, is_verified, email_verified FROM users WHERE id = :id');
            $stmt->execute([':id' => $user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e2) {
            // كحل أخير تابع بدون الحقل
            $stmt = $pdo->prepare('SELECT username, email, phone, avatar, created_at, is_verified FROM users WHERE id = :id');
            $stmt->execute([':id' => $user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $user['email_verified'] = 0;
        }
    } else {
        throw $e;
    }
}

if (!$user) {
    require_once __DIR__ . '/header.php';
    echo '<h2>حساب غير موجود</h2>';
    echo '<p>لا يمكن العثور على بيانات الحساب. إذا استمرت المشكلة، يرجى تسجيل الخروج ثم تسجيل الدخول مرة أخرى.</p>';
    require_once __DIR__ . '/footer.php';
    exit;
}

// جلب المفضلة والرسائل
$favorites = get_user_favorites($user_id);
$messages = get_user_messages($user_id);
$favorites_count = is_array($favorites) ? count($favorites) : 0;
$messages_count = is_array($messages) ? count($messages) : 0;

require_once __DIR__ . '/header.php';
?>

<h2>صفحة المستخدم</h2>
<section class="profile-info card-modern">
    <h3>معلومات الحساب</h3>
    <div class="profile-header">
        <div class="avatar">
            <?php $avatar = $user['avatar'] ? $user['avatar'] : 'placeholder.png'; ?>
            <img src="<?php echo htmlspecialchars($avatar); ?>" alt="صورة الحساب" class="avatar-img">
        </div>
        <div class="profile-meta">
            <p>اسم المستخدم: <?php echo htmlspecialchars($user['username']); ?></p>
            <p>البريد الإلكتروني: <?php echo htmlspecialchars($user['email']); ?></p>
            <p>رقم الجوال: <?php echo htmlspecialchars($user['phone']); ?></p>
            <p>تاريخ التسجيل: <?php echo htmlspecialchars($user['created_at']); ?></p>
            <p>
                توثيق الهوية (نفاذ):
                <?php if ((int)($user['is_verified'] ?? 0) === 1): ?>
                    <span class="badge badge-success">موثّق</span>
                <?php else: ?>
                    <span class="badge badge-warning">غير موثّق</span>
                    <a class="btn-modern btn-compact" href="verify_nafath.php" style="margin-inline-start:8px;">توثيق عبر نفاذ</a>
                <?php endif; ?>
            </p>
            <p>
                توثيق البريد:
                <?php if ((int)($user['email_verified'] ?? 0) === 1): ?>
                    <span class="badge badge-success">مفعّل</span>
                <?php else: ?>
                    <span class="badge badge-warning">غير مفعّل</span>
                    <form method="POST" action="profile.php" style="display:inline">
                        <button type="submit" name="resend_email_ver" class="btn-modern btn-compact">إرسال رابط التفعيل</button>
                    </form>
                <?php endif; ?>
            </p>
        </div>
    </div>
</section>

<section class="edit-profile card-modern">
    <h3>تعديل الملف الشخصي</h3>
    <form method="POST" action="profile.php" enctype="multipart/form-data" class="profile-edit-form form-modern">
        <div class="form-row">
            <div class="form-group">
                <label for="username">اسم المستخدم:</label>
                <input type="text" name="username" id="username" class="input-modern" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            <div class="form-group">
                <label for="phone">رقم الجوال:</label>
                <input type="text" name="phone" id="phone" class="input-modern" value="<?php echo htmlspecialchars($user['phone']); ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="avatar">تغيير صورتك:</label>
                <input type="file" name="avatar" id="avatar" accept="image/*" class="input-modern">
                <small class="form-hint">الأنواع المسموحة: jpg, jpeg, png, gif, webp</small>
            </div>
        </div>
        <button type="submit" name="edit_profile" class="btn-modern">حفظ التعديلات</button>
    </form>
</section>

<section class="favorites-section card-modern">
    <h3>إعلانات المفضلة (<?php echo (int)$favorites_count; ?>)</h3>
    <?php if (!$favorites): ?>
        <p>لا توجد إعلانات في المفضلة.</p>
    <?php else: ?>
        <div class="listings-grid modern-grid">
            <?php foreach ($favorites as $ad): ?>
                <div class="listing-card card-modern">
                    <?php
                    $images = get_ad_images($ad['id']);
                    $img = $images ? $images[0]['image_path'] : 'placeholder.png';
                    ?>
                    <a href="ad_details.php?id=<?php echo $ad['id']; ?>">
                        <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" class="listing-image">
                    </a>
                    <div class="ad-info">
                        <h3 class="listing-title"><a href="ad_details.php?id=<?php echo $ad['id']; ?>"><?php echo htmlspecialchars($ad['title']); ?></a></h3>
                        <p class="listing-price"><?php echo number_format($ad['price'], 2); ?> ر.س</p>
                        <p class="listing-category">قسم: <?php echo htmlspecialchars($ad['category_name']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="messages-section card-modern">
    <h3>الرسائل الواردة (<?php echo (int)$messages_count; ?>)</h3>
    <?php if (!$messages): ?>
        <p>لا توجد رسائل.</p>
    <?php else: ?>
        <ul class="messages-list">
            <?php foreach ($messages as $msg): ?>
                <li class="card-modern">
                    <strong>من:</strong> <?php echo htmlspecialchars($msg['sender_name']); ?> <br>
                    <strong>الإعلان:</strong> <a href="ad_details.php?id=<?php echo $msg['ad_id']; ?>"><?php echo htmlspecialchars($msg['ad_title']); ?></a><br>
                    <strong>الرسالة:</strong> <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                    <span class="timestamp"> (<?php echo htmlspecialchars($msg['created_at']); ?>)</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php
require_once __DIR__ . '/footer.php';