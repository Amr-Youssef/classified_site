<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// الحصول على الفلاتر من الاستعلام
$filters = [];
if (isset($_GET['keyword'])) {
    $filters['keyword'] = trim($_GET['keyword']);
}
if (isset($_GET['category_id']) && is_numeric($_GET['category_id'])) {
    $filters['category_id'] = (int) $_GET['category_id'];
}
if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
    $filters['min_price'] = (float) $_GET['min_price'];
}
if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
    $filters['max_price'] = (float) $_GET['max_price'];
}
if (isset($_GET['condition'])) {
    $filters['condition'] = $_GET['condition'];
}
// مدينة
if (isset($_GET['city']) && $_GET['city'] !== '') {
    $filters['city'] = trim($_GET['city']);
}
// ترتيب
if (isset($_GET['sort']) && $_GET['sort'] !== '') {
    $filters['sort'] = $_GET['sort'];
}
// ترقيم صفحات
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) && is_numeric($_GET['per_page']) ? max(6, (int)$_GET['per_page']) : 12;
$filters['page'] = $page;
$filters['per_page'] = $per_page;
// فلاتر متقدمة محتملة
if (isset($_GET['car_make'])) $filters['car_make'] = trim($_GET['car_make']);
if (isset($_GET['car_model'])) $filters['car_model'] = trim($_GET['car_model']);
if (isset($_GET['car_year_from']) && $_GET['car_year_from'] !== '') $filters['car_year_from'] = (int)$_GET['car_year_from'];
if (isset($_GET['car_year_to']) && $_GET['car_year_to'] !== '') $filters['car_year_to'] = (int)$_GET['car_year_to'];
if (isset($_GET['device_brand'])) $filters['device_brand'] = trim($_GET['device_brand']);
if (isset($_GET['device_model'])) $filters['device_model'] = trim($_GET['device_model']);

// جلب الأقسام لاستخدامها في الفلتر
$categories = get_categories();
// جلب المدن المتوفرة مرتبة حسب عدد الإعلانات
$city_counts = get_cities_with_counts();

