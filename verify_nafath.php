<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';
$requestId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $national_id = trim($_POST['national_id'] ?? '');
    if ($national_id === '' || !preg_match('/^\d{10}$/', $national_id)) {
        $error = 'الرجاء إدخال رقم هوية وطني صحيح (10 أرقام).';
    } else {
        $res = nafath_create_request($pdo, (int)$_SESSION['user_id'], $national_id);
        if (!empty($res['ok'])) {
            $requestId = (string)($res['request_id'] ?? '');
        } else {
            $error = 'تعذر بدء طلب التحقق. حاول لاحقًا.';
        }
    }
}

require_once __DIR__ . '/header.php';
?>
<style>
.vf-card{max-width:640px;margin:40px auto;background:#111a22;border:1px solid #1f2a36;border-radius:14px;padding:24px;color:#e5eef7}
.vf-card h1{margin-top:0;font-size:22px}
.vf-card .row{display:flex;gap:12px;flex-wrap:wrap}
.vf-card input{width:100%;padding:12px 14px;border-radius:10px;border:1px solid #2a3b4b;background:#0d141b;color:#e5eef7}
.vf-card button{background:#2d7cff;border:none;color:#fff;padding:10px 16px;border-radius:10px;cursor:pointer}
.vf-hint{font-size:13px;color:#9fb3c8}
.vf-status{margin-top:16px;padding:12px;border-radius:10px;background:#0d141b;border:1px dashed #294055}
</style>

<div class="vf-card">
    <h1>توثيق الحساب عبر نفاذ</h1>
    <?php if (!empty($error)): ?>
        <div class="vf-status" style="color:#ff9b9b;border-color:#804545;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($requestId === ''): ?>
        <form method="post" action="">
            <div class="row">
                <input name="national_id" inputmode="numeric" autocomplete="off" placeholder="رقم الهوية الوطنية (10 أرقام)" maxlength="10" required />
                <button type="submit">بدء التحقق</button>
            </div>
            <p class="vf-hint">سيتم إرسال إشعار إلى تطبيق نفاذ للموافقة.</p>
        </form>
    <?php else: ?>
        <div class="vf-status" id="statusBox">تم إنشاء الطلب. رقم الطلب: <strong><?php echo htmlspecialchars($requestId); ?></strong><br>يرجى فتح تطبيق نفاذ والموافقة. نتحقق من الحالة تلقائيًا...</div>
        <script>
        (function(){
            const req = <?php echo json_encode($requestId); ?>;
            let tries = 0;
            const box = document.getElementById('statusBox');
            const tick = () => {
                fetch('nafath_status.php?rid=' + encodeURIComponent(req), {headers:{'Accept':'application/json'}})
                .then(r => r.json())
                .then(d => {
                    tries++;
                    if (!d || !d.status){ return; }
                    if (d.status === 'approved'){
                        box.innerHTML = 'تم التوثيق بنجاح. سيتم تحويلك للملف الشخصي...';
                        setTimeout(()=>{ window.location.href='profile.php'; }, 1000);
                    } else if (d.status === 'rejected') {
                        box.innerHTML = 'تم رفض الطلب. يمكنك المحاولة مرة أخرى.';
                    } else if (d.status === 'expired') {
                        box.innerHTML = 'انتهت صلاحية الطلب. ابدأ طلبًا جديدًا.';
                    } else {
                        if (tries < 60) setTimeout(tick, 2000);
                    }
                }).catch(()=>{ if (tries < 60) setTimeout(tick, 3000); });
            };
            setTimeout(tick, 1500);
        })();
        </script>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>