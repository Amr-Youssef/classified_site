<?php
// حمّل الإعدادات والدوال أولاً قبل أي إخراج HTML لتفادي تحذير headers already sent
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// الحصول على معرف الإعلان
$page_error = '';
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $page_error = 'إعلان غير صالح.';
}
$ad_id = (int) $_GET['id'];
// جلب بيانات الإعلان
$ad = ($page_error === '') ? get_ad($ad_id) : null;
if ($page_error === '' && !$ad) {
    $page_error = 'الإعلان غير موجود.';
}

// جلب الصور
$images = get_ad_images($ad_id);

// جلب معلومات البائع (البريد والهاتف) لعرضها فى قسم التواصل. إذا حدث خطأ
// فى الاستعلام، سيتم تجاهل القسم بالكامل.
$seller = null;
try {
    $stmtSeller = $pdo->prepare('SELECT username, email, phone FROM users WHERE id = :id');
    $stmtSeller->execute(['id' => $ad['user_id']]);
    $seller = $stmtSeller->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $seller = null;
}

// إضافة إلى المفضلة إذا تم الطلب
$favorite_message = '';
if (isset($_GET['favorite']) && isset($_SESSION['user_id'])) {
    if (add_favorite($_SESSION['user_id'], $ad_id)) {
        $favorite_message = 'تمت إضافة الإعلان إلى المفضلة!';
    } else {
        $favorite_message = 'هذا الإعلان موجود بالفعل في المفضلة.';
    }
}

// معالجة إرسال رسالة
$message_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && isset($_SESSION['user_id'])) {
    $message_content = trim($_POST['message']);
    if ($message_content) {
        $res = send_message($_SESSION['user_id'], $ad['user_id'], $ad_id, $message_content);
        if ($res) {
            $message_success = 'تم إرسال رسالتك بنجاح!';
        } else {
            $message_success = 'حدث خطأ أثناء الإرسال. حاول مرة أخرى.';
        }
    }
}

// معالجة إضافة تعليق
$comment_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment']) && isset($_SESSION['user_id'])) {
    $comment_content = trim($_POST['comment']);
    if ($comment_content) {
        $res = add_comment($ad_id, $_SESSION['user_id'], $comment_content);
        if ($res) {
            $comment_success = 'تم إضافة تعليقك.';
        } else {
            $comment_success = 'حدث خطأ أثناء إضافة التعليق.';
        }
    }
}

// معالجة حذف تعليق (من صاحبه أو من صاحب الإعلان كمشرف بسيط)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment']) && isset($_POST['delete_comment_id'])) {
    $delId = (int)$_POST['delete_comment_id'];
    // إذا كان المستخدم هو صاحب الإعلان أو صاحب التعليق
    if (isset($_SESSION['user_id'])) {
        // حاول حذف بصلاحية المستخدم
        delete_comment($delId, $_SESSION['user_id']);
    }
    header('Location: ad_details.php?id=' . $ad_id);
    exit;
}

// بدء تعديل: نحتاج أن نعرض نموذج التعديل (handled later inline)
$editingCommentId = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_edit']) && isset($_POST['edit_comment_id'])) {
    $editingCommentId = (int)$_POST['edit_comment_id'];
}

// تقديم تعديل التعليق
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_edit']) && isset($_POST['edit_id']) && isset($_POST['edit_content']) && isset($_SESSION['user_id'])) {
    $editId = (int)$_POST['edit_id'];
    $newContent = trim($_POST['edit_content']);
    edit_comment($editId, $_SESSION['user_id'], $newContent);
    header('Location: ad_details.php?id=' . $ad_id);
    exit;
}

// قبول التعليق من قبل صاحب الإعلان (كمشرف محلي)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_comment_id']) && isset($_SESSION['user_id'])) {
    $approveId = (int)$_POST['approve_comment_id'];
    // فقط صاحب الإعلان يمكنه الموافقة
    if (!empty($ad) && $_SESSION['user_id'] === (int)$ad['user_id']) {
        approve_comment($approveId);
    }
    header('Location: ad_details.php?id=' . $ad_id);
    exit;
}

