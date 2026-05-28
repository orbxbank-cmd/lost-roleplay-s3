<?php
require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../core/Session.php';
require_once __DIR__ . '/../../../core/Logger.php';
require_once __DIR__ . '/../../../core/Auth.php';
\Core\Session::start();
\Core\Auth::requireAdmin();

$app = require __DIR__ . '/../../../config/app.php';
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?= $app['app_name'] ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../public/assets/css/style.css">
    <style>
        .admin-layout {
            display: grid;
            grid-template-columns: 230px 1fr;
            min-height: 100vh;
        }
        .admin-sidebar {
            background: #0d1117;
            border-right: 1px solid var(--border);
            padding: 1.5rem 1rem;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .admin-sidebar h3 {
            color: var(--info);
            margin-bottom: 1.5rem;
            font-size: 1.05rem;
            font-family: var(--header-font);
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .admin-sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.65rem 0.8rem;
            color: var(--text-secondary);
            border-radius: 6px;
            margin-bottom: 0.2rem;
            transition: all 0.2s;
            font-size: 0.85rem;
            font-family: var(--header-font);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .admin-sidebar a:hover,
        .admin-sidebar a.active {
            background: rgba(13, 110, 253, 0.1);
            color: var(--primary);
        }
        .admin-content {
            padding: 2rem;
            background: var(--bg-dark);
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
            font-size: 1.4rem;
            font-family: var(--header-font);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.2rem 1.5rem;
        }
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary);
            font-family: var(--header-font);
        }
        .stat-card .stat-label {
            color: var(--text-muted);
            font-size: 0.8rem;
            margin-top: 0.2rem;
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
        <h3><i class="fas fa-gamepad"></i> Lost RP</h3>
        <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>"><i class="fas fa-chart-bar"></i> Dashboard</a>
        <a href="products.php" class="<?= $currentPage === 'products.php' ? 'active' : '' ?>"><i class="fas fa-box"></i> Products</a>
        <a href="orders.php" class="<?= $currentPage === 'orders.php' ? 'active' : '' ?>"><i class="fas fa-clipboard-list"></i> Orders</a>
        <a href="users.php" class="<?= $currentPage === 'users.php' ? 'active' : '' ?>"><i class="fas fa-users"></i> Users</a>
        <a href="applications.php" class="<?= $currentPage === 'applications.php' ? 'active' : '' ?>"><i class="fas fa-clipboard-check"></i> Applications</a>
        <a href="coin_purchases.php" class="<?= $currentPage === 'coin_purchases.php' ? 'active' : '' ?>"><i class="fas fa-coins"></i> Coin Purchases</a>
        <a href="bundles.php" class="<?= $currentPage === 'bundles.php' ? 'active' : '' ?>"><i class="fas fa-gift"></i> Bundles</a>
        <hr style="border-color: var(--border); margin: 1rem 0;">
        <a href="../../public/index.php"><i class="fas fa-store"></i> Back to Shop</a>
        <a href="../../public/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </aside>
    <main class="admin-content">
