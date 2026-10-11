<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    // أضف عمود email_verified إن لم يكن موجوداً
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 0");
        echo "Added users.email_verified\n";
    } else {
        echo "users.email_verified already exists\n";
    }

    // أنشئ جدول email_verifications إن لم يكن موجوداً
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_created (user_id, created_at),
        UNIQUE KEY uniq_token (token),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Ensured email_verifications table\n";

    echo "Migration OK.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Migration failed: " . $e->getMessage() . "\n";
}
