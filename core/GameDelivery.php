<?php

namespace Core;

use PDO;
use PDOException;

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Database.php';

class GameDelivery
{
    private ?PDO $gameDb = null;
    private bool $connected = false;
    private Database $localDb;
    private array $carModels;
    private array $jobMapping;
    private int $defaultHouseType;
    private int $defaultHousePrice;

    public function __construct()
    {
        $this->localDb = Database::getInstance();
        $this->carModels = [
            560, 562, 522, 411, 541, 559, 602, 429, 415, 451, 477, 480, 506, 533, 558, 603,
        ];
        $this->jobMapping = [
            'default' => 1,
        ];
        $this->defaultHouseType = 1;
        $this->defaultHousePrice = 500000;

        $this->connect();
    }

    private function connect(): void
    {
        try {
            $config = require __DIR__ . '/../config/game_database.php';
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset={$config['charset']}";
            $this->gameDb = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $this->connected = true;
        } catch (PDOException $e) {
            Logger::error('Game DB connection failed', ['error' => $e->getMessage()]);
            $this->connected = false;
        }
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function processAllPending(): array
    {
        $results = [];
        $deliveries = $this->localDb->fetchAll(
            "SELECT d.*, o.ingame_name as order_ingame, o.customer_name
             FROM shop_deliveries d
             JOIN shop_orders o ON o.id = d.order_id
             WHERE d.status = 'pending'
             ORDER BY d.id ASC"
        );

        foreach ($deliveries as $delivery) {
            $results[$delivery['id']] = $this->processDelivery($delivery);
        }

        return $results;
    }

    public function processByOrderId(int $orderId): array
    {
        $results = [];
        $deliveries = $this->localDb->fetchAll(
            "SELECT d.*, o.ingame_name as order_ingame, o.customer_name
             FROM shop_deliveries d
             JOIN shop_orders o ON o.id = d.order_id
             WHERE d.order_id = ? AND d.status = 'pending'",
            [$orderId]
        );

        foreach ($deliveries as $delivery) {
            $results[$delivery['id']] = $this->processDelivery($delivery);
        }

        return $results;
    }

    public function processDeliveryById(int $deliveryId): array
    {
        $delivery = $this->localDb->fetch(
            "SELECT d.*, o.ingame_name as order_ingame, o.customer_name
             FROM shop_deliveries d
             JOIN shop_orders o ON o.id = d.order_id
             WHERE d.id = ?",
            [$deliveryId]
        );

        if (!$delivery) {
            return ['success' => false, 'error' => 'Delivery not found'];
        }

        return $this->processDelivery($delivery);
    }

    private function processDelivery(array $delivery): array
    {
        if (!$this->connected) {
            $this->markFailed($delivery['id'], 'Game DB not connected');
            return ['success' => false, 'error' => 'Game DB not connected'];
        }

        $productName = $delivery['product_name'];
        $ingameName = $delivery['ingame_name'] ?: $delivery['order_ingame'] ?: $delivery['customer_name'];
        $quantity = (int)$delivery['quantity'];
        $metadata = !empty($delivery['metadata']) ? json_decode($delivery['metadata'], true) : null;

        try {
            $user = $this->findUser($ingameName);
            if (!$user) {
                $this->markFailed($delivery['id'], "User '$ingameName' not found in game DB");
                return ['success' => false, 'error' => "User '$ingameName' not found in game DB"];
            }

            $result = $this->executeProductDelivery($productName, $user, $quantity, $delivery);
            $this->completeDelivery($delivery['id']);

            Logger::info('Game delivery completed', [
                'delivery_id' => $delivery['id'],
                'product' => $productName,
                'ingame' => $ingameName,
            ]);

            return ['success' => true, 'action' => $result];
        } catch (\Exception $e) {
            $this->markFailed($delivery['id'], $e->getMessage());
            Logger::error('Game delivery failed', [
                'delivery_id' => $delivery['id'],
                'product' => $productName,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function findUser(string $name): ?array
    {
        $stmt = $this->gameDb->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$name]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    private function executeProductDelivery(string $productName, array $user, int $quantity, array $delivery): string
    {
        $normalizedName = trim(preg_replace('/\s+/', ' ', $productName));
        $metadata = !empty($delivery['metadata']) ? json_decode($delivery['metadata'], true) : null;

        // Promo: R5 + $250M + Sultan → admin 5 + money + Sultan
        if (stripos($normalizedName, 'r5') !== false && stripos($normalizedName, 'sultan') !== false) {
            return $this->deliverPromoR5($normalizedName, $user);
        }

        // Promo: $100M + 2 Sultan → money + 2 Sultans
        if (stripos($normalizedName, '$100m') !== false && stripos($normalizedName, 'sultan') !== false) {
            return $this->deliverPromo100M2Sultan($user);
        }

        // Specific cars (Sultan, NRG, Elegy)
        if (stripos($normalizedName, 'sultan') !== false || stripos($normalizedName, 'nrg') !== false || stripos($normalizedName, 'elegy') !== false) {
            return $this->deliverSpecificCars($normalizedName, $user, $quantity);
        }

        // Admin levels
        if (stripos($normalizedName, 'admin level') !== false || stripos($normalizedName, 'r5') !== false) {
            return $this->deliverAdminLevel($normalizedName, $user);
        }

        // Money ($100 Million, $250 Million, etc.)
        if (stripos($normalizedName, '$') !== false || stripos($normalizedName, 'million') !== false || stripos($normalizedName, 'billion') !== false) {
            return $this->deliverMoney($normalizedName, $user);
        }

        // Levels (+5 Levels, +10 Levels, etc.)
        if (preg_match('/^\+?(\d+)\s*levels?$/i', $normalizedName, $m)) {
            return $this->deliverLevels((int)$m[1], $user);
        }

        // Cars (N Cars)
        if (preg_match('/^(\d+)\s*cars?$/i', $normalizedName, $m)) {
            return $this->deliverCars((int)$m[1], $user, $metadata);
        }

        // House + Job bundle
        if (stripos($normalizedName, 'house') !== false && stripos($normalizedName, 'job') !== false) {
            return $this->deliverHouseAndJob($user);
        }

        // House only
        if (stripos($normalizedName, 'house') !== false) {
            return $this->deliverHouse($user);
        }

        // Job only
        if (stripos($normalizedName, 'job') !== false) {
            return $this->deliverJob($user);
        }

        // Manual action items
        if (stripos($normalizedName, 'unban') !== false) {
            throw new \Exception("'Unban Account' requires manual admin action");
        }

        if (stripos($normalizedName, 'name change') !== false) {
            throw new \Exception("'Name Change' requires manual admin action");
        }

        if (stripos($normalizedName, 'password reset') !== false) {
            throw new \Exception("'Password Reset' requires manual admin action");
        }

        throw new \Exception("Unknown product: '$normalizedName'");
    }

    private function deliverPromo100M2Sultan(array $user): string
    {
        $results = [];

        $stmt = $this->gameDb->prepare("UPDATE users SET cash = cash + ? WHERE uid = ?");
        $stmt->execute([100000000, $user['uid']]);
        $results[] = "Added $100,000,000 cash";

        $checkStmt = $this->gameDb->prepare("SELECT MAX(id) as max_id FROM vehicles");
        $checkStmt->execute();
        $row = $checkStmt->fetch();
        $nextId = ($row['max_id'] ?? 0) + 1;

        $stmt = $this->gameDb->prepare("INSERT INTO vehicles (id, ownerid, owner, modelid, price) VALUES (?, ?, ?, ?, 0)");
        $stmt->execute([$nextId, $user['uid'], $user['username'], 560]);
        $stmt->execute([$nextId + 1, $user['uid'], $user['username'], 560]);
        $results[] = "Added 2 Sultan (model 560)";

        return implode('; ', $results);
    }

    private function deliverR5Only(array $user): string
    {
        $stmt = $this->gameDb->prepare("UPDATE users SET adminlevel = ? WHERE uid = ?");
        $stmt->execute([5, $user['uid']]);
        return "Admin level set to 5";
    }

    private function deliverAdminLevel(string $name, array $user): string
    {
        $level = 1;
        if (stripos($name, '30') !== false || stripos($name, 'max') !== false) {
            $level = 30;
        } elseif (stripos($name, '10') !== false) {
            $level = 10;
        } elseif (stripos($name, '99') !== false || stripos($name, 'sys') !== false) {
            $level = 99;
        } else {
            $level = 1;
        }

        $stmt = $this->gameDb->prepare("UPDATE users SET adminlevel = ? WHERE uid = ?");
        $stmt->execute([$level, $user['uid']]);
        return "Admin level set to $level";
    }

    private function deliverMoney(string $name, array $user): string
    {
        $amount = 0;

        if (preg_match('/\$(\d+)\s*M/', $name, $m)) {
            $amount = (int)$m[1] * 1000000;
        } elseif (preg_match('/\$(\d+)\s*Billion/', $name, $m)) {
            $amount = (int)$m[1] * 1000000000;
        } elseif (stripos($name, 'million') !== false) {
            if (stripos($name, '100') !== false) $amount = 100000000;
            elseif (stripos($name, '250') !== false) $amount = 250000000;
            elseif (stripos($name, '500') !== false) $amount = 500000000;
        } elseif (stripos($name, 'billion') !== false) {
            $amount = 1000000000;
        }

        if ($amount <= 0) {
            throw new \Exception("Unrecognized money amount in: '$name'");
        }

        $stmt = $this->gameDb->prepare("UPDATE users SET cash = cash + ? WHERE uid = ?");
        $stmt->execute([$amount, $user['uid']]);
        return "Added $" . number_format($amount) . " cash";
    }

    private function deliverLevels(int $levels, array $user): string
    {
        $stmt = $this->gameDb->prepare("UPDATE users SET level = level + ? WHERE uid = ?");
        $stmt->execute([$levels, $user['uid']]);
        return "Added $levels levels";
    }

    private function deliverCars(int $count, array $user, ?array $metadata = null): string
    {
        $inserted = 0;

        $selectedModels = null;
        if ($metadata && isset($metadata['models']) && is_array($metadata['models']) && count($metadata['models']) > 0) {
            $selectedModels = $metadata['models'];
        }

        $checkStmt = $this->gameDb->prepare("SELECT MAX(id) as max_id FROM vehicles");
        $checkStmt->execute();
        $row = $checkStmt->fetch();
        $nextId = ($row['max_id'] ?? 0) + 1;

        $stmt = $this->gameDb->prepare(
            "INSERT INTO vehicles (id, ownerid, owner, modelid, price) VALUES (?, ?, ?, ?, 0)"
        );

        for ($i = 0; $i < $count; $i++) {
            if ($selectedModels !== null) {
                $modelId = $selectedModels[$i % count($selectedModels)];
            } else {
                $modelId = $this->carModels[$i % count($this->carModels)];
            }
            $stmt->execute([$nextId + $i, $user['uid'], $user['username'], $modelId]);
            $inserted++;
        }

        $details = $selectedModels ? ' (chosen models)' : '';
        return "Added $inserted vehicles$details";
    }

    private function deliverHouseAndJob(string $name, array $user): string
    {
        $houseResult = $this->deliverHouseInternal($user);
        $jobResult = $this->deliverJob($user);
        return "$houseResult; $jobResult";
    }

    private function deliverHouse(array $user): string
    {
        return $this->deliverHouseInternal($user);
    }

    private function deliverHouseInternal(array $user): string
    {
        $checkStmt = $this->gameDb->prepare("SELECT MAX(id) as max_id FROM houses");
        $checkStmt->execute();
        $row = $checkStmt->fetch();
        $nextId = ($row['max_id'] ?? 0) + 1;

        $stmt = $this->gameDb->prepare(
            "INSERT INTO houses (id, ownerid, owner, type, price) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$nextId, $user['uid'], $user['username'], $this->defaultHouseType, $this->defaultHousePrice]);

        return "House #$nextId assigned";
    }

    private function deliverJob(array $user): string
    {
        $jobId = $this->jobMapping['default'] ?? 1;
        $stmt = $this->gameDb->prepare("UPDATE users SET job = ? WHERE uid = ?");
        $stmt->execute([$jobId, $user['uid']]);
        return "Job #$jobId assigned";
    }

    private function deliverSpecificCars(string $name, array $user, int $quantity): string
    {
        $carCounts = [];
        if (preg_match('/(\d+)\s*Sultan/i', $name, $m)) $carCounts[560] = (int)$m[1];
        if (preg_match('/(\d+)\s*NRG/i', $name, $m)) $carCounts[522] = (int)$m[1];
        if (preg_match('/(\d+)\s*Elegy/i', $name, $m)) $carCounts[562] = (int)$m[1];

        if (empty($carCounts)) {
            return $this->deliverCars($quantity, $user);
        }

        $checkStmt = $this->gameDb->prepare("SELECT MAX(id) as max_id FROM vehicles");
        $checkStmt->execute();
        $row = $checkStmt->fetch();
        $nextId = ($row['max_id'] ?? 0) + 1;

        $stmt = $this->gameDb->prepare(
            "INSERT INTO vehicles (id, ownerid, owner, modelid, price) VALUES (?, ?, ?, ?, 0)"
        );

        $inserted = 0;
        foreach ($carCounts as $modelId => $count) {
            for ($i = 0; $i < $count; $i++) {
                $stmt->execute([$nextId + $inserted, $user['uid'], $user['username'], $modelId]);
                $inserted++;
            }
        }

        return "Added $inserted specific vehicles";
    }

    private function deliverPromoR5(string $name, array $user): string
    {
        $results = [];

        $stmt = $this->gameDb->prepare("UPDATE users SET adminlevel = ? WHERE uid = ?");
        $stmt->execute([5, $user['uid']]);
        $results[] = "Admin level set to 5";

        $amount = 250000000;
        $stmt = $this->gameDb->prepare("UPDATE users SET cash = cash + ? WHERE uid = ?");
        $stmt->execute([$amount, $user['uid']]);
        $results[] = "Added $" . number_format($amount) . " cash";

        $checkStmt = $this->gameDb->prepare("SELECT MAX(id) as max_id FROM vehicles");
        $checkStmt->execute();
        $row = $checkStmt->fetch();
        $nextId = ($row['max_id'] ?? 0) + 1;

        $stmt = $this->gameDb->prepare(
            "INSERT INTO vehicles (id, ownerid, owner, modelid, price) VALUES (?, ?, ?, ?, 0)"
        );
        $stmt->execute([$nextId, $user['uid'], $user['username'], 560]);
        $results[] = "Added Sultan (model 560)";

        return implode('; ', $results);
    }

    private function markFailed(int $deliveryId, string $reason): void
    {
        $this->localDb->update('deliveries', [
            'status' => 'failed',
        ], 'id = :id', ['id' => $deliveryId]);
    }

    private function completeDelivery(int $deliveryId): void
    {
        $this->localDb->update('deliveries', [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $deliveryId]);
    }
}
