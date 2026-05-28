<?php

namespace Core;

use PDO;

class Delivery
{
    private static ?PDO $gameDb = null;

    private static function getGameDB(): ?PDO
    {
        if (self::$gameDb !== null) return self::$gameDb;
        $cfgPath = __DIR__ . '/../config/game_database.php';
        if (!file_exists($cfgPath)) return null;
        $c = require $cfgPath;
        if (empty($c['host'])) return null;
        try {
            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['dbname']};charset={$c['charset']}";
            self::$gameDb = new PDO($dsn, $c['username'], $c['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (\Exception $e) {
            Logger::error('Game DB connection failed for delivery', ['error' => $e->getMessage()]);
            return null;
        }
        return self::$gameDb;
    }

    public static function deliverOrderItems(int $orderId): array
    {
        $db = Database::getInstance();
        $order = $db->fetch("SELECT * FROM shop_orders WHERE id = ?", [$orderId]);
        if (!$order) return ['success' => false, 'message' => 'Order not found'];

        $items = $db->fetchAll("SELECT * FROM shop_order_items WHERE order_id = ?", [$orderId]);
        if (empty($items)) return ['success' => false, 'message' => 'No items in order'];

        $gameDb = self::getGameDB();
        if (!$gameDb) return ['success' => false, 'message' => 'Game DB unavailable'];

        $ingameName = $order['ingame_name'] ?: $order['customer_name'];
        $results = [];
        $allSuccess = true;

        foreach ($items as $item) {
            $result = self::deliverItem($gameDb, $item, $ingameName);
            if (!$result['success']) $allSuccess = false;
            $results[] = $result;

            $db->insert('deliveries', [
                'order_id' => $orderId,
                'user_id' => $order['user_id'],
                'ingame_name' => $ingameName,
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'status' => $result['success'] ? 'completed' : 'failed',
            ]);
        }

        if ($allSuccess) {
            $db->update('orders', ['order_status' => 'delivered'], 'id = :id', ['id' => $orderId]);
        }

        return [
            'success' => $allSuccess,
            'message' => $allSuccess ? 'All items delivered' : 'Some items failed',
            'results' => $results,
        ];
    }

    private static function deliverItem(PDO $gameDb, array $item, string $ingameName): array
    {
        $name = $item['product_name'];
        $qty = (int)($item['quantity'] ?? 1);

        try {
            $userQ = $gameDb->prepare("SELECT uid, cash, bank, level, adminlevel FROM users WHERE username = ? LIMIT 1");
            $userQ->execute([$ingameName]);
            $player = $userQ->fetch();

            if (!$player) {
                $userQ = $gameDb->prepare("SELECT uid, cash, bank, level, adminlevel FROM users WHERE username = ? LIMIT 1");
                $userQ->execute([$ingameName]);
                $player = $userQ->fetch();
                if (!$player) return ['success' => false, 'item' => $name, 'message' => 'Player not found in game DB'];
            }

            if (stripos($name, 'admin level') !== false) {
                preg_match('/\d+/', $name, $m);
                $adminLevel = (int)($m[0] ?? 1);
                $currentAdmin = (int)($player['adminlevel'] ?? 0);
                if ($adminLevel > $currentAdmin) {
                    $gameDb->prepare("UPDATE users SET adminlevel = ? WHERE uid = ?")->execute([$adminLevel, $player['uid']]);
                }
                return ['success' => true, 'item' => $name, 'message' => "Admin level $adminLevel set"];
            }

            if (stripos($name, 'unban') !== false) {
                $gameDb->prepare("DELETE FROM bans WHERE username = ?")->execute([$ingameName]);
                return ['success' => true, 'item' => $name, 'message' => "Unbanned $ingameName"];
            }

            if (stripos($name, 'name change') !== false) {
                $gameDb->prepare("INSERT INTO changes (username, date, type) VALUES (?, NOW(), 'namechange')")->execute([$ingameName]);
                return ['success' => true, 'item' => $name, 'message' => 'Name change request logged'];
            }

            if (stripos($name, 'password reset') !== false) {
                $newPass = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                $newHash = strtoupper(hash('whirlpool', $newPass));
                $gameDb->prepare("UPDATE users SET password = ? WHERE uid = ?")->execute([$newHash, $player['uid']]);
                return ['success' => true, 'item' => $name, 'message' => "Password reset to: $newPass"];
            }

            if (stripos($name, 'house + job') !== false) {
                $gameDb->prepare("UPDATE users SET job = 1 WHERE uid = ?")->execute([$player['uid']]);
                return ['success' => true, 'item' => $name, 'message' => 'House + Job given'];
            }

            if (stripos($name, 'house') !== false && stripos($name, 'job') === false) {
                return ['success' => true, 'item' => $name, 'message' => 'House given'];
            }

            if (stripos($name, 'job') !== false && stripos($name, 'house') === false) {
                $gameDb->prepare("UPDATE users SET job = 1 WHERE uid = ?")->execute([$player['uid']]);
                return ['success' => true, 'item' => $name, 'message' => 'Job given'];
            }

            if (stripos($name, 'million') !== false || stripos($name, 'billion') !== false || $name[0] === '$') {
                preg_match('/[\d,.]+/', $name, $m);
                $amount = (float)str_replace(',', '', $m[0] ?? '0');
                if (stripos($name, 'billion') !== false) $amount *= 1000;
                $amount = (int)($amount * 1000000 * $qty);
                $gameDb->prepare("UPDATE users SET cash = cash + ? WHERE uid = ?")->execute([$amount, $player['uid']]);
                return ['success' => true, 'item' => $name, 'message' => "$amount cash added"];
            }

            if (stripos($name, 'level') !== false) {
                preg_match('/\d+/', $name, $m);
                $boost = (int)($m[0] ?? 5);
                $gameDb->prepare("UPDATE users SET level = level + ? WHERE uid = ?")->execute([$boost, $player['uid']]);
                return ['success' => true, 'item' => $name, 'message' => "+$boost levels added"];
            }

            if (stripos($name, 'car') !== false || stripos($name, 'sultan') !== false || stripos($name, 'nrg') !== false || stripos($name, 'elegy') !== false) {
                preg_match('/\d+/', $name, $m);
                $carCount = (int)($m[0] ?? 2);

                $models = [];
                if (stripos($name, 'sultan') !== false) $models = array_merge($models, array_fill(0, $qty * 4, 560));
                if (stripos($name, 'nrg') !== false) $models = array_merge($models, array_fill(0, $qty * 2, 522));
                if (stripos($name, 'elegy') !== false) $models = array_merge($models, array_fill(0, $qty * 2, 562));

                if (empty($models)) {
                    $carModels = [560, 562, 565, 587, 589, 596, 597, 598, 599, 602, 603, 405, 409, 411, 415, 419, 421, 426, 429, 433, 436, 439, 451, 475, 477, 478, 480, 489, 491, 492, 494, 496, 500, 502, 503, 504, 506, 507, 516, 517, 526, 527, 529, 533, 534, 535, 536, 540, 541, 542, 545, 546, 547, 549, 550, 551, 555, 558, 559, 561, 565, 566, 567, 568, 575, 576, 579, 580, 585, 587];
                    for ($i = 0; $i < $carCount; $i++) {
                        $models[] = $carModels[array_rand($carModels)];
                    }
                }

                $ins = $gameDb->prepare("INSERT INTO vehicles (ownerid, owner, modelid, price) VALUES (?, ?, ?, 0)");
                foreach ($models as $modelId) {
                    $ins->execute([$player['uid'], $ingameName, $modelId]);
                }
                return ['success' => true, 'item' => $name, 'message' => count($models) . ' cars given'];
            }

            return ['success' => false, 'item' => $name, 'message' => 'Unknown product type'];
        } catch (\Exception $e) {
            Logger::error('Delivery error', ['item' => $name, 'error' => $e->getMessage()]);
            return ['success' => false, 'item' => $name, 'message' => $e->getMessage()];
        }
    }
}
