<?php
require_once __DIR__ . '/../../src/db.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if ($method === 'GET') {
    if ($id) {
        $stmt = $pdo->prepare('SELECT s.*, i.name AS item_name, w.name AS warehouse_name FROM stocks s JOIN items i ON i.id = s.item_id JOIN warehouses w ON w.id = s.warehouse_id WHERE s.id = ?');
        $stmt->execute([$id]); $r = $stmt->fetch(); if (!$r) { http_response_code(404); echo json_encode(['error' => 'Stock no encontrado'], JSON_UNESCAPED_UNICODE); exit; } echo json_encode($r, JSON_UNESCAPED_UNICODE); exit;
    }
    if (isset($_GET['warehouse_id'])) {
        $stmt = $pdo->prepare('SELECT s.*, i.name AS item_name FROM stocks s JOIN items i ON i.id = s.item_id WHERE s.warehouse_id = ? ORDER BY i.name');
        $stmt->execute([(int)$_GET['warehouse_id']]); echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE); exit;
    }
    if (isset($_GET['item_id'])) {
        $stmt = $pdo->prepare('SELECT s.*, w.name AS warehouse_name FROM stocks s JOIN warehouses w ON w.id = s.warehouse_id WHERE s.item_id = ? ORDER BY w.name');
        $stmt->execute([(int)$_GET['item_id']]); echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE); exit;
    }
    echo json_encode($pdo->query('SELECT s.*, i.name AS item_name, w.name AS warehouse_name FROM stocks s JOIN items i ON i.id = s.item_id JOIN warehouses w ON w.id = s.warehouse_id ORDER BY w.name, i.name')->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    // create or upsert stock for item+warehouse
    if (empty($input['item_id']) || empty($input['warehouse_id'])) { http_response_code(422); echo json_encode(['error' => 'item_id y warehouse_id son obligatorios'], JSON_UNESCAPED_UNICODE); exit; }
    // Validate existence of item and warehouse
    $stmt = $pdo->prepare('SELECT id FROM items WHERE id = ?'); $stmt->execute([(int)$input['item_id']]); if(!$stmt->fetch()){ http_response_code(422); echo json_encode(['error'=>'item_id no encontrado'], JSON_UNESCAPED_UNICODE); exit; }
    $stmt = $pdo->prepare('SELECT id FROM warehouses WHERE id = ?'); $stmt->execute([(int)$input['warehouse_id']]); if(!$stmt->fetch()){ http_response_code(422); echo json_encode(['error'=>'warehouse_id no encontrado'], JSON_UNESCAPED_UNICODE); exit; }
    $qty = isset($input['quantity']) ? (int)$input['quantity'] : 0;
    $unit_price_ars = isset($input['unit_price']) && $input['unit_price'] !== '' ? (float)$input['unit_price'] : null;
    $usd_rate_at_entry = isset($input['usd_rate']) && $input['usd_rate'] !== '' ? (float)$input['usd_rate'] : null;
    // if usd_rate not provided, try to read current from settings
    if ($usd_rate_at_entry === null) {
        try {
            $r = $pdo->prepare('SELECT v FROM settings WHERE k = ?'); $r->execute(['usd_rate']); $rr = $r->fetch(); if ($rr) $usd_rate_at_entry = (float)$rr['v'];
        } catch (Throwable $e) { /* ignore */ }
    }
    // get previous quantity if any (and existing price)
    $stmt = $pdo->prepare('SELECT id, quantity, unit_price_ars, usd_rate_at_entry FROM stocks WHERE item_id = ? AND warehouse_id = ?'); $stmt->execute([(int)$input['item_id'], (int)$input['warehouse_id']]); $prev = $stmt->fetch();
    $prevQty = $prev ? (int)$prev['quantity'] : 0;
    if ($prev) {
        // stock exists: do not overwrite stored price/rate on upsert unless explicit flag provided (use POST param allow_price_update)
        $insertStmt = $pdo->prepare('INSERT INTO stocks (item_id, warehouse_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = CURRENT_TIMESTAMP');
        $insertStmt->execute([(int)$input['item_id'], (int)$input['warehouse_id'], $qty]);
        // keep previous price values for movement recording
        $record_unit_price = $prev['unit_price_ars'] ?? null;
        $record_usd_rate = $prev['usd_rate_at_entry'] ?? $usd_rate_at_entry;
    } else {
        // new stock row: set initial price and rate
        $insertStmt = $pdo->prepare('INSERT INTO stocks (item_id, warehouse_id, quantity, unit_price_ars, usd_rate_at_entry) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), unit_price_ars = VALUES(unit_price_ars), usd_rate_at_entry = VALUES(usd_rate_at_entry), updated_at = CURRENT_TIMESTAMP');
        $insertStmt->execute([(int)$input['item_id'], (int)$input['warehouse_id'], $qty, $unit_price_ars, $usd_rate_at_entry]);
        $record_unit_price = $unit_price_ars;
        $record_usd_rate = $usd_rate_at_entry;
    }
    // record movement if quantity changed (require stock_movements table)
    try{
        $delta = $qty - $prevQty;
        $mstmt = $pdo->prepare('INSERT INTO stock_movements (item_id, warehouse_id, quantity_before, quantity_after, delta, unit_price_ars, usd_rate_at_entry) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $mstmt->execute([(int)$input['item_id'], (int)$input['warehouse_id'], $prevQty, $qty, $delta, $record_unit_price, $record_usd_rate]);
    }catch(Throwable $e){ /* ignore if movements table missing */ }
    echo json_encode(['id' => (int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE); exit;
}

if ($method === 'PUT') {
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Falta id'], JSON_UNESCAPED_UNICODE); exit; }
    if (!array_key_exists('quantity', $input) && !array_key_exists('unit_price', $input) && !array_key_exists('usd_rate', $input)) { http_response_code(422); echo json_encode(['error' => 'quantity o unit_price/usd_rate es obligatorio'], JSON_UNESCAPED_UNICODE); exit; }
    // record previous
    $stmt = $pdo->prepare('SELECT item_id, warehouse_id, quantity FROM stocks WHERE id = ?'); $stmt->execute([$id]); $row = $stmt->fetch(); if(!$row){ http_response_code(404); echo json_encode(['error'=>'Stock no encontrado'], JSON_UNESCAPED_UNICODE); exit; }
    $prevQty = (int)$row['quantity'];
    $newQty = array_key_exists('quantity', $input) ? (int)$input['quantity'] : $prevQty;
    $unit_price_ars = array_key_exists('unit_price', $input) ? (float)$input['unit_price'] : $row['unit_price_ars'] ?? null;
    $usd_rate_at_entry = array_key_exists('usd_rate', $input) ? (float)$input['usd_rate'] : $row['usd_rate_at_entry'] ?? null;
    // Prevent price overwrite unless explicit force flag provided
    if (array_key_exists('unit_price', $input) && $row['unit_price_ars'] !== null && empty($input['force_price_update'])) {
        http_response_code(403); echo json_encode(['error' => 'Precio bloqueado. Envíe "force_price_update": true para forzar actualización.'], JSON_UNESCAPED_UNICODE); exit;
    }
    // update fields accordingly
    $ufields = ['quantity = ?']; $uparams = [$newQty];
    if ($unit_price_ars !== null) { $ufields[] = 'unit_price_ars = ?'; $uparams[] = $unit_price_ars; }
    if ($usd_rate_at_entry !== null) { $ufields[] = 'usd_rate_at_entry = ?'; $uparams[] = $usd_rate_at_entry; }
    $uparams[] = $id;
    $stmt = $pdo->prepare('UPDATE stocks SET ' . implode(', ', $ufields) . ', updated_at = CURRENT_TIMESTAMP WHERE id = ?'); $stmt->execute($uparams);
    try{ $mstmt = $pdo->prepare('INSERT INTO stock_movements (item_id, warehouse_id, quantity_before, quantity_after, delta, unit_price_ars, usd_rate_at_entry) VALUES (?, ?, ?, ?, ?, ?, ?)'); $mstmt->execute([(int)$row['item_id'], (int)$row['warehouse_id'], $prevQty, $newQty, $newQty - $prevQty, $unit_price_ars, $usd_rate_at_entry]); }catch(Throwable $e){ }
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE); exit;
}

if ($method === 'DELETE') {
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Falta id'], JSON_UNESCAPED_UNICODE); exit; }
    $stmt = $pdo->prepare('DELETE FROM stocks WHERE id = ?'); $stmt->execute([$id]); echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE); exit;
}

http_response_code(405); echo json_encode(['error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
