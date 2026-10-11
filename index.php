<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// بيانات ديناميكية للصفحة الرئيسية
$categories = get_categories();
$city_counts = get_cities_with_counts();

// إعلانات مميزة (Premium) مع دعم premium_until
$featured_ads = get_featured_ads(8);

// أحدث الإعلانات
$latest_ads = get_ads([]);
$latest_ads = array_slice($latest_ads, 0, 8);

// اختيار أفضل 3 أقسام جذرية بحسب عدد الإعلانات تحتها (القسم + فروعه المباشرة)
$popular_roots = [];
try {
    $stmt = $pdo->query("SELECT r.id, r.name,
                                (SELECT COUNT(*) FROM ads a WHERE a.category_id = r.id
                                 OR a.category_id IN (SELECT id FROM categories c2 WHERE c2.parent_id = r.id)) AS ads_count
                         FROM categories r
                         WHERE r.parent_id IS NULL
                         ORDER BY ads_count DESC, r.id ASC
                         LIMIT 3");
    $popular_roots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $popular_roots = []; }

require_once __DIR__ . '/header.php';
?>

<?php
// Robust category resolution: normalize Arabic names and match with synonyms/contains
function __normalize_ar(string $s): string {
    $s = trim($s);
    // Remove tatweel and diacritics
    $s = preg_replace('/\x{0640}|[\x{064B}-\x{0652}]/u', '', $s);
    // Unify common letters
    $map = [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا',
        'ى' => 'ي', 'ئ' => 'ي', 'ؤ' => 'و',
        'ة' => 'ه',
    ];
    $s = strtr($s, $map);
    // Collapse spaces
    $s = preg_replace('/\s+/u', ' ', $s);
    return $s;
}

// Build normalized map name->id
$__cat_map_norm = [];
foreach ($categories as $__c) {
    $name = __normalize_ar((string)$__c['name']);
    if ($name !== '') $__cat_map_norm[$name] = (int)$__c['id'];
}

function __find_cat_id_synonyms(array $names, array $mapNorm): int {
    $candidates = [];
    foreach ($names as $n) {
        $nn = __normalize_ar((string)$n);
        if ($nn === '') continue;
        if (isset($mapNorm[$nn])) return (int)$mapNorm[$nn];
        // partial contains both ways
        foreach ($mapNorm as $k => $id) {
            if ($k === '') continue;
            if (mb_strpos($k, $nn) !== false || mb_strpos($nn, $k) !== false) {
                $candidates[$k] = (int)$id;
            }
        }
    }
    // pick the longest key match if any
    if ($candidates) {
        uasort($candidates, function($aId, $bId){ return 0; });
        // just return first candidate (order not critical)
        return (int)array_values($candidates)[0];
    }
    return 0;
}

// Synonyms per slider section (expandable)
$names_electronics = ['إلكترونيات','الكترونيات','اجهزه','أجهزة','هواتف','جوالات','موبايلات','حاسبات','حواسيب','كمبيوتر','كمبيوترات'];
$names_services    = ['خدمات','الخدمات','صيانة','توصيل','نقل عفش','تنظيف','برمجة','تصميم'];
$names_cars        = ['سيارات','سيارة','مركبات','مركبه','شاحنات','دراجات','دراجات نارية','نقل'];
$names_realestate  = ['عقارات','عقار','اراضي','أراضي','شقق','فلل','عمائر'];
$names_jobs        = ['وظائف','وظيفة','توظيف','فرص عمل'];

$__cat_electronics = __find_cat_id_synonyms($names_electronics, $__cat_map_norm);
$__cat_services    = __find_cat_id_synonyms($names_services, $__cat_map_norm);
$__cat_cars        = __find_cat_id_synonyms($names_cars, $__cat_map_norm);
$__cat_realestate  = __find_cat_id_synonyms($names_realestate, $__cat_map_norm);
$__cat_jobs        = __find_cat_id_synonyms($names_jobs, $__cat_map_norm);
?>

<!-- بطاقات استكشاف سريعة للأقسام الجذرية -->
<section class="card-modern" style="padding:16px 14px; border-radius:18px; margin: 14px 0 24px;">
    <h2 style="margin:0 0 10px;">استكشف الأقسام</h2>
    <div class="categories-grid modern-grid" style="margin-bottom:0;">
        <?php
        $icon_map = [ 'سيارات'=>'🚗','سيارة'=>'🚗','سيارات ومركبات'=>'🚗','الكترونيات'=>'📱','إلكترونيات'=>'📱','هواتف'=>'📱','أجهزة'=>'💻','عقارات'=>'🏠','عقار'=>'🏠','وظائف'=>'💼','خدمات'=>'🛠️' ];
        $shown = 0;
        foreach ($categories as $cat) {
            if (empty($cat['parent_id']) && $shown < 8) {
                $emoji = $icon_map[$cat['name']] ?? '📂';
                echo '<a class="category-card card-modern" href="category.php?category_id=' . (int)$cat['id'] . '">';
                echo '<div class="category-icon">' . $emoji . '</div>';
                echo '<div class="category-name">' . htmlspecialchars($cat['name']) . '</div>';
                echo '</a>';
                $shown++;
            }
        }
        ?>
    </div>
</section>

<!-- سلايدر مبسط لأفكار شائعة -->
<section class="homepage-slider">
    <!-- كل شريحة تمثل قسماً رئيسياً في الموقع مع صورة ووصف ورابط -->
    <div class="slide">
        <?php $hrefTech = $__cat_electronics ? ('browse.php?category_id=' . $__cat_electronics) : 'search.php?keyword=الكترونيات'; ?>
        <a href="<?php echo $hrefTech; ?>" class="slide-link">
            <img src="slider_images/tech.png" alt="منتجات تكنولوجية" />
            <div class="slide-caption">
                <h3>منتجات تكنولوجية</h3>
                <p>اكتشف أحدث الهواتف، الأجهزة اللوحية والحواسيب</p>
            </div>
        </a>
    </div>
    <div class="slide">
        <?php $hrefServices = $__cat_services ? ('browse.php?category_id=' . $__cat_services) : 'search.php?keyword=خدمات'; ?>
        <a href="<?php echo $hrefServices; ?>" class="slide-link">
            <img src="slider_images/services.png" alt="خدمات محلية" />
            <div class="slide-caption">
                <h3>خدمات محلية</h3>
                <p>توصيل، صيانة، وتسوق والمزيد من الخدمات القريبة منك</p>
            </div>
        </a>
    </div>
    <div class="slide">
        <?php $hrefCars = $__cat_cars ? ('browse.php?category_id=' . $__cat_cars) : 'search.php?keyword=سيارات'; ?>
        <a href="<?php echo $hrefCars; ?>" class="slide-link">
            <img src="slider_images/car.png" alt="إعلانات سيارات" />
            <div class="slide-caption">
                <h3>إعلانات سيارات</h3>
                <p>ابحث عن سيارتك الجديدة أو المستعملة بأفضل الأسعار</p>
            </div>
        </a>
    </div>
    <div class="slide">
        <?php $hrefReal = $__cat_realestate ? ('browse.php?category_id=' . $__cat_realestate) : 'search.php?keyword=عقارات'; ?>
        <a href="<?php echo $hrefReal; ?>" class="slide-link">
            <img src="slider_images/realestate.png" alt="إعلانات عقارات" />
            <div class="slide-caption">
                <h3>إعلانات عقارات</h3>
                <p>شقق، فلل، وأراضٍ للبيع أو للإيجار في منطقتك</p>
            </div>
        </a>
    </div>
    <div class="slide">
        <?php $hrefJobs = $__cat_jobs ? ('browse.php?category_id=' . $__cat_jobs) : 'search.php?keyword=وظائف'; ?>
        <a href="<?php echo $hrefJobs; ?>" class="slide-link">
            <img src="slider_images/jobs.png" alt="إعلانات وظائف" />
            <div class="slide-caption">
                <h3>إعلانات وظائف</h3>
                <p>فرص عمل ووظائف شاغرة في مختلف المجالات</p>
            </div>
        </a>
    </div>
    <!-- نقاط التنقل بين الشرائح -->
    <div class="slider-dots">
        <span class="dot" data-index="0"></span>
        <span class="dot" data-index="1"></span>
        <span class="dot" data-index="2"></span>
        <span class="dot" data-index="3"></span>
        <span class="dot" data-index="4"></span>
    </div>
</section>

<script>
// برنامج بسيط لتدوير الشرائح تلقائياً مع إمكانية التنقل عبر النقاط
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.homepage-slider .slide');
    const dots = document.querySelectorAll('.homepage-slider .dot');
    let currentIndex = 0;

    function showSlide(index) {
        slides.forEach((slide, i) => {
            slide.style.display = i === index ? 'block' : 'none';
            if (dots[i]) {
                dots[i].classList.toggle('active', i === index);
            }
        });
    }

    function nextSlide() {
        currentIndex = (currentIndex + 1) % slides.length;
        showSlide(currentIndex);
    }

    // إظهار أول شريحة
    showSlide(currentIndex);
    // بدء التدوير التلقائي كل خمس ثوانٍ
    const interval = setInterval(nextSlide, 5000);

    // عند الضغط على أي نقطة، الانتقال إلى الشريحة المقابلة
    dots.forEach(dot => {
        dot.addEventListener('click', () => {
            const idx = parseInt(dot.dataset.index, 10);
            currentIndex = idx;
            showSlide(currentIndex);
        });
    });
});
</script>



