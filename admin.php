<?php
/**
 * لوحة الإدارة: تسمح للمشرفين بإدارة المستخدمين والإعلانات والتعليقات والتصنيفات.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// تحقق من تسجيل الدخول وصلاحية المشرف
if (!isset($_SESSION['user_id']) || !is_admin_user($_SESSION['user_id'])) {
    require_once __DIR__ . '/header.php';
    echo '<h2>غير مصرح بالدخول</h2><p>هذه الصفحة مخصصة للمشرفين فقط.</p>';
    require_once __DIR__ . '/footer.php';
    exit;
}

$admin_id = (int)$_SESSION['user_id'];
$messages = ['success' => [], 'error' => []];

// معالجة الإجراءات (حذف مستخدم، حذف إعلان، حذف/قبول تعليق)
// ترقية مستخدم إلى أدمن
if (isset($_GET['promote_user']) && is_numeric($_GET['promote_user'])) {
    $uid = (int)$_GET['promote_user'];
    // لا تسمح بترقية نفسك إذا كنت بالفعل مديراً
    if ($uid > 0 && $uid != $admin_id) {
        $stmt = $pdo->prepare('UPDATE users SET is_admin = 1 WHERE id = :id');
        $stmt->execute(['id' => $uid]);
    }
    header('Location: admin.php');
    exit;
}

// إزالة صلاحية الأدمن لمستخدم
if (isset($_GET['demote_user']) && is_numeric($_GET['demote_user'])) {
    $uid = (int)$_GET['demote_user'];
    // لا تسمح للمشرف بحذف صلاحية نفسه تفادياً لفقدان الوصول
    if ($uid > 0 && $uid != $admin_id) {
        $stmt = $pdo->prepare('UPDATE users SET is_admin = 0 WHERE id = :id');
        $stmt->execute(['id' => $uid]);
    }
    header('Location: admin.php');
    exit;
}
// حذف مستخدم
if (isset($_GET['delete_user']) && is_numeric($_GET['delete_user'])) {
    $uid = (int)$_GET['delete_user'];
    // لا تسمح للمشرف بحذف نفسه تفادياً لفقدان الصلاحية
    if ($uid > 0 && $uid != $admin_id) {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $uid]);
        // بعد الحذف، أعد التوجيه لإزالة متغير GET من الرابط
        header('Location: admin.php');
        exit;
    }
}

// حذف إعلان
if (isset($_GET['delete_ad']) && is_numeric($_GET['delete_ad'])) {
    $adid = (int)$_GET['delete_ad'];
    $stmt = $pdo->prepare('DELETE FROM ads WHERE id = :id');
    $stmt->execute(['id' => $adid]);
    header('Location: admin.php');
    exit;
}

// حذف تعليق (حذف منطقي)
if (isset($_GET['delete_comment']) && is_numeric($_GET['delete_comment'])) {
    $cid = (int)$_GET['delete_comment'];
    delete_comment($cid);
    header('Location: admin.php');
    exit;
}

// قبول تعليق
if (isset($_GET['approve_comment']) && is_numeric($_GET['approve_comment'])) {
    $cid = (int)$_GET['approve_comment'];
    approve_comment($cid);
    header('Location: admin.php');
    exit;
}

// ---------------- إدارة التصنيفات (الأقسام) ----------------
// إضافة قسم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['cat_name'] ?? '');
    $parent_id = isset($_POST['cat_parent']) && $_POST['cat_parent'] !== '' ? (int)$_POST['cat_parent'] : null;
    if ($name === '') {
        $messages['error'][] = 'يرجى إدخال اسم القسم.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO categories (parent_id, name) VALUES (:parent_id, :name)');
        $stmt->execute(['parent_id' => $parent_id, 'name' => $name]);
        $messages['success'][] = 'تمت إضافة القسم بنجاح.';
    }
}

// تحديث قسم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $id = (int)($_POST['cat_id'] ?? 0);
    $name = trim($_POST['cat_name'] ?? '');
    $parent_id = isset($_POST['cat_parent']) && $_POST['cat_parent'] !== '' ? (int)$_POST['cat_parent'] : null;
    if ($id <= 0 || $name === '') {
        $messages['error'][] = 'بيانات غير مكتملة لتعديل القسم.';
    } else {
        // منع جعل القسم أباً لنفسه أو لأحد أحفاده
        $cats = get_categories();
        $childrenMap = [];
        foreach ($cats as $c) {
            $pid = (int)($c['parent_id'] ?? 0);
            if (!isset($childrenMap[$pid])) $childrenMap[$pid] = [];
            $childrenMap[$pid][] = (int)$c['id'];
        }
        $desc = [];
        $stack = [$id];
        while ($stack) {
            $cur = array_pop($stack);
            if (!empty($childrenMap[$cur])) {
                foreach ($childrenMap[$cur] as $ch) { $desc[$ch] = true; $stack[] = $ch; }
            }
        }
        if ($parent_id !== null && ($parent_id === $id || isset($desc[$parent_id]))) {
            $messages['error'][] = 'لا يمكن نقل القسم تحت نفسه أو أحد أقسامه الفرعية.';
        } else {
            $stmt = $pdo->prepare('UPDATE categories SET name = :name, parent_id = :parent_id WHERE id = :id');
            $stmt->execute(['name' => $name, 'parent_id' => $parent_id, 'id' => $id]);
            $messages['success'][] = 'تم تحديث القسم بنجاح.';
        }
    }
}

// نقل إعلانات قسم إلى قسم آخر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_ads'])) {
    $from = (int)($_POST['from_cat'] ?? 0);
    $to = (int)($_POST['to_cat'] ?? 0);
    if ($from > 0 && $to > 0 && $from !== $to) {
        $stmt = $pdo->prepare('UPDATE ads SET category_id = :to WHERE category_id = :from');
        $stmt->execute(['to' => $to, 'from' => $from]);
        $messages['success'][] = 'تم نقل الإعلانات بنجاح.';
    } else {
        $messages['error'][] = 'يرجى اختيار قسمين صالحين للنقل.';
    }
}

// حذف قسم (مسموح فقط إذا لا يحتوي على أقسام فرعية أو إعلانات)
if (isset($_GET['delete_category']) && is_numeric($_GET['delete_category'])) {
    $cid = (int)$_GET['delete_category'];
    // تحقق من عدم وجود أبناء
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = :id');
    $stmt->execute(['id' => $cid]);
    $childCount = (int)$stmt->fetchColumn();
    // تحقق من عدم وجود إعلانات
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ads WHERE category_id = :id');
    $stmt->execute(['id' => $cid]);
    $adsCount = (int)$stmt->fetchColumn();
    if ($childCount > 0 || $adsCount > 0) {
        $messages['error'][] = 'لا يمكن حذف القسم لأنه يحتوي على ' . $childCount . ' قسم فرعي و ' . $adsCount . ' إعلان. انقلهم أولاً.';
    } else {
        $stmt = $pdo->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute(['id' => $cid]);
        $messages['success'][] = 'تم حذف القسم بنجاح.';
    }
}

// جلب البيانات لعرضها
// المستخدمون
$usersStmt = $pdo->query('SELECT id, username, email, phone, created_at, is_admin FROM users ORDER BY id ASC');
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// الإعلانات
$adsStmt = $pdo->query('SELECT ads.id, ads.title, ads.created_at, users.username AS owner_name FROM ads JOIN users ON ads.user_id = users.id ORDER BY ads.created_at DESC');
$ads = $adsStmt->fetchAll(PDO::FETCH_ASSOC);

// التعليقات (عرض آخر 50 تعليق)
$commentsStmt = $pdo->query('SELECT comments.id, comments.content, comments.approved, comments.deleted_at, comments.created_at, comments.ad_id, comments.user_id, users.username AS commenter, ads.title AS ad_title FROM comments JOIN users ON comments.user_id = users.id JOIN ads ON comments.ad_id = ads.id ORDER BY comments.created_at DESC LIMIT 50');
$comments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);

// --------- إحصائيات للوحة الإدارة ---------
$stats = [];
try {
    $stats['total_users']      = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['admin_users']      = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn();
    $stats['total_ads']        = (int)$pdo->query('SELECT COUNT(*) FROM ads')->fetchColumn();
    $stats['total_comments']   = (int)$pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
    $stats['pending_comments'] = (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE approved = 0 AND deleted_at IS NULL")->fetchColumn();
    $stats['total_categories'] = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
} catch (Exception $e) {
    // في حال حدوث خطأ في الاستعلام، نترك الإحصائيات فارغة
    $stats = [];
}
?>
<?php require_once __DIR__ . '/header.php'; ?>

<h2>لوحة الإدارة</h2>

<!-- قسم الإحصائيات الرئيسية -->
<?php if (!empty($stats)): ?>
<section class="admin-section dashboards">
    <h3>إحصائيات عامة</h3>
    <div class="dashboard-grid">
        <div class="dash-card">
            <h4>إجمالي المستخدمين</h4>
            <p><?php echo $stats['total_users']; ?></p>
        </div>
        <div class="dash-card">
            <h4>المشرفون</h4>
            <p><?php echo $stats['admin_users']; ?></p>
        </div>
        <div class="dash-card">
            <h4>عدد الإعلانات</h4>
            <p><?php echo $stats['total_ads']; ?></p>
        </div>
        <div class="dash-card">
            <h4>عدد التعليقات</h4>
            <p><?php echo $stats['total_comments']; ?></p>
        </div>
        <div class="dash-card">
            <h4>التعليقات بانتظار الموافقة</h4>
            <p><?php echo $stats['pending_comments']; ?></p>
        </div>
        <div class="dash-card">
            <h4>الأقسام</h4>
            <p><?php echo $stats['total_categories']; ?></p>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- إدارة التصنيفات -->
<section class="admin-section">
    <h3>إدارة التصنيفات</h3>
    <?php
        // اجلب التصنيفات مع عدد الإعلانات
        $stmtCats = $pdo->query('SELECT c.id, c.parent_id, c.name, COUNT(a.id) AS ads_count
                                 FROM categories c
                                 LEFT JOIN ads a ON a.category_id = c.id
                                 GROUP BY c.id, c.parent_id, c.name
                                 ORDER BY c.parent_id, c.id');
        $allCats = $stmtCats->fetchAll(PDO::FETCH_ASSOC);
        // ابنِ خريطة الأبناء
        $childrenMap = [];
        foreach ($allCats as $c) {
            $pid = (int)($c['parent_id'] ?? 0);
            if (!isset($childrenMap[$pid])) $childrenMap[$pid] = [];
            $childrenMap[$pid][] = $c;
        }
        // مساعد لعرض قائمة خيارات الآباء بشكل هرمي
        function buildCatOptions($parent, $childrenMap, $level = 0, $excludeIds = [], $selectedId = null) {
            $out = '';
            if (empty($childrenMap[$parent])) return $out;
            foreach ($childrenMap[$parent] as $c) {
                $id = (int)$c['id'];
                if (in_array($id, $excludeIds, true)) { continue; }
                $indent = str_repeat('— ', $level);
                $sel = ($selectedId !== null && $selectedId == $id) ? ' selected' : '';
                $out .= '<option value="' . $id . '"' . $sel . '>' . $indent . htmlspecialchars($c['name']) . '</option>';
                $out .= buildCatOptions($id, $childrenMap, $level + 1, $excludeIds, $selectedId);
            }
            return $out;
        }
        function renderCatRows($parent, $childrenMap, $level = 0) {
            if (empty($childrenMap[$parent])) return;
            foreach ($childrenMap[$parent] as $c) {
                $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
                $childCount = !empty($childrenMap[$c['id']]) ? count($childrenMap[$c['id']]) : 0;
                $rowParent = (int)($c['parent_id'] ?? 0);
                echo '<tr data-id="' . (int)$c['id'] . '" data-parent="' . $rowParent . '">';
                echo '<td>' . (int)$c['id'] . '</td>';
                echo '<td>' . $indent;
                if ($childCount > 0) {
                    echo '<button type="button" class="cat-toggle" title="طي/توسيع" data-target="' . (int)$c['id'] . '">▾</button> ';
                }
                echo htmlspecialchars($c['name']) . '</td>';
                echo '<td>' . ($c['parent_id'] ? (int)$c['parent_id'] : '—') . '</td>';
                echo '<td>' . (int)$c['ads_count'] . '</td>';
                echo '<td>' . (int)$childCount . '</td>';
                echo '<td>';
                echo '<details><summary>تعديل</summary>';
                echo '<form method="POST" class="form-inline-grid">';
                echo '<input type="hidden" name="update_category" value="1">';
                echo '<input type="hidden" name="cat_id" value="' . (int)$c['id'] . '">';
                echo '<input class="input-modern" type="text" name="cat_name" value="' . htmlspecialchars($c['name']) . '" placeholder="اسم القسم">';
                echo '<select class="input-modern" name="cat_parent">';
                echo '<option value="">(جذر)</option>';
                echo buildCatOptions(0, $childrenMap, 0, [(int)$c['id']], (int)$c['parent_id']);
                echo '</select>';
                echo '<button class="btn btn-modern" type="submit">حفظ</button>';
                echo '</form>';
                echo '</details> ';
                echo '<a href="admin.php?delete_category=' . (int)$c['id'] . '" onclick="return confirm(\'سيتم حذف هذا القسم إن كان فارغاً. تأكيد؟\');">حذف</a>';
                echo '</td>';
                echo '</tr>';
                renderCatRows($c['id'], $childrenMap, $level + 1);
            }
        }
    ?>

    <?php if (!empty($messages['success']) || !empty($messages['error'])): ?>
        <div class="admin-messages">
            <?php foreach ($messages['success'] as $m): ?><p style="color:#6fd36f; margin:4px 0;">• <?php echo htmlspecialchars($m); ?></p><?php endforeach; ?>
            <?php foreach ($messages['error'] as $m): ?><p style="color:#ff8686; margin:4px 0;">• <?php echo htmlspecialchars($m); ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="admin-grid-two">
        <div>
            <h4>جميع التصنيفات</h4>
            <div style="display:flex; gap:8px; margin:6px 0 10px;">
                <button type="button" class="btn btn-modern" id="expandAll">توسيع الكل</button>
                <button type="button" class="btn btn-modern" id="collapseAll">طي الكل</button>
            </div>
            <div class="table-responsive">
            <table class="admin-table categories-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم</th>
                        <th>الأب</th>
                        <th>عدد الإعلانات</th>
                        <th>عدد الفروع</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php renderCatRows(0, $childrenMap, 0); ?>
                </tbody>
            </table>
            </div>
        </div>
        <div>
            <h4>إضافة قسم جديد</h4>
            <form method="POST" class="form-modern">
                <input type="hidden" name="add_category" value="1">
                <label>اسم القسم</label>
                <input type="text" class="input-modern" name="cat_name" placeholder="مثال: سيارات" required>
                <label>القسم الأب (اختياري)</label>
                <input type="text" class="input-modern" id="parentSearch" placeholder="ابحث عن قسم أب...">
                <select name="cat_parent" class="input-modern">
                    <option value="">(جذر)</option>
                    <?php echo buildCatOptions(0, $childrenMap, 0); ?>
                </select>
                <button type="submit" class="btn btn-modern" style="margin-top:8px;">إضافة</button>
            </form>

            <h4 style="margin-top:18px;">نقل إعلانات قسم</h4>
            <form method="POST" class="form-modern">
                <input type="hidden" name="move_ads" value="1">
                <label>من القسم</label>
                <select name="from_cat" class="input-modern" required>
                    <?php foreach ($allCats as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>"><?php echo '[' . (int)$c['id'] . '] ' . htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>إلى القسم</label>
                <select name="to_cat" class="input-modern" required>
                    <?php foreach ($allCats as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>"><?php echo '[' . (int)$c['id'] . '] ' . htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-modern" style="margin-top:8px;">نقل</button>
            </form>
        </div>
    </div>
</section>

<style>
/* تحسين زر الطي/التوسيع وطبقات الإخفاء */
.cat-toggle { border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; background: rgba(255,255,255,0.06); color: #fff; cursor: pointer; width: 28px; height: 24px; line-height: 22px; text-align: center; font-size: 14px; }
tr.cat-hidden { display: none; }
</style>

<script>
(function(){
    const tbody = document.querySelector('section.admin-section table.admin-table tbody');
    if (!tbody) return;
    const rowsByParent = new Map();
    const rows = Array.from(tbody.querySelectorAll('tr[data-id]'));
    rows.forEach(r => {
        const pid = r.getAttribute('data-parent') || '0';
        if (!rowsByParent.has(pid)) rowsByParent.set(pid, []);
        rowsByParent.get(pid).push(r);
    });

    function setChildrenHidden(parentId, hidden) {
        const list = rowsByParent.get(String(parentId)) || [];
        list.forEach(r => {
            if (hidden) r.classList.add('cat-hidden'); else r.classList.remove('cat-hidden');
            const id = r.getAttribute('data-id');
            // إذا أخفيت الأب، أخفِ جميع الأحفاد أيضاً
            setChildrenHidden(id, hidden);
        });
    }

    // أزرار الطي/التوسيع داخل الصفوف
    tbody.addEventListener('click', function(e){
        const btn = e.target.closest('.cat-toggle');
        if (!btn) return;
        const id = btn.getAttribute('data-target');
        // قرر الحالة بحسب أول ابن
        const firstChild = (rowsByParent.get(id) || [])[0];
        const willHide = firstChild && !firstChild.classList.contains('cat-hidden');
        setChildrenHidden(id, willHide);
        // حدّث شكل السهم
        btn.textContent = willHide ? '▸' : '▾';
    });

    // تحكم عام
    const expandAllBtn = document.getElementById('expandAll');
    const collapseAllBtn = document.getElementById('collapseAll');
    if (expandAllBtn) expandAllBtn.addEventListener('click', () => {
        rows.forEach(r => r.classList.remove('cat-hidden'));
        document.querySelectorAll('.cat-toggle').forEach(b => b.textContent = '▾');
    });
    if (collapseAllBtn) collapseAllBtn.addEventListener('click', () => {
        // أخف كل شيء عدا الجذور (parent=0)
        rows.forEach(r => { if ((r.getAttribute('data-parent') || '0') !== '0') r.classList.add('cat-hidden'); });
        document.querySelectorAll('.cat-toggle').forEach(b => b.textContent = '▸');
    });

    // افتراضياً على الشاشات الصغيرة، قم بطي جميع الفروع لتقليل الطول والتمرير الأفقي
    if (window.innerWidth <= 768 && collapseAllBtn) {
        collapseAllBtn.click();
    }

    // بحث سريع لاختيار أول خيار مطابق في قائمة الأب (إضافة قسم)
    const search = document.getElementById('parentSearch');
    const parentSelect = search ? search.nextElementSibling : null;
    if (search && parentSelect && parentSelect.tagName === 'SELECT') {
        search.addEventListener('input', function(){
            const q = this.value.trim().toLowerCase();
            if (!q) return;
            const opts = Array.from(parentSelect.options);
            const found = opts.find(o => o.text.toLowerCase().includes(q));
            if (found) {
                parentSelect.value = found.value;
                // محاولة تمرير التركيز لتنبيه المستخدم
                parentSelect.scrollTop = found.index * 24;
            }
        });
    }
})();
</script>

<section class="admin-section">
    <h3>المستخدمون</h3>
    <?php if (!$users): ?>
        <p>لا يوجد مستخدمون.</p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="admin-table">
            <thead>
                <tr>
                    <th>المعرف</th>
                    <th>الاسم</th>
                    <th>البريد الإلكتروني</th>
                    <th>الجوال</th>
                    <th>تاريخ التسجيل</th>
                    <th>الدور</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo (int)$u['id']; ?></td>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><?php echo htmlspecialchars($u['phone']); ?></td>
                        <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                        <td><?php echo ((int)$u['is_admin'] === 1) ? 'أدمن' : 'مستخدم'; ?></td>
                        <td>
                            <?php if ($u['id'] != $admin_id): ?>
                                <?php if ((int)$u['is_admin'] === 1): ?>
                                    <!-- خيار إزالة صلاحية الأدمن -->
                                    <a href="admin.php?demote_user=<?php echo (int)$u['id']; ?>" onclick="return confirm('هل أنت متأكد من إزالة صلاحية الأدمن؟');">إرجاع لمستخدم</a>
                                <?php else: ?>
                                    <!-- خيار ترقية المستخدم إلى أدمن -->
                                    <a href="admin.php?promote_user=<?php echo (int)$u['id']; ?>" onclick="return confirm('هل أنت متأكد من ترقية المستخدم إلى أدمن؟');">ترقية لأدمن</a>
                                <?php endif; ?>
                                |
                                <a href="admin.php?delete_user=<?php echo (int)$u['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف هذا المستخدم؟');">حذف</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<section class="admin-section">
    <h3>الإعلانات</h3>
    <?php if (!$ads): ?>
        <p>لا توجد إعلانات.</p>
    <?php else: ?>
    <div class="table-responsive">
    <table class="admin-table">
            <thead>
                <tr>
                    <th>المعرف</th>
                    <th>العنوان</th>
                    <th>المالك</th>
                    <th>تاريخ النشر</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ads as $a): ?>
                    <tr>
                        <td><?php echo (int)$a['id']; ?></td>
                        <td><a href="ad_details.php?id=<?php echo (int)$a['id']; ?>" target="_blank"><?php echo htmlspecialchars($a['title']); ?></a></td>
                        <td><?php echo htmlspecialchars($a['owner_name']); ?></td>
                        <td><?php echo htmlspecialchars($a['created_at']); ?></td>
                        <td><a href="admin.php?delete_ad=<?php echo (int)$a['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف هذا الإعلان؟');">حذف</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<?php
// إدارة التمييز: إيقاف/تمديد يدوياً + عرض السجل
// إجراءات POST: إيقاف/تمديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_feature_action'])) {
    $action = $_POST['admin_feature_action'];
    $targetAd = (int)($_POST['ad_id'] ?? 0);
    $extendDays = (int)($_POST['extend_days'] ?? 0);
    if ($targetAd > 0) {
        if ($action === 'off') {
            try {
                $pdo->prepare('UPDATE ads SET is_premium = 0, premium_until = NULL WHERE id = :id')->execute(['id' => $targetAd]);
                $messages['success'][] = 'تم إلغاء تمييز الإعلان #' . $targetAd;
            } catch (Throwable $e) { $messages['error'][] = 'تعذر إلغاء التمييز'; }
        } elseif ($action === 'extend' && $extendDays > 0) {
            try {
                $st = $pdo->prepare("UPDATE ads SET is_premium = 1, premium_until = CASE WHEN premium_until IS NOT NULL AND premium_until > NOW() THEN DATE_ADD(premium_until, INTERVAL :d DAY) ELSE DATE_ADD(NOW(), INTERVAL :d DAY) END WHERE id = :id");
                $st->bindValue(':d', $extendDays, PDO::PARAM_INT);
                $st->bindValue(':id', $targetAd, PDO::PARAM_INT);
                $st->execute();
                $messages['success'][] = 'تم تمديد تمييز الإعلان #' . $targetAd . ' لمدة ' . $extendDays . ' يوم';
            } catch (Throwable $e) { $messages['error'][] = 'تعذر التمديد'; }
        }
    }
}

