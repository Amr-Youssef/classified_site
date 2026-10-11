<?php
require_once __DIR__ . '/../config.php';

try {
    // إنشاء جدول promotions إذا لم يكن موجوداً
    $sql = "CREATE TABLE IF NOT EXISTS promotions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ad_id INT NOT NULL,
        user_id INT NOT NULL,
        days INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        provider VARCHAR(32) NOT NULL DEFAULT 'mock',
        provider_ref VARCHAR(191) DEFAULT NULL,
        status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        paid_at DATETIME DEFAULT NULL,
        FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_ad_status (ad_id, status),
        INDEX idx_user (user_id),
        INDEX idx_ref (provider_ref)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $pdo->exec($sql);

    // تأكد من وجود premium_until في ads
    try {
        $pdo->exec("ALTER TABLE ads ADD COLUMN premium_until DATETIME NULL AFTER is_premium");
        echo "[migrate] premium_until column added to ads\n";
    } catch (Throwable $e) {
        // موجود مسبقاً
    }
    echo "[migrate] promotions table is ready\n";
} catch (Throwable $e) {
    echo "[migrate] Error: " . $e->getMessage() . "\n";
    exit(1);
}
echo "[migrate] Done.\n";
?>