// طلب تمييز: إنشاء ترقية ثم تحويل لبوابة الدفع
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_ad']) && isset($_SESSION['user_id'])) {
    if (!empty($ad) && (int)$_SESSION['user_id'] === (int)$ad['user_id']) {
        $days = isset($_POST['days']) ? max(1, (int)$_POST['days']) : 7;
        $promo_id = create_promotion($ad_id, (int)$_SESSION['user_id'], $days, PAYMENT_PROVIDER);
        $start = start_payment_for_promotion($promo_id);
        if (!empty($start['redirect'])) {
            header('Location: ' . $start['redirect']);
            exit;
        }
    }
    header('Location: ad_details.php?id=' . $ad_id);
    exit;
}
// بعد معالجة أي تحويلات، اعرض الترويسة ثم المحتوى
require_once __DIR__ . '/header.php';
if ($page_error !== '') {
    echo '<p class="error">' . htmlspecialchars($page_error) . '</p>';
    require_once __DIR__ . '/footer.php';
    exit;
}
?>

<style>
/* تخطيط عام لصفحة تفاصيل الإعلان - بطاقة موحدة نظيفة */
.ad-details-wrapper {
    max-width: 900px;
    margin: 28px auto;
    padding: 0 16px;
}
.detail-card {
    padding: 22px 18px;
    border-radius: 24px;
    background: var(--card-bg, rgba(255,255,255,0.06));
    border: 1px solid var(--card-border, rgba(255,255,255,0.12));
    box-shadow: 0 12px 32px rgba(0,0,0,0.20);
}
.detail-section { padding: 14px 8px; }
.detail-divider { height: 1px; background: rgba(255,255,255,0.10); margin: 8px 0 12px; border: 0; }

