<?php
require_once __DIR__ . '/../config.php';

function col_exists(PDO $pdo, string $table, string $column): bool {
    $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c";
    $stm = $pdo->prepare($sql);
    $stm->execute([':t'=>$table, ':c'=>$column]);
    return (int)$stm->fetchColumn() > 0;
}

function table_exists(PDO $pdo, string $table): bool {
    $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t";
    $stm = $pdo->prepare($sql);
    $stm->execute([':t'=>$table]);
    return (int)$stm->fetchColumn() > 0;
}

try {
    echo "Running Nafath DB migration...\n";
    // Add columns to users
    if (!col_exists($pdo, 'users', 'is_verified')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `is_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `id`");
        echo "- Added users.is_verified\n";
    } else { echo "- users.is_verified exists\n"; }

    if (!col_exists($pdo, 'users', 'nafath_uuid')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `nafath_uuid` VARCHAR(64) NULL AFTER `is_verified`");
        echo "- Added users.nafath_uuid\n";
    } else { echo "- users.nafath_uuid exists\n"; }

    if (!col_exists($pdo, 'users', 'nafath_verified_at')) {
        $pdo->exec("ALTER TABLE `users` ADD COLUMN `nafath_verified_at` DATETIME NULL AFTER `nafath_uuid`");
        echo "- Added users.nafath_verified_at\n";
    } else { echo "- users.nafath_verified_at exists\n"; }

    // Create nafath_verifications table
    if (!table_exists($pdo, 'nafath_verifications')) {
        $pdo->exec(<<<SQL
CREATE TABLE `nafath_verifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `national_id` VARCHAR(20) NOT NULL,
  `request_id` VARCHAR(128) NOT NULL,
  `status` ENUM('pending','approved','rejected','expired') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY `uniq_request` (`request_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_nafath_ver_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
        echo "- Created table nafath_verifications\n";
    } else { echo "- Table nafath_verifications exists\n"; }

    echo "Done.\n";
} catch (Throwable $e) {
    echo "Migration failed: ".$e->getMessage()."\n";
    exit(1);
}
