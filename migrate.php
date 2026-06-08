<?php
$host = getenv('DB_HOST') ?: '45.8.187.109';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 's82939_Lost100';
$user = getenv('DB_USER') ?: 's82939_Lost100';
$pass = getenv('DB_PASS') ?: '';

if (!$pass) {
    $config = require __DIR__ . '/config/database.php';
    $host = $config['host'];
    $port = $config['port'];
    $dbname = $config['dbname'];
    $user = $config['username'];
    $pass = $config['password'];
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Add metadata column to shop_deliveries
    $stmt = $pdo->query("SHOW COLUMNS FROM shop_deliveries LIKE 'metadata'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE shop_deliveries ADD COLUMN metadata JSON DEFAULT NULL AFTER `status`");
        echo "OK: Added metadata column to shop_deliveries\n";
    } else {
        echo "SKIP: metadata column already exists\n";
    }

    // 2. Update VIP plan prices
    $updates = [
        "UPDATE shop_vip_plans SET price_coins = 500, duration_days = 7 WHERE level = 1",
        "UPDATE shop_vip_plans SET price_coins = 1000, duration_days = 14 WHERE level = 2",
        "UPDATE shop_vip_plans SET price_coins = 2000, duration_days = 30 WHERE level = 3",
    ];
    foreach ($updates as $sql) {
        $pdo->exec($sql);
        echo "OK: $sql\n";
    }

} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
