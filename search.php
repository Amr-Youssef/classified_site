<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// البحث بالكلمة المفتاحية فقط
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$ads = [];
$cats = [];
if ($keyword) {
    // نتائج الأقسام المقترحة أولاً
    $cats = search_categories($keyword, 8);
    // نتائج الإعلانات المتقدمة
    $ads = search_ads_advanced($keyword);
}
require_once __DIR__ . '/header.php';
?>


<?php if ($keyword): ?>
    <h3>نتائج البحث عن: <?php echo htmlspecialchars($keyword); ?></h3>
    <?php if (!empty($cats)): ?>
        <h4>أقسام متطابقة</h4>
        <ul class="search-cats" style="list-style:none;padding:0;margin:8px 0 16px;display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ($cats as $c): ?>
                <li>
                    <a class="btn" href="browse.php?category_id=<?php echo (int)$c['id']; ?>" style="display:inline-flex;gap:6px;align-items:center;background:#f1f3f5;color:#0078d4;border:1px solid #d0d7de;border-radius:999px;padding:6px 12px;text-decoration:none;">
                        <span>📁 <?php echo htmlspecialchars($c['name']); ?></span>
                        <small style="color:#555">(<?php echo (int)$c['ads_count']; ?>)</small>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if (!$ads): ?>
        <p>لم يتم العثور على نتائج.</p>
    <?php else: ?>
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
                    <div class="ad-info">
                        <h3 class="listing-title"><a href="ad_details.php?id=<?php echo $ad['id']; ?>"><?php echo htmlspecialchars($ad['title']); ?></a></h3>
                        <p class="listing-price"><?php echo number_format($ad['price'], 2); ?> ر.س</p>
                        <p class="listing-category">قسم: <?php echo htmlspecialchars($ad['category_name']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>