<?php
/**
 * صفحة عرض محادثة واحدة بين مستخدمين حول إعلان معين.
 * تسمح للمستخدم بقراءة الرسائل المتبادلة والرد عليها.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// تأكد من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$current_user = (int)$_SESSION['user_id'];

// الحصول على معرف المستخدم الآخر واختياريًا معرف الإعلان
$page_error = '';
$inline_errors = [];
$redirect_to = null;

if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    $page_error = 'محادثة غير صالحة.';
}
$other_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
// ad_id إجباري فقط إذا تم تمريره في الرابط
$ad_id = 0;
if (isset($_GET['ad_id']) && is_numeric($_GET['ad_id'])) {
    $ad_id = (int)$_GET['ad_id'];
}

// منع المستخدم من فتح محادثة مع نفسه
if (!$page_error && $other_id === $current_user) {
    $page_error = 'لا يمكن فتح محادثة مع نفسك.';
}

// جلب اسم الطرف الآخر
$other_name = '';
if (!$page_error) {
    $stmtUser = $pdo->prepare('SELECT username FROM users WHERE id = :id');
    $stmtUser->execute(['id' => $other_id]);
    $other_name = $stmtUser->fetchColumn();
}

// عند إرسال رسالة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$page_error) {
    $content = isset($_POST['message']) ? trim($_POST['message']) : '';
    // تحديد الإعلان المستخدم: من URL أو من الحقل في النموذج
    $ad_id_use = 0;
    if ($ad_id > 0) {
        $ad_id_use = $ad_id;
    } elseif (isset($_POST['ad_id']) && is_numeric($_POST['ad_id'])) {
        $ad_id_use = (int)$_POST['ad_id'];
        // تحقق أن الإعلان يخص أحد الطرفين لسلامة المنطق
        $stmtChk = $pdo->prepare('SELECT COUNT(*) FROM ads WHERE id = :ad AND (user_id = :u1 OR user_id = :u2)');
        $stmtChk->execute(['ad' => $ad_id_use, 'u1' => $current_user, 'u2' => $other_id]);
        if ((int)$stmtChk->fetchColumn() === 0) {
            $inline_errors[] = 'إعلان غير صالح لهذه المحادثة.';
            $ad_id_use = 0;
        }
    }

    if ($ad_id_use === 0) {
        $inline_errors[] = 'يرجى اختيار إعلان لربط الرسالة.';
    } elseif ($content === '' && empty($_FILES['attachments']['name'][0])) {
        $inline_errors[] = 'يرجى كتابة رسالة أو إرفاق ملف.';
    } else {
        // إنشاء الرسالة أولاً
        $ok = send_message($current_user, $other_id, $ad_id_use, $content);
        if ($ok) {
            // احصل على معرف الرسالة الأخيرة المدخلة
            $message_id = $pdo->lastInsertId();
            // معالجة المرفقات إن وجدت
            if (!empty($_FILES['attachments']['name'][0])) {
                // تأكد من مسار التخزين
                $uploadBase = __DIR__ . '/uploads/messages';
                if (!is_dir($uploadBase)) {
                    @mkdir($uploadBase, 0777, true);
                }
                // مجلد لكل محادثة حسب ad_id و user ids لمزيد من التنظيم
                $convDir = $uploadBase . '/' . (int)$ad_id_use;
                if (!is_dir($convDir)) { @mkdir($convDir, 0777, true); }

                $names = $_FILES['attachments']['name'];
                $tmpNames = $_FILES['attachments']['tmp_name'];
                $sizes = $_FILES['attachments']['size'];
                $types = $_FILES['attachments']['type'];
                $maxSize = 10 * 1024 * 1024; // 10MB
                $allowed = [
                    'image/jpeg','image/png','image/gif','image/webp','image/bmp','image/svg+xml',
                    'application/pdf',
                    'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/zip','application/x-zip-compressed'
                ];
                $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
                for ($i = 0; $i < count($names); $i++) {
                    if (!is_uploaded_file($tmpNames[$i])) continue;
                    if ($sizes[$i] > $maxSize) continue;
                    $mime = $types[$i];
                    if ($finfo) {
                        $det = finfo_file($finfo, $tmpNames[$i]);
                        if ($det) { $mime = $det; }
                    }
                    if (!in_array($mime, $allowed, true)) continue;
                    $orig = basename($names[$i]);
                    $ext = pathinfo($orig, PATHINFO_EXTENSION);
                    $safe = uniqid('att_', true) . ($ext ? ('.' . $ext) : '');
                    $destPath = $convDir . '/' . $safe;
                    if (@move_uploaded_file($tmpNames[$i], $destPath)) {
                        $publicPath = 'uploads/messages/' . (int)$ad_id_use . '/' . $safe;
                        save_message_attachment((int)$message_id, $publicPath, (string)$mime, (int)$sizes[$i]);
                    }
                }
                if ($finfo) { finfo_close($finfo); }
            }
            $redir = 'conversation.php?user_id=' . $other_id;
            if ($ad_id_use > 0) { $redir .= '&ad_id=' . $ad_id_use; }
            $redirect_to = $redir;
        } else {
            $inline_errors[] = 'حدث خطأ أثناء الإرسال.';
        }
    }
}

// إذا كان هناك إعادة توجيه، نفذها قبل أي إخراج
if ($redirect_to) {
    header('Location: ' . $redirect_to);
    exit;
}

// إذا كان معرف الإعلان موجوداً، نجلب عنوان الإعلان
$ad_title = '';
if ($ad_id > 0 && !$page_error) {
    $stmtAd = $pdo->prepare('SELECT title FROM ads WHERE id = :id');
    $stmtAd->execute(['id' => $ad_id]);
    $ad_title = $stmtAd->fetchColumn();
}

// جلب الرسائل: إما لجميع الرسائل مع هذا المستخدم أو المرتبطة بإعلان محدد
if (!$page_error) {
    if ($ad_id > 0) {
        $messages = get_conversation_messages_with_attachments($current_user, $other_id, $ad_id);
    } else {
        $messages = get_user_conversation_messages_with_attachments($current_user, $other_id);
    }
} else {
    $messages = [];
}
?>
<?php require_once __DIR__ . '/header.php'; ?>
<?php
// عرض أخطاء الصفحة أعلى المحتوى إن وجدت
if ($page_error) {
    echo '<div class="container" style="padding:12px 16px;color:#fff;background:#0b1a2a">';
    echo '<p style="margin:8px 0;color:#ffb3b3">' . htmlspecialchars($page_error) . '</p>';
    echo '</div>';
}
if (!empty($inline_errors)) {
    echo '<div class="container" style="padding:8px 16px">';
    foreach ($inline_errors as $err) {
        echo '<p class="error" style="color:#ffb3b3;margin:6px 0">' . htmlspecialchars($err) . '</p>';
    }
    echo '</div>';
}
?>
<style>
/* صفحة محادثة حديثة مستوحاة من التصميم */
.chat-page {
    --bg1: #0b1a2a;
    --bg2: #0f2032;
    --bubble-blue: linear-gradient(135deg,#1a4fb3,#0e6bd8);
    --bubble-dark: rgba(255,255,255,0.08);
    --text: #e6f1ff;
    --muted: #9fb3c8;
    --blur: 16px;
    color-scheme: dark; /* اجعل عناصر النموذج الداكنة افتراضياً */
    background: radial-gradient(1000px 800px at 20% 0%, var(--bg2), var(--bg1));
    min-height: 70vh;
    padding: 12px 0 0;
}
.chat-container { max-width: 760px; margin: 0 auto; padding: 0 16px; direction: rtl; }
.chat-header { text-align: center; color: #fff; font-size: 2rem; font-weight: 700; margin: 8px 0 8px; }
.chat-sub { text-align: center; color: var(--muted); margin-bottom: 8px; }
.conversation-thread { height: 60vh; max-height: 60vh; overflow-y: auto; padding: 8px 4px 12px; }
@media (max-width: 600px) { .conversation-thread { height: 62vh; max-height: 62vh; } }

.message-row { display: flex; margin: 10px 0; }
.message-row.mine { justify-content: flex-end; }
.message-row.theirs { justify-content: flex-start; }
.message-bubble { position: relative; color: var(--text); padding: 12px 14px; max-width: 78%; border-radius: 18px; backdrop-filter: blur(var(--blur)); }
.message-row.mine .message-bubble { background: var(--bubble-blue); border-radius: 18px 18px 4px 18px; box-shadow: 0 8px 18px rgba(14,107,216,0.25); }
.message-row.theirs .message-bubble { background: var(--bubble-dark); border: 1px solid rgba(255,255,255,0.06); border-radius: 18px 18px 18px 4px; }
.message-bubble .timestamp { display: block; margin-top: 6px; font-size: 12px; color: rgba(230,241,255,0.55); text-align: left; }
.message-row.mine .message-bubble .timestamp { text-align: left; }
.message-row.theirs .message-bubble .timestamp { text-align: right; }
.message-bubble .attachments { margin-top: 8px; display: grid; grid-template-columns: repeat(auto-fill, minmax(120px,1fr)); gap: 6px; }
.message-bubble .attachments img { width: 100%; height: 120px; object-fit: cover; border-radius: 12px; border: 1px solid rgba(255,255,255,0.12); }
.file-attachment { color: #7fb3ff; text-decoration: none; display: inline-block; padding: 4px 6px; background: rgba(127,179,255,0.08); border-radius: 8px; }

/* الشريط السفلي (المُرسِل) */
.composer { position: sticky; bottom: 0; z-index: 2; padding: 10px 0 16px; background: linear-gradient(to top, rgba(11,26,42,0.95), rgba(11,26,42,0.6) 40%, rgba(11,26,42,0)); backdrop-filter: blur(8px); }
/* إزالة أي خلفية بيضاء افتراضية على النموذج في بعض المتصفحات */
.chat-container .message-form { background: transparent !important; border: 0 !important; box-shadow: none !important; }
.ad-picker { margin-bottom: 8px; color: var(--muted); }
.ad-picker select { width: 100%; padding: 10px 12px; border-radius: 10px; background: rgba(15,32,50,0.85); color: var(--text); border: 1px solid rgba(255,255,255,0.12); -webkit-appearance: none; appearance: none; }
.ad-picker select:focus { outline: none; border-color: rgba(143,186,255,0.6); box-shadow: 0 0 0 3px rgba(143,186,255,0.15); }
.ad-picker label { color: var(--muted); }
.ad-picker select option { background: #0f2032; color: var(--text); }
.composer-row { display: grid; grid-template-columns: 1fr auto auto; gap: 10px; align-items: center; }
.composer-row .input-modern { width: 100%; resize: none; min-height: 48px; max-height: 140px; padding: 12px 14px; border-radius: 999px; background: rgba(15,32,50,0.85); border: 1px solid rgba(255,255,255,0.12); color: var(--text); outline: none; -webkit-appearance: none; appearance: none; }
.composer-row .input-modern::placeholder { color: rgba(230,241,255,0.58); }
.composer-row .input-modern:focus { border-color: rgba(143,186,255,0.6); box-shadow: 0 0 0 3px rgba(143,186,255,0.15); }
.btn-icon { width: 48px; height: 48px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.18); display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.06); color: #fff; font-size: 22px; cursor: pointer; }
.btn-icon.plus { background: linear-gradient(135deg,#11468f,#0e6bd8); }
.btn-icon.send { background: linear-gradient(135deg,#1a4fb3,#0e6bd8); }
.btn-icon:hover { filter: brightness(1.05); }
.hidden-file { display: none; }

/* حالة لا توجد رسائل */
.empty-thread { text-align: center; color: var(--muted); padding: 16px 0; }
@media (max-width: 720px) {
    /* Lift the composer above the fixed bottom nav (56px) and support notches */
    .composer { bottom: calc(56px + env(safe-area-inset-bottom)); padding-bottom: calc(16px + env(safe-area-inset-bottom)); }
}
</style>

<div class="chat-page">
    <div class="chat-container">
        <div class="chat-header"><?php echo htmlspecialchars($other_name); ?></div>
        <?php if ($ad_id > 0): ?>
            <div class="chat-sub"><strong>الإعلان:</strong> <a href="ad_details.php?id=<?php echo $ad_id; ?>" style="color:#8fbaff; text-decoration:none;"><?php echo htmlspecialchars($ad_title); ?></a></div>
        <?php endif; ?>

        <?php if (!$messages): ?>
            <div class="empty-thread">لا توجد رسائل بعد.</div>
        <?php else: ?>
            <div class="conversation-thread" id="thread">
                <?php foreach ($messages as $msg): ?>
                    <?php $isMine = ($msg['from_user_id'] == $current_user); ?>
                    <div class="message-row <?php echo $isMine ? 'mine' : 'theirs'; ?>">
                        <div class="message-bubble">
                            <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                            <?php if (!empty($msg['attachments'])): ?>
                                <div class="attachments">
                                    <?php foreach ($msg['attachments'] as $att): ?>
                                        <?php $mime = strtolower($att['mime_type']); ?>
                                        <?php if (strpos($mime, 'image/') === 0): ?>
                                            <a href="<?php echo htmlspecialchars($att['file_path']); ?>" target="_blank">
                                                <img src="<?php echo htmlspecialchars($att['file_path']); ?>" alt="attachment" />
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo htmlspecialchars($att['file_path']); ?>" target="_blank" class="file-attachment">
                                                تنزيل الملف (<?php echo htmlspecialchars(basename($att['file_path'])); ?>)
                                            </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <span class="timestamp"><?php echo htmlspecialchars($msg['created_at']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <section class="composer">
            <?php $actionUrl = 'conversation.php?user_id=' . $other_id . ($ad_id > 0 ? ('&ad_id=' . $ad_id) : ''); ?>
            <form method="POST" enctype="multipart/form-data" action="<?php echo $actionUrl; ?>" class="message-form">
                <?php if ($ad_id > 0): ?>
                    <input type="hidden" name="ad_id" value="<?php echo (int)$ad_id; ?>" />
                <?php else: ?>
                    <?php
                        $stmtAds = $pdo->prepare('SELECT id, title FROM ads WHERE user_id = :u1 OR user_id = :u2 ORDER BY created_at DESC');
                        $stmtAds->execute(['u1' => $current_user, 'u2' => $other_id]);
                        $adsOptions = $stmtAds->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="ad-picker">
                        <label for="ad_id_select">اختر الإعلان لربط الرسائل:</label>
                        <select id="ad_id_select" name="ad_id" required>
                            <option value="">— اختر —</option>
                            <?php foreach ($adsOptions as $opt): ?>
                                <option value="<?php echo (int)$opt['id']; ?>"><?php echo htmlspecialchars($opt['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="composer-row">
                    <textarea name="message" rows="1" placeholder="اكتب رسالة..." class="input-modern" id="msgBox"></textarea>
                    <input id="attInput" class="hidden-file" type="file" name="attachments[]" multiple accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/x-zip-compressed" />
                    <label for="attInput" class="btn-icon plus" title="إرفاق ملفات">+</label>
                    <button type="submit" class="btn-icon send" title="إرسال">➤</button>
                </div>
            </form>
        </section>
    </div>
</div>

<script>
// Scroll to bottom of thread on load
document.addEventListener('DOMContentLoaded', function() {
    var thread = document.getElementById('thread');
    if (thread) { thread.scrollTop = thread.scrollHeight; }
    var box = document.getElementById('msgBox');
    if (box) {
        box.addEventListener('keydown', function(e){
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                // submit the form
                var form = this.closest('form');
                if (form) form.submit();
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>