// اذا تم تحديد قسم، تحقق إذا له أقسام فرعية أعرضها أولاً
$children = [];
$parent = null;
if (!empty($filters['category_id'])) {
    $parent_id = (int)$filters['category_id'];
    // جلب اسم القسم الأب (بما في ذلك parent_id الخاص به)
    $stmt = $pdo->prepare('SELECT id, parent_id, name FROM categories WHERE id = :id');
    $stmt->execute(['id' => $parent_id]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($parent) {
        // جلب الأقسام الفرعية مع عدد الإعلانات
        $stmt = $pdo->prepare('SELECT c.id, c.parent_id, c.name, COUNT(a.id) AS ads_count
                               FROM categories c
                               LEFT JOIN ads a ON a.category_id = c.id
                               WHERE c.parent_id = :pid
                               GROUP BY c.id, c.parent_id, c.name
                               ORDER BY c.id');
        $stmt->execute(['pid' => $parent_id]);
        $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// جلب الإعلانات حسب الفلاتر (فقط إذا لم نعرض الأقسام الفرعية)
$ads = [];
// إذا وُجدت أقسام فرعية ولا يريد المستخدم رؤية إعلانات القسم الأب، لا نجلب إعلاناته افتراضياً
$show_parent_ads = isset($_GET['show_parent_ads']) && $_GET['show_parent_ads'] == '1';
if (empty($children) || $show_parent_ads) {
    $ads = get_ads($filters);
    $total = count_ads($filters);
    $total_pages = max(1, (int)ceil($total / $per_page));
} else {
    $total = 0;
    $total_pages = 1;
}

// حدد نوع الفلاتر المتقدمة بناءً على القسم المختار
$advanced_type = '';
if (!empty($filters['category_id'])) {
    // اجلب مسار القسم لتحديد الجذر الأعلى
    $path = get_category_path((int)$filters['category_id']);
    $top = $path ? $path[0] : null; // الجذر الأعلى
    if ($top) {
        // السيارات: id = 1 حسب seed
        if ((int)$top['id'] === 1) {
            $advanced_type = 'cars';
        }
        // الأجهزة: id = 9 حسب seed
        elseif ((int)$top['id'] === 9) {
            $advanced_type = 'devices';
        }
    }
}
?>
<?php require_once __DIR__ . '/header.php'; ?>
<h2>تصفح الإعلانات</h2>

<!-- نموذج البحث والتصفية -->
<form method="GET" action="browse.php" class="search-form form-modern">
    <input type="text" name="keyword" placeholder="ابحث بالكلمات المفتاحية" class="input-modern" value="<?php echo isset($filters['keyword']) ? htmlspecialchars($filters['keyword']) : ''; ?>">
    <select name="category_id" class="input-modern">
        <option value="">اختر القسم</option>
        <?php foreach ($categories as $cat): ?>
            <?php if (empty($cat['parent_id'])): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($filters['category_id']) && $filters['category_id'] == $cat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
    <select name="city" class="input-modern">
        <option value="">كل المدن</option>
        <?php foreach ($city_counts as $row): $city = $row['city']; $cnt = (int)$row['count']; ?>
            <option value="<?php echo htmlspecialchars($city); ?>" <?php echo (!empty($filters['city']) && $filters['city'] === $city) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($city); ?><?php echo $cnt > 0 ? ' ('.$cnt.')' : ''; ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="sort" class="input-modern">
        <option value="newest" <?php echo (!empty($filters['sort']) && $filters['sort']==='newest') ? 'selected' : ''; ?>>الأحدث</option>
        <option value="oldest" <?php echo (!empty($filters['sort']) && $filters['sort']==='oldest') ? 'selected' : ''; ?>>الأقدم</option>
        <option value="price_asc" <?php echo (!empty($filters['sort']) && $filters['sort']==='price_asc') ? 'selected' : ''; ?>>السعر: من الأقل إلى الأعلى</option>
        <option value="price_desc" <?php echo (!empty($filters['sort']) && $filters['sort']==='price_desc') ? 'selected' : ''; ?>>السعر: من الأعلى إلى الأقل</option>
    </select>
    <input type="number" step="0.01" name="min_price" placeholder="السعر الأدنى" class="input-modern" value="<?php echo isset($filters['min_price']) ? htmlspecialchars($filters['min_price']) : ''; ?>">
    <input type="number" step="0.01" name="max_price" placeholder="السعر الأعلى" class="input-modern" value="<?php echo isset($filters['max_price']) ? htmlspecialchars($filters['max_price']) : ''; ?>">
    <select name="condition" class="input-modern">
        <option value="">الحالة</option>
        <option value="new" <?php echo (isset($filters['condition']) && $filters['condition'] == 'new') ? 'selected' : ''; ?>>جديد</option>
        <option value="used" <?php echo (isset($filters['condition']) && $filters['condition'] == 'used') ? 'selected' : ''; ?>>مستعمل</option>
    </select>

    <?php if ($advanced_type === 'cars'): ?>
        <input type="text" name="car_make" placeholder="الشركة المصنعة (مثال: تويوتا)" class="input-modern" value="<?php echo isset($filters['car_make']) ? htmlspecialchars($filters['car_make']) : ''; ?>">
        <input type="text" name="car_model" placeholder="الموديل (مثال: كورولا)" class="input-modern" value="<?php echo isset($filters['car_model']) ? htmlspecialchars($filters['car_model']) : ''; ?>">
        <input type="number" name="car_year_from" placeholder="سنة من" class="input-modern" value="<?php echo isset($filters['car_year_from']) ? htmlspecialchars($filters['car_year_from']) : ''; ?>">
        <input type="number" name="car_year_to" placeholder="سنة إلى" class="input-modern" value="<?php echo isset($filters['car_year_to']) ? htmlspecialchars($filters['car_year_to']) : ''; ?>">
    <?php elseif ($advanced_type === 'devices'): ?>
        <input type="text" name="device_brand" placeholder="العلامة التجارية (مثال: آبل)" class="input-modern" value="<?php echo isset($filters['device_brand']) ? htmlspecialchars($filters['device_brand']) : ''; ?>">
        <input type="text" name="device_model" placeholder="الموديل (مثال: آيفون 13)" class="input-modern" value="<?php echo isset($filters['device_model']) ? htmlspecialchars($filters['device_model']) : ''; ?>">
    <?php endif; ?>
    <button type="submit" class="btn-modern">بحث</button>
    <input type="hidden" name="page" value="1">
</form>
<?php if (!empty($children)): ?>
    <?php if (!empty($parent)): ?>
        <?php $path = get_category_path($parent['id']); ?>
        <nav class="breadcrumbs" aria-label="مسار التنقل">
            <?php
            // نعرض المسار من الجذر إلى الحالي، لكن نستخدم CSS مع row-reverse ليتوافق مع RTL
            foreach ($path as $idx => $p):
                // أيقونة مجلد صغيرة
                ?>
                <a href="browse.php?category_id=<?php echo $p['id']; ?>">
                    <span class="crumb-icon">📁</span>
                    <span class="crumb-text"><?php echo htmlspecialchars($p['name']); ?></span>
                </a>
                <?php if ($idx < count($path) - 1): ?>
                    <span class="crumb-sep">›</span>
                <?php endif;
            endforeach;
            ?>
        </nav>
        <h3>الأقسام الفرعية لقسم: <?php echo htmlspecialchars($parent['name']); ?></h3>
    <?php endif; ?>
    <div class="categories-grid modern-grid">
        <?php foreach ($children as $cat): ?>
            <a href="browse.php?category_id=<?php echo $cat['id']; ?>" class="category-card card-modern">
                <div class="category-icon">📁</div>
                <div class="category-name"><?php echo htmlspecialchars($cat['name']); ?></div>
                <div class="category-count">(<?php echo (int)$cat['ads_count']; ?>)</div>
            </a>
        <?php endforeach; ?>
    </div>
    <p>
        <a href="browse.php?category_id=<?php echo $parent['id']; ?>&show_parent_ads=1" class="btn btn-modern btn-compact-sm">عرض إعلانات هذا القسم</a>
    </p>
<?php else: ?>
    <?php if (!$ads): ?>
        <?php
        // في حال عدم وجود أقسام فرعية ولا نتائج، اقترح موضوعات شائعة من القسم الأعلى إن وُجد
        $suggested = [];
        if (!empty($filters['category_id'])) {
            $path = get_category_path((int)$filters['category_id']);
            $top = $path ? $path[0] : null;
            if ($top) {
                $suggested = get_smart_subtopics((int)$top['id'], 10);
            }
        }
        ?>
        <p>لا توجد إعلانات مطابقة للمعايير المختارة.</p>
        <?php if (!empty($suggested)): ?>
            <h4>اقتراحات للبحث:</h4>
            <div class="chips">
                <?php foreach ($suggested as $s): ?>
                    <?php if ($s['type'] === 'category'): ?>
                        <a class="chip" href="browse.php?category_id=<?php echo (int)$s['id']; ?>">#<?php echo htmlspecialchars($s['label']); ?> (<?php echo (int)$s['count']; ?>)</a>
                    <?php else: ?>
                        <?php
                          $qs = $_GET;
                          // طبّق مفتاح القيمة المقترحة كفلتر إضافي
                          $qs[$s['key']] = $s['value'];
                          $href = 'browse.php?' . http_build_query($qs);
                        ?>
                        <a class="chip" href="<?php echo htmlspecialchars($href); ?>">#<?php echo htmlspecialchars($s['label']); ?> (<?php echo (int)$s['count']; ?>)</a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="results-meta" style="margin:10px 0;">
            <span>النتائج: <?php echo (int)$total; ?></span>
            <?php if ($total_pages > 1): ?>
                <span style="margin-inline-start:10px;">الصفحة <?php echo (int)$page; ?> من <?php echo (int)$total_pages; ?></span>
            <?php endif; ?>
        </div>
        <div class="listings-grid modern-grid">
            <?php foreach ($ads as $ad): ?>
            <div class="listing-card card-modern">
                    <?php
                    $images = get_ad_images($ad['id']);
                    $img = $images ? $images[0]['image_path'] : 'placeholder.png';
                    ?>
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
        <?php if ($total_pages > 1): ?>
            <?php
            // بناء روابط الصفحات مع الحفاظ على الاستعلامات الأخرى
            $baseQuery = $_GET;
            ?>
            <nav class="pagination" aria-label="ترقيم الصفحات" style="display:flex; gap:6px; justify-content:center; margin: 16px 0;">
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <?php $baseQuery['page'] = $p; $link = 'browse.php?' . http_build_query($baseQuery); ?>
                    <a href="<?php echo htmlspecialchars($link); ?>" class="page-link <?php echo $p === $page ? 'active' : ''; ?>">
                        <?php echo $p; ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>