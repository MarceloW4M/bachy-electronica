<?php
require_once __DIR__ . '/../../src/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if ($method === 'GET') {
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM devices WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { http_response_code(404); echo json_encode(['error'=>'Not found'], JSON_UNESCAPED_UNICODE); exit; }
        // get photos
        $stmt2 = $pdo->prepare('SELECT id, filename FROM device_photos WHERE device_id = ?');
        $stmt2->execute([$id]);
        $row['photos'] = $stmt2->fetchAll();
        // include customer name/phone for UI
        if (!empty($row['customer_id'])) {
            $stmt3 = $pdo->prepare('SELECT name, phone FROM customers WHERE id = ?');
            $stmt3->execute([$row['customer_id']]);
            $cust = $stmt3->fetch();
            if ($cust) {
                $row['customer_name'] = $cust['name'];
                $row['phone'] = $cust['phone'];
            }
        }
        echo json_encode($row, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // support filtering by customer_id for device selection
    if (isset($_GET['customer_id'])) {
        $cid = (int)$_GET['customer_id'];
        $stmt = $pdo->prepare('SELECT d.*, c.name AS customer_name FROM devices d LEFT JOIN customers c ON c.id = d.customer_id WHERE d.customer_id = ? ORDER BY d.created_at DESC');
        $stmt->execute([$cid]);
        echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rows = $pdo->query('SELECT d.*, c.name AS customer_name FROM devices d LEFT JOIN customers c ON c.id = d.customer_id ORDER BY d.created_at DESC')->fetchAll();
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    // create
    $stmt = $pdo->prepare('INSERT INTO devices (customer_id, serial, brand, model, notes) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $input['customer_id'] ?? null,
        $input['serial'] ?? null,
        $input['brand'] ?? null,
        $input['model'] ?? null,
        $input['notes'] ?? null,
    ]);
    echo json_encode(['id' => (int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'PUT') {
    if (!$id) { http_response_code(400); echo json_encode(['error'=>'Missing id'], JSON_UNESCAPED_UNICODE); exit; }
    $stmt = $pdo->prepare('UPDATE devices SET customer_id = ?, serial = ?, brand = ?, model = ?, notes = ? WHERE id = ?');
    $stmt->execute([
        $input['customer_id'] ?? null,
        $input['serial'] ?? null,
        $input['brand'] ?? null,
        $input['model'] ?? null,
        $input['notes'] ?? null,
        $id,
    ]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'DELETE') {
    if (!$id) { http_response_code(400); echo json_encode(['error'=>'Missing id'], JSON_UNESCAPED_UNICODE); exit; }
    // deleting device will cascade to photos via fk
    $stmt = $pdo->prepare('DELETE FROM devices WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
