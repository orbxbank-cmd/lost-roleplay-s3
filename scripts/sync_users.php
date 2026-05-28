<?php
// Sync existing local shop users with game UIDs
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';

$gameConfig = require __DIR__ . '/../config/game_database.php';
$dsn = "mysql:host={$gameConfig['host']};port={$gameConfig['port']};dbname={$gameConfig['dbname']};charset={$gameConfig['charset']}";
$gameDb = new PDO($dsn, $gameConfig['username'], $gameConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$db = \Core\Database::getInstance();
$users = $db->fetchAll("SELECT id, username FROM shop_users WHERE game_uid IS NULL");

$synced = 0;
foreach ($users as $user) {
    $stmt = $gameDb->prepare("SELECT uid FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$user['username']]);
    $gameUser = $stmt->fetch();
    if ($gameUser) {
        $db->query("UPDATE shop_users SET game_uid = ? WHERE id = ?", [$gameUser['uid'], $user['id']]);
        echo "Synced: {$user['username']} (ID {$user['id']}) -> game_uid {$gameUser['uid']}\n";
        $synced++;
    } else {
        echo "Not found in game: {$user['username']}\n";
    }
}

echo "\nDone. Synced $synced users.\n";
