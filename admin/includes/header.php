<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Auth.php';
\Core\Session::start();
\Core\Auth::requireAdmin();

$app = require __DIR__ . '/../../config/app.php';
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - <?= $app['app_name'] ?></title>
    <link rel="stylesheet" href="../public/assets/css/style.css">
    <style>
        .admin-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        .admin-sidebar {
            background: var(--bg-card);
            border-left: 1px solid var(--border);
            padding: 1.5rem;
        }
        .admin-sidebar h3 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }
        .admin-sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.7rem 1rem;
            color: var(--text-secondary);
            border-radius: 8px;
            margin-bottom: 0.3rem;
            transition: all 0.2s;
        }
        .admin-sidebar a:hover,
        .admin-sidebar a.active {
            background: rgba(233, 30, 99, 0.1);
            color: var(--primary);
        }
        .admin-content {
            padding: 2rem;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }
        .admin-header h2 {
            font-size: 1.5rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
        }
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
        }
        .stat-card .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-top: 0.3rem;
        }
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <h3>🎮 Lost RP</h3>
        <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">📊 الإحصائيات</a>
        <a href="products.php" class="<?= $currentPage === 'products.php' ? 'active' : '' ?>">📦 المنتجات</a>
        <a href="orders.php" class="<?= $currentPage === 'orders.php' ? 'active' : '' ?>">📋 الطلبات</a>
        <a href="users.php" class="<?= $currentPage === 'users.php' ? 'active' : '' ?>">👥 المستخدمين</a>
        <a href="unban_requests.php" class="<?= $currentPage === 'unban_requests.php' ? 'active' : '' ?>">🔨 طلب فك الحظر</a>
        <a href="gift_cards.php" class="<?= $currentPage === 'gift_cards.php' ? 'active' : '' ?>">🎁 كودات الهدايا</a>
        <a href="coin_purchases.php" class="<?= $currentPage === 'coin_purchases.php' ? 'active' : '' ?>">🪙 طلبات الشحن</a>
        <a href="leaderboard_rewards.php" class="<?= $currentPage === 'leaderboard_rewards.php' ? 'active' : '' ?>">🏆 مكافآت التوب</a>
        <a href="deliver.php" class="<?= $currentPage === 'deliver.php' ? 'active' : '' ?>">🚚 توصيل مباشر</a>
        <hr style="border-color: var(--border); margin: 1rem 0;">
        <a href="../public/index.php">🏠 العودة للمتجر</a>
        <a href="../public/logout.php">🚪 تسجيل خروج</a>
    </aside>
    <main class="admin-content">