/* العنوان وبيانات الميتا */
.ad-title { margin: 0 0 6px 0; font-size: 2.2rem; color: #fff; letter-spacing: .3px; }
.ad-meta  { color: #cfd8dc; margin: 0; font-size: 0.98rem; }

/* تحسين بناء معرض الصور */
.ad-gallery { display: grid; gap: 12px; padding: 12px; border-radius: 16px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); }
.ad-gallery .main-image img { width: 100%; height: 360px; object-fit: cover; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.15); }
@media (max-width: 900px) { .ad-gallery .main-image img { height: 240px; } }
.ad-gallery .thumbs { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; }
.ad-gallery .thumbs .thumb-img { width: 100%; height: 90px; object-fit: cover; border-radius: 10px; border: 1px solid var(--card-border, rgba(255,255,255,.12)); }
.no-image-card { display: flex; align-items: center; justify-content: center; flex-direction: column; height: 220px; border: 1px dashed rgba(255,255,255,0.25); border-radius: 12px; color: #ccc; background: rgba(255,255,255,0.04); }
.no-image-card .no-image-icon { font-size: 40px; margin-bottom: 8px; color: #aaa; }

/* لمسة نظافة إضافية على شريط الميتا */
.ad-meta { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); padding: 8px 12px; border-radius: 10px; display: inline-block; }

/* الوصف داخل بطاقة حديثة */
.ad-description { }

.ad-contact { }
.ad-actions { display: flex; flex-direction: column; gap: 10px; }
.ad-actions .btn { display: inline-block; }
.message-form-section { }

/* التعليقات */
.comments-section { }
.comments-section h3 { margin-top: 0; }

/* معلومات التواصل أسفل الصفحة */
.contact-info { color: #b0bec5; }

/* لمسات جمالية عامة متناسقة مع النمط الحديث */
.card-modern { backdrop-filter: blur(var(--blur-amount, 15px)) saturate(180%); background: var(--card-bg, rgba(255,255,255,.06)); border: 1px solid var(--card-border, rgba(255,255,255,.15)); border-radius: 16px; }
.badge-premium { display: inline-block; margin-right: 8px; padding: 4px 10px; border-radius: 999px; background: linear-gradient(135deg,#f7b733,#fc4a1a); color:#fff; font-size: 12px; vertical-align: middle; }
.meta-chip { display:inline-block; margin-inline-start:8px; padding:4px 8px; border-radius:8px; background: rgba(255,255,255,.08); color:#ffd700; font-weight:600; font-size:12px; }
.section-title { font-size: 1.25rem; margin: 0 0 8px; color: #fff; }
.comment-item { display: flex; gap: 12px; }
.comment-avatar { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); }
.comment-content { flex: 1; }
</style>

<div class="ad-details-wrapper">

<div class="detail-card card-modern">

<h2 class="ad-title">
        <?php if (!empty($ad['is_premium'])): ?><span class="badge-premium">مميز</span><?php endif; ?>
        <?php echo htmlspecialchars($ad['title']); ?>
</h2>
<p class="ad-meta">قسم: <?php echo htmlspecialchars($ad['category_name']); ?> | السعر: <?php echo number_format($ad['price'], 2); ?> ر.س | الحالة: <?php echo htmlspecialchars($ad['condition'] === 'new' ? 'جديد' : 'مستعمل'); ?></p>
<hr class="detail-divider" />
<?php if ($favorite_message): ?>
    <p class="success"><?php echo $favorite_message; ?></p>
<?php endif; ?>
<?php /* تمت إزالة نموذج المراسلة من صفحة التفاصيل، لذلك لا حاجة لإظهار رسالة نجاح الإرسال هنا */ ?>

<!-- صور الإعلان -->
<div class="ad-gallery card-modern detail-section">
    <?php if ($images && count($images) > 0): ?>
        <?php $mainImage = $images[0]['image_path']; ?>
        <div class="main-image">
            <img src="<?php echo htmlspecialchars($mainImage); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" />
        </div>
        <?php if (count($images) > 1): ?>
            <div class="thumbs">
                <?php foreach (array_slice($images, 1) as $img): ?>
                    <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" class="thumb-img" />
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="no-image-card">
            <div class="no-image-icon">🖼️</div>
            <div class="no-image-text">لا توجد صورة لهذا الإعلان</div>
        </div>
    <?php endif; ?>
</div>

<div class="ad-description card-modern description-modern detail-section">
    <h3 class="section-title">الوصف</h3>
    <p><?php echo nl2br(htmlspecialchars($ad['description'])); ?></p>
</div>

<?php if ($seller && (!empty($seller['email']) || !empty($seller['phone']))): ?>
    <!-- قسم معلومات التواصل مع البائع -->
    <div class="ad-contact card-modern detail-section">
        <h3>التواصل</h3>
        <?php if (!empty($seller['email'])): ?>
            <p><strong>البريد:</strong> <?php echo htmlspecialchars($seller['email']); ?></p>
        <?php endif; ?>
        <?php if (!empty($seller['phone'])): ?>
            <p><strong>الهاتف:</strong> <?php echo htmlspecialchars($seller['phone']); ?></p>
        <?php endif; ?>
        <?php if (!isset($_SESSION['user_id'])): ?>
            <a class="btn btn-modern" href="login.php">سجّل الدخول لمراسلة المعلن</a>
        <?php elseif ((int)$_SESSION['user_id'] !== (int)$ad['user_id']): ?>
            <a class="btn btn-modern" href="conversation.php?ad_id=<?php echo $ad_id; ?>&user_id=<?php echo (int)$ad['user_id']; ?>">مراسلة المعلن</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- أزرار التفاعل -->
<div class="ad-actions card-modern detail-section" style="padding:12px;">
<?php if (!isset($_SESSION['user_id'])): ?>
        <a href="login.php" class="btn btn-modern">سجّل الدخول لإضافة للمفضلة</a>
    <?php elseif ($_SESSION['user_id'] !== $ad['user_id']): ?>
        <a href="ad_details.php?id=<?php echo $ad_id; ?>&favorite=1" class="btn btn-modern">إضافة إلى المفضلة</a>
    <?php else: ?>
        <a href="add_ad.php?edit=<?php echo $ad_id; ?>" class="btn btn-modern">هذا إعلانك - تعديل الإعلان</a>
        <form method="POST" action="ad_details.php?id=<?php echo $ad_id; ?>" style="display:inline-flex; align-items:center; gap:8px; margin-inline-start:8px;">
            <input type="hidden" name="promote_ad" value="1">
            <select name="days" class="input-modern" style="max-width:160px;" onchange="updatePromoPrice(this)">
                <option value="7">7 أيام - <?php echo number_format(PROMO_PRICE_7, 2); ?> ر.س</option>
                <option value="14">14 يوماً - <?php echo number_format(PROMO_PRICE_14, 2); ?> ر.س</option>
                <option value="30">30 يوماً - <?php echo number_format(PROMO_PRICE_30, 2); ?> ر.س</option>
            </select>
            <button type="submit" class="btn btn-modern">الدفع للترقية</button>
        </form>
    <?php endif; ?>
</div>

<!-- تمت إزالة نموذج المراسلة، وتم استبداله بزر داخل قسم التواصل يوجّه إلى صفحة المحادثة -->

<!-- نموذج التعليقات -->
<section class="comments-section card-modern detail-section">
    <h3>التعليقات</h3>
    <?php if ($comment_success): ?><p class="success"><?php echo htmlspecialchars($comment_success); ?></p><?php endif; ?>
    <?php if (isset($_SESSION['user_id'])): ?>
        <form method="POST" action="ad_details.php?id=<?php echo $ad_id; ?>" class="comment-form form-modern">
            <textarea name="comment" rows="3" required placeholder="اكتب تعليقك..." class="input-modern"></textarea>
            <button type="submit" class="btn-modern">إضافة تعليق</button>
        </form>
    <?php else: ?>
        <p>سجل دخولك لتتمكن من إضافة تعليق.</p>
    <?php endif; ?>

    <?php
        $page = isset($_GET['cpage']) ? max(1, (int)$_GET['cpage']) : 1;
        $perPage = 10;
        $comments = get_ad_comments($ad_id, $page, $perPage, false);
        if (!$comments) {
            echo '<p>لا توجد تعليقات بعد.</p>';
        } else {
            echo '<ul class="comments-list">';
            foreach ($comments as $c) {
                echo '<li class="comment-item card-modern">';
                echo '<span class="comment-avatar">💬</span>';
                echo '<div class="comment-content">';
                echo '<strong>' . htmlspecialchars($c['username']) . '</strong>';
                echo '<span class="comment-time"> (' . htmlspecialchars($c['created_at']) . ')</span>';
                echo '<p>' . nl2br(htmlspecialchars($c['content'])) . '</p>';
                // أزرار التعديل/الحذف إن كان المعلق هو المستخدم الحالي
                if (isset($_SESSION['user_id']) && $_SESSION['user_id'] === (int)$c['user_id']) {
                    echo '<form method="POST" style="display:inline-block;" action="ad_details.php?id=' . $ad_id . '">';
                    echo '<input type="hidden" name="edit_comment_id" value="' . (int)$c['id'] . '">';
                    echo '<button type="submit" name="start_edit" class="btn-modern">تعديل</button>';
                    echo '</form> ';
                    echo '<form method="POST" style="display:inline-block;" action="ad_details.php?id=' . $ad_id . '">';
                    echo '<input type="hidden" name="delete_comment_id" value="' . (int)$c['id'] . '">';
                    echo '<button type="submit" name="delete_comment" class="btn-modern">حذف</button>';
                    echo '</form>';
                }
                echo '</div>';
                echo '</li>';
            }
            echo '</ul>';

            // روابط الصفحات البسيطة
            echo '<div class="comments-pager">';
            if ($page > 1) {
                $prev = $page - 1;
                echo '<a href="ad_details.php?id=' . $ad_id . '&cpage=' . $prev . '">السابق</a> ';
            }
            // محاولة عرض رابط التالي دائمًا (يمكن تحسينه بعد حساب العدد الكلي)
            $next = $page + 1;
            echo '<a href="ad_details.php?id=' . $ad_id . '&cpage=' . $next . '">التالي</a>';
            echo '</div>';
        }
    ?>
</section>

<p class="contact-info detail-section"><strong>اسم المعلن:</strong> <?php echo htmlspecialchars($ad['username']); ?>
    | <strong>البريد الإلكتروني:</strong> <?php echo htmlspecialchars($ad['email']); ?>
    <?php if (!empty($ad['phone'])):
        $telHref = format_phone_display($ad['phone'], true);
        $display = format_phone_display($ad['phone'], false);
    ?>
        | <strong>رقم الجوال:</strong> <a href="tel:<?php echo htmlspecialchars($telHref); ?>"><?php echo htmlspecialchars($display); ?></a>
    <?php endif; ?>
</p>

</div><!-- /.detail-card -->

</div>

<script>
// تبديل الصورة الرئيسية عند الضغط على المصغرات (واجهة فقط)
document.addEventListener('DOMContentLoaded', function() {
    var main = document.querySelector('.ad-gallery .main-image img');
    var thumbs = document.querySelectorAll('.ad-gallery .thumbs .thumb-img');
    if (main && thumbs.length) {
        thumbs.forEach(function(th) {
            th.addEventListener('click', function() {
                var src = th.getAttribute('src');
                if (src) main.setAttribute('src', src);
            });
        });
    }
});
</script>

<script>
function updatePromoPrice(sel){
    // السعر ظاهر داخل الخيار ذاته؛ لا حاجة لتغيير زر. يمكن لاحقاً عرض السعر بشكل مستقل.
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>