<!-- مدن شائعة -->
<?php if (!empty($city_counts)): ?>
<section class="card-modern" style="padding:14px; border-radius:16px; margin: 12px 0 24px;">
    <h2 style="margin:0 0 10px;">ابحث سريعاً حسب المدينة</h2>
        <div class="chip-filter-container" style="display:flex; justify-content: space-between; align-items:center; gap:8px; margin-bottom:8px;">
                <input type="text" class="input-modern" id="cityFilter" placeholder="ابحث عن مدينة..." style="max-width:260px;">
                <small style="color:#ccc">مرتبة حسب الأكثر إعلانات</small>
        </div>
        <div class="chips" id="cityChips">
            <?php $i=0; foreach ($city_counts as $row): $city = $row['city']; $cnt = (int)$row['count']; $i++; ?>
                <a class="chip<?php echo ($i > 30 ? ' extra-city' : ''); ?>" style="<?php echo ($i > 30 ? 'display:none' : ''); ?>" href="browse.php?city=<?php echo urlencode($city); ?>">🏙️ <?php echo htmlspecialchars($city); ?> <small style="opacity:.85">(<?php echo $cnt; ?>)</small></a>
            <?php endforeach; ?>
        </div>
        <?php if (count($city_counts) > 30): ?>
        <div style="margin-top:8px; text-align:center;">
            <button type="button" id="toggleMoreCities" class="btn-modern" style="min-width:160px;">عرض المزيد</button>
        </div>
        <?php endif; ?>
