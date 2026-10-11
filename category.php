<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// احصل على معرف القسم الأب من المعامل
$parent_id = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
if (!$parent_id) {
    // معرف غير صالح، إعادة التوجيه إلى الصفحة الرئيسية
    header('Location: index.php');
    exit;
}

// جلب اسم القسم الأب
$stmt = $pdo->prepare('SELECT id, name FROM categories WHERE id = :id');
$stmt->execute(['id' => $parent_id]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$parent) {
    header('Location: index.php');
    exit;
}

// جلب الأقسام الفرعية لهذا القسم الأب مع عدد الإعلانات في كل قسم
$stmt = $pdo->prepare('SELECT c.id, c.parent_id, c.name, COUNT(a.id) AS ads_count
                       FROM categories c
                       LEFT JOIN ads a ON a.category_id = c.id
                       WHERE c.parent_id = :pid
                       GROUP BY c.id, c.parent_id, c.name
                       ORDER BY c.id');
$stmt->execute(['pid' => $parent_id]);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إذا لم توجد أقسام فرعية، أعد التوجيه إلى صفحة التصفح لعرض الإعلانات داخل هذا القسم
if (empty($children)) {
    header('Location: browse.php?category_id=' . $parent_id);
    exit;
}
?>
<?php require_once __DIR__ . '/header.php'; ?>
<style>
/* Category page responsive tweaks */
.category-page { padding: 10px 12px; }
.category-page .breadcrumbs { display:flex; flex-wrap:wrap; gap:8px; align-items:center; background: rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.10); border-radius: 12px; padding:8px 10px; margin: 6px 0 14px; }
.category-page .breadcrumbs a { text-decoration:none; color: inherit; display:inline-flex; align-items:center; gap:6px; }
.category-page .breadcrumbs .crumb-sep { opacity:.5; }
.category-page h2 { margin: 6px 0 12px; font-size: clamp(18px, 4vw, 24px); }
.category-page .categories-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
.category-page .category-card { display:flex; flex-direction:column; justify-content:center; align-items:center; min-height: 110px; text-align:center; }
.category-page .category-card .category-icon { font-size: 28px; margin-bottom: 6px; }
.category-page .listings-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 12px; }
.category-page .listing-card .listing-image { width:100%; height: 180px; object-fit: cover; border-radius: 12px; }
/* Icon-only back button */
.category-page .back-row { display:flex; justify-content:flex-start; margin: 8px 0 14px; }
.category-page .back-btn { display:inline-flex; align-items:center; justify-content:center; width:40px; height:40px; border-radius:10px; border:1px solid rgba(255,255,255,.18); background: rgba(255,255,255,.08); color:#fff; text-decoration:none; }
.category-page .back-btn:hover { background: rgba(255,255,255,.12); }
.category-page .back-btn svg { width:20px; height:20px; stroke: currentColor; }
@media (max-width: 720px) {
    .category-page { padding-inline: 10px; }
    .category-page .categories-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .category-page .category-card { min-height: 96px; }
    .category-page .category-card .category-icon { font-size: 24px; }
    .category-page .listings-grid { grid-template-columns: 1fr; gap: 10px; }
    .category-page .listing-card .listing-image { height: 160px; }
    .category-page .back-btn { width:36px; height:36px; }
}
/* Back button sizing: keep it compact and inline */
.category-page .btn.btn-modern { display:inline-flex; align-items:center; justify-content:center; height:40px; padding:0 14px; border-radius:10px; font-size:14px; font-weight:700; width:auto; min-width:unset; }
@media (max-width: 720px) {
    .category-page .btn.btn-modern { height:36px; padding:0 12px; font-size:13px; }
}
</style>
<div class="category-page">
<?php $path = get_category_path($parent['id']); ?>
<nav class="breadcrumbs" aria-label="مسار التنقل">
    <?php foreach ($path as $idx => $p): ?>
        <a href="browse.php?category_id=<?php echo $p['id']; ?>">
            <span class="crumb-icon">📁</span>
            <span class="crumb-text"><?php echo htmlspecialchars($p['name']); ?></span>
        </a>
        <?php if ($idx < count($path) - 1): ?>
            <span class="crumb-sep">›</span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
<h2>الأقسام الفرعية لقسم: <?php echo htmlspecialchars($parent['name']); ?></h2>
<div class="categories-grid modern-grid">
    <?php foreach ($children as $cat): ?>
        <a href="browse.php?category_id=<?php echo $cat['id']; ?>" class="category-card card-modern">
            <div class="category-icon">📁</div>
            <div class="category-name"><?php echo htmlspecialchars($cat['name']); ?></div>
            <div class="category-count">(<?php echo (int)$cat['ads_count']; ?>)</div>
        </a>
    <?php endforeach; ?>
</div>

<?php
// عرض الإعلانات الموجودة مباشرة في هذا القسم الأب (إذا وُجدت)
$parent_ads = [];
$parent_ads = get_ads(['category_id' => $parent['id']]);
?>
<?php
// موضوعات فرعية شائعة مستندة إلى البيانات
$smart = get_smart_subtopics($parent['id'], 8);
if (!empty($smart)):
?>
    <h3>موضوعات فرعية شائعة</h3>
    <ul class="smart-subtopics" style="list-style:none;padding:0;margin:10px 0 18px;display:flex;flex-wrap:wrap;gap:8px;">
        <?php foreach ($smart as $s):
            $name = $s['name'] ?? ($s['label'] ?? '');
            $cnt  = (int)($s['cnt'] ?? ($s['count'] ?? 0));
            $url  = '#';
            if (($s['type'] ?? '') === 'category') {
                $url = 'browse.php?category_id=' . (int)$s['id'];
            } else {
                // بناء رابط تصفية بالخصائص
                $key = $s['key'] ?? '';
                $val = $s['value'] ?? '';
                $q = [];
                // خريطة مفاتيح الخصائص إلى باراميترات browse.php
                if ($key === 'car_make') { $q['car_make'] = $val; $q['category_id'] = 1; }
                elseif ($key === 'car_model') { $q['car_model'] = $val; $q['category_id'] = 1; }
                elseif ($key === 'car_year') { $q['car_year_from'] = $val; $q['car_year_to'] = $val; $q['category_id'] = 1; }
                elseif ($key === 'device_brand') { $q['device_brand'] = $val; $q['category_id'] = 9; }
                elseif ($key === 'device_model') { $q['device_model'] = $val; $q['category_id'] = 9; }
                // كوّن الاستعلام
                if (!empty($q)) {
                    $parts = [];
                    foreach ($q as $k => $v) { $parts[] = urlencode($k) . '=' . urlencode($v); }
                    $url = 'browse.php?' . implode('&', $parts);
                } else {
                    // في حال خصائص عامة أخرى غير مدعومة، أعرض جميع النتائج بالبحث العام
                    $url = 'browse.php?keyword=' . urlencode($name);
                }
            }
        ?>
            <li>
                <a href="<?php echo htmlspecialchars($url); ?>" class="btn" style="display:inline-flex;align-items:center;gap:6px;background:#f1f3f5;color:#0078d4;border:1px solid #d0d7de;border-radius:999px;padding:6px 12px;text-decoration:none;">
                    <span><?php echo htmlspecialchars($name); ?></span>
                    <small style="color:#555">(<?php echo $cnt; ?>)</small>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php if (!empty($parent_ads)): ?>
    <h3>الإعلانات في قسم <?php echo htmlspecialchars($parent['name']); ?></h3>
    <div class="listings-grid modern-grid">
        <?php foreach ($parent_ads as $ad): ?>
            <div class="listing-card card-modern">
                <?php $images = get_ad_images($ad['id']); $img = $images ? $images[0]['image_path'] : 'placeholder.png'; ?>
                <a href="ad_details.php?id=<?php echo $ad['id']; ?>">
                    <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" class="listing-image">
                </a>
                <div class="listing-info">
                    <h3 class="listing-title"><a href="ad_details.php?id=<?php echo $ad['id']; ?>"><?php echo htmlspecialchars($ad['title']); ?></a></h3>
                    <p class="listing-price"><?php echo number_format($ad['price'], 2); ?> ر.س</p>
                    <p class="listing-category">قسم: <?php echo htmlspecialchars($ad['category_name']); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="back-row">
    <a href="index.php" class="back-btn" aria-label="رجوع">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <!-- Chevron pointing right (back in RTL) -->
            <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
    </a>
</div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
