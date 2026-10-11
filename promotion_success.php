<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!isset($_GET['id'])) { header('Location: index.php'); exit; }
$promo_id = (int)$_GET['id'];
$promo = get_promotion_by_id($promo_id);
if (!$promo) { header('Location: index.php'); exit; }
$ad = get_ad((int)$promo['ad_id']);

require_once __DIR__ . '/header.php';
?>
<div class="ad-details-wrapper">
  <div class="detail-card card-modern">
    <h3>تم تفعيل التمييز</h3>
    <p>تمت معالجة طلبك بنجاح. إعلانك الآن مميز لمدة <?php echo (int)$promo['days']; ?> يوم.</p>
    <p><a class="btn btn-modern" href="ad_details.php?id=<?php echo (int)$promo['ad_id']; ?>">الانتقال للإعلان</a></p>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