</section>
<?php endif; ?>

<script>
// تصفية سريعة لمدن الشيبس حسب ما يكتبه المستخدم
document.addEventListener('DOMContentLoaded', function() {
    var input = document.getElementById('cityFilter');
    var container = document.getElementById('cityChips');
    if (!input || !container) return;
    input.addEventListener('input', function() {
        var q = (input.value || '').toLowerCase();
        var chips = container.querySelectorAll('.chip');
        chips.forEach(function(chip){
            var name = chip.getAttribute('data-name') || chip.textContent.toLowerCase();
            var show = !q || name.indexOf(q) !== -1;
            chip.style.display = show ? 'inline-flex' : 'none';
        });
    });
    // زر عرض المزيد/أقل
    var toggleBtn = document.getElementById('toggleMoreCities');
    if (toggleBtn) {
        var expanded = false;
        toggleBtn.addEventListener('click', function(){
            var extras = container.querySelectorAll('.extra-city');
            extras.forEach(function(el){ el.style.display = expanded ? 'none' : 'inline-flex'; });
            expanded = !expanded;
            toggleBtn.textContent = expanded ? 'عرض أقل' : 'عرض المزيد';
        });
    }
});
</script>

<!-- موضوعات شائعة داخل أفضل الأقسام -->
<?php if (!empty($popular_roots)): ?>
<section class="card-modern" style="padding:14px; border-radius:16px; margin: 12px 0 24px;">
    <h2 style="margin:0 0 10px;">موضوعات رائجة</h2>
    <?php foreach ($popular_roots as $root): $smart = get_smart_subtopics((int)$root['id'], 8); if (empty($smart)) continue; ?>
        <h4 style="margin:10px 0 6px;"><?php echo htmlspecialchars($root['name']); ?> <small style="color:#bbb">(<?php echo (int)$root['ads_count']; ?>)</small></h4>
    <div class="chips" style="margin-bottom:12px;">
            <?php foreach ($smart as $s):
                $name = $s['name'] ?? ($s['label'] ?? '');
                $cnt  = (int)($s['cnt'] ?? ($s['count'] ?? 0));
                $url  = '#';
                if (($s['type'] ?? '') === 'category') {
                    $url = 'browse.php?category_id=' . (int)$s['id'];
                } else {
                    $key = $s['key'] ?? '';
                    $val = $s['value'] ?? '';
                    $q = [];
                    if ($key === 'car_make') { $q['car_make'] = $val; $q['category_id'] = 1; }
                    elseif ($key === 'car_model') { $q['car_model'] = $val; $q['category_id'] = 1; }
                    elseif ($key === 'car_year') { $q['car_year_from'] = $val; $q['car_year_to'] = $val; $q['category_id'] = 1; }
                    elseif ($key === 'device_brand') { $q['device_brand'] = $val; $q['category_id'] = 9; }
                    elseif ($key === 'device_model') { $q['device_model'] = $val; $q['category_id'] = 9; }
                    if (!empty($q)) {
                        $parts = [];
                        foreach ($q as $k => $v) { $parts[] = urlencode($k) . '=' . urlencode($v); }
                        $url = 'browse.php?' . implode('&', $parts);
                    } else { $url = 'browse.php?keyword=' . urlencode($name); }
                }
            ?>
        <a class="chip" href="<?php echo htmlspecialchars($url); ?>"># <?php echo htmlspecialchars($name); ?> <small style="opacity:.85">(<?php echo $cnt; ?>)</small></a>
            <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- إعلانات مميزة -->
