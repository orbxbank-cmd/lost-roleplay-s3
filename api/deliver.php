<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Logger.php';

$db = \Core\Database::getInstance();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'pending':
        $deliveries = $db->fetchAll("
            SELECT d.id, d.order_id, d.ingame_name, d.product_name, d.quantity, d.created_at
            FROM shop_deliveries d
            WHERE d.status = 'pending'
            ORDER BY d.id ASC
            LIMIT 50
        ");
        echo json_encode(['success' => true, 'deliveries' => $deliveries]);
        break;

    case 'complete':
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $db->update('deliveries', [
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $id]);
            \Core\Logger::info('Delivery completed', ['delivery_id' => $id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        }
        break;

    case 'fail':
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $db->update('deliveries', ['status' => 'failed'], 'id = :id', ['id' => $id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
