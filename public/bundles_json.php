<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache');
require_once __DIR__ . '/../core/Database.php';

$db = \Core\Database::getInstance();
$bundles = $db->fetchAll("SELECT id, name, total_price FROM shop_bundles WHERE is_active = 1");

foreach ($bundles as &$b) {
    $products = $db->fetchAll(
        "SELECT bp.product_id, bp.quantity, (p.price * 10) as price FROM shop_bundle_products bp
         JOIN shop_products p ON p.id = bp.product_id
         WHERE bp.bundle_id = ?",
        [$b['id']]
    );
    $b['products'] = $products;
    $b['total_price'] = (float)$b['total_price'] * 10;
}

echo json_encode($bundles);