<?php if (!empty($featured_ads)): ?>
<h2>إعلانات مميزة</h2>
<div class="listings-grid modern-grid">
    <?php foreach ($featured_ads as $ad): ?>
    <div class="listing-card card-modern is-featured">
            <?php $images = get_ad_images($ad['id']); $img = $images ? $images[0]['image_path'] : 'placeholder.png'; ?>
            <a href="ad_details.php?id=<?php echo $ad['id']; ?>">
        <img loading="lazy" src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" class="listing-image">
            </a>
            <div class="ad-info">
        <span class="badge-featured">مميز</span>
                <h3 class="listing-title"><a href="ad_details.php?id=<?php echo $ad['id']; ?>"><?php echo htmlspecialchars($ad['title']); ?></a></h3>
                <p class="listing-price"><?php echo number_format($ad['price'], 2); ?> ر.س</p>
                <p class="listing-category">قسم: <?php echo htmlspecialchars($ad['category_name']); ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- أحدث الإعلانات -->
<h2>أحدث الإعلانات</h2>
<div class="listings-grid modern-grid">
    <?php foreach ($latest_ads as $ad): ?>
        <div class="listing-card card-modern">
            <?php
            // الحصول على أول صورة للإعلان
            $images = get_ad_images($ad['id']);
            $img = $images ? $images[0]['image_path'] : 'placeholder.png';
            ?>
            <a href="ad_details.php?id=<?php echo $ad['id']; ?>">
                <img loading="lazy" src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" class="listing-image">
            </a>
            <div class="ad-info">
                <h3 class="listing-title"><a href="ad_details.php?id=<?php echo $ad['id']; ?>"><?php echo htmlspecialchars($ad['title']); ?></a></h3>
                <p class="listing-price">
                    <?php echo number_format($ad['price'], 2); ?> ر.س
                </p>
                <p class="listing-category">قسم: <?php echo htmlspecialchars($ad['category_name']); ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<p style="margin-top:12px;"><a class="btn" href="browse.php">عرض المزيد</a> <a class="btn" href="add_ad.php" style="margin-inline-start:8px;">+ أضف إعلانك</a></p>

<?php
require_once __DIR__ . '/footer.php';