// جلب عمليات التمييز (فلتر حالة اختياري)
$promotions = [];
try {
    $statusFilter = isset($_GET['pstatus']) ? trim($_GET['pstatus']) : '';
    if ($statusFilter !== '' && in_array($statusFilter, ['pending','paid','failed','cancelled'], true)) {
        $stmt = $pdo->prepare('SELECT p.*, u.username, a.title FROM promotions p JOIN users u ON p.user_id = u.id JOIN ads a ON p.ad_id = a.id WHERE p.status = :st ORDER BY p.id DESC LIMIT 200');
        $stmt->execute(['st' => $statusFilter]);
        $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $q = 'SELECT p.*, u.username, a.title FROM promotions p JOIN users u ON p.user_id = u.id JOIN ads a ON p.ad_id = a.id ORDER BY p.id DESC LIMIT 200';
        $promotions = $pdo->query($q)->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) { $promotions = []; }
?>

<section class="admin-section">
    <h3>إدارة التمييز</h3>
    <?php if (!empty($messages['success']) || !empty($messages['error'])): ?>
        <div class="admin-messages">
            <?php foreach ($messages['success'] as $m): ?><p style="color:#6fd36f; margin:4px 0;">• <?php echo htmlspecialchars($m); ?></p><?php endforeach; ?>
            <?php foreach ($messages['error'] as $m): ?><p style="color:#ff8686; margin:4px 0;">• <?php echo htmlspecialchars($m); ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form method="GET" class="form-modern" style="display:flex; gap:8px; align-items:center; margin:8px 0 12px;">
        <input type="hidden" name="" value="">
        <label>الحالة:</label>
        <select name="pstatus" class="input-modern" onchange="this.form.submit()">
            <?php $cur = $_GET['pstatus'] ?? ''; ?>
            <option value="" <?php echo ($cur==='')?'selected':''; ?>>الكل</option>
            <option value="pending" <?php echo ($cur==='pending')?'selected':''; ?>>بإنتظار الدفع</option>
            <option value="paid" <?php echo ($cur==='paid')?'selected':''; ?>>مدفوع</option>
            <option value="failed" <?php echo ($cur==='failed')?'selected':''; ?>>فاشل</option>
            <option value="cancelled" <?php echo ($cur==='cancelled')?'selected':''; ?>>ملغي</option>
        </select>
    </form>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الإعلان</th>
                    <th>المستخدم</th>
                    <th>المدة</th>
                    <th>السعر</th>
                    <th>المزوّد</th>
                    <th>الحالة</th>
                    <th>المرجع</th>
                    <th>تاريخ</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($promotions as $p): ?>
                <tr>
                    <td><?php echo (int)$p['id']; ?></td>
                    <td><a href="ad_details.php?id=<?php echo (int)$p['ad_id']; ?>" target="_blank"><?php echo htmlspecialchars($p['title']); ?></a></td>
                    <td><?php echo htmlspecialchars($p['username']); ?></td>
                    <td><?php echo (int)$p['days']; ?> يوم</td>
                    <td><?php echo number_format((float)$p['price'], 2); ?> ر.س</td>
                    <td><?php echo htmlspecialchars($p['provider']); ?></td>
                    <td><?php echo htmlspecialchars($p['status']); ?></td>
                    <td><?php echo htmlspecialchars($p['provider_ref'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                    <td>
                        <form method="POST" class="form-inline-grid" style="gap:6px;">
                            <input type="hidden" name="ad_id" value="<?php echo (int)$p['ad_id']; ?>">
                            <button class="btn btn-modern" name="admin_feature_action" value="off" onclick="return confirm('إلغاء تمييز الإعلان الآن؟');">إطفاء التمييز</button>
                            <input type="number" name="extend_days" min="1" placeholder="أيام" class="input-modern" style="width:90px;">
                            <button class="btn btn-modern" name="admin_feature_action" value="extend">تمديد</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-section">
    <h3>التعليقات الأخيرة</h3>
    <?php if (!$comments): ?>
        <p>لا توجد تعليقات.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>المعرف</th>
                    <th>الإعلان</th>
                    <th>المستخدم</th>
                    <th>المحتوى</th>
                    <th>الحالة</th>
                    <th>تاريخ</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comments as $c): ?>
                    <tr>
                        <td><?php echo (int)$c['id']; ?></td>
                        <td><a href="ad_details.php?id=<?php echo (int)$c['ad_id']; ?>" target="_blank"><?php echo htmlspecialchars($c['ad_title']); ?></a></td>
                        <td><?php echo htmlspecialchars($c['commenter']); ?></td>
                        <td><?php echo htmlspecialchars(mb_strimwidth($c['content'], 0, 100, '...')); ?></td>
                        <td>
                            <?php
                                if ($c['deleted_at']) {
                                    echo 'محذوف';
                                } elseif ($c['approved']) {
                                    echo 'معتمد';
                                } else {
                                    echo 'في الانتظار';
                                }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($c['created_at']); ?></td>
                        <td>
                            <?php if (!$c['approved']): ?>
                                <a href="admin.php?approve_comment=<?php echo (int)$c['id']; ?>">اعتماد</a>
                            <?php endif; ?>
                            <?php if (!$c['deleted_at']): ?>
                                <a href="admin.php?delete_comment=<?php echo (int)$c['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف هذا التعليق؟');">حذف</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>