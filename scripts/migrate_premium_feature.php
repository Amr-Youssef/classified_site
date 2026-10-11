<?php
// إضافة عمود premium_until إلى جدول ads إن لم يكن موجوداً
require_once __DIR__ . '/../config.php';

try {
    // افحص إن كان العمود موجوداً
    $stmt = $pdo->query("SHOW COLUMNS FROM ads LIKE 'premium_until'");
    $exists = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$exists) {
        $pdo->exec("ALTER TABLE ads ADD COLUMN premium_until DATETIME NULL AFTER is_premium");
        echo "[migrate] premium_until column added to ads\n";
    } else {
        echo "[migrate] premium_until already exists\n";
    }
    echo "[migrate] Done.\n";
} catch (Exception $e) {
    echo "[migrate] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}