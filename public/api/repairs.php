<?php

require_once __DIR__ . '/../../src/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$input = json_decode(file_get_contents('php://input'), true) ?: [];

function normalize_repair_status($status)
{
    $value = trim((string) ($status ?? ''));
    if ($value === '') {
        return 'en reparacion';
    }

    $normalized = strtolower($value);
    $normalized = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $normalized);

    if (in_array($normalized, ['pendiente', 'pending'], true)) {
        return 'pending';
    }

    if (in_array($normalized, ['recibida', 'received'], true)) {
        return 'received';
    }

    if (in_array($normalized, ['completada', 'done', 'concluido'], true)) {
        return 'completada';
    }

    if (in_array($normalized, ['en reparacion', 'en-reparacion', 'repairing'], true)) {
        return 'en reparacion';
    }

    return $value;
}

if ($method === 'GET' && isset($_GET['calendar'])) {
    $stmt = $pdo->query("SELECT r.id, r.device_model, r.status, COALESCE(r.scheduled_at, r.created_at) AS start_at, c.name AS customer_name, t.name AS technician_name FROM repairs r LEFT JOIN customers c ON c.id = r.customer_id LEFT JOIN technicians t ON t.id = r.technician_id ORDER BY start_at ASC");
    $events = [];
    foreach ($stmt->fetchAll() as $row) {
        $title = trim(($row['device_model'] ?: 'Reparación') . ' - ' . ($row['customer_name'] ?: 'Sin cliente'));
        if (!empty($row['technician_name'])) {
            $title .= ' (Tec: ' . $row['technician_name'] . ')';
        }
        $events[] = [
            'id' => (int) $row['id'],
            'title' => $title,
            'start' => $row['start_at'],
            'status' => $row['status'],
        ];
    }
    echo json_encode($events, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'GET') {
    if ($id) {
        $stmt = $pdo->prepare("SELECT r.*, c.name AS customer_name, c.contact_name AS customer_contact_name, c.contact_phone AS customer_contact_phone, c.email AS customer_email, t.name AS technician_name, d.serial AS device_serial, d.brand AS device_brand, d.model AS device_model FROM repairs r LEFT JOIN customers c ON c.id = r.customer_id LEFT JOIN technicians t ON t.id = r.technician_id LEFT JOIN devices d ON d.id = r.device_id WHERE r.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Reparación no encontrada'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode($row, JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Support optional filters: customer_id, q (search order_number or id), status
    $customer_id = isset($_GET['customer_id']) && $_GET['customer_id'] !== '' ? (int)$_GET['customer_id'] : null;
    $q = isset($_GET['q']) && strlen(trim($_GET['q'])) ? trim($_GET['q']) : null;
    $status = isset($_GET['status']) && strlen(trim($_GET['status'])) ? trim($_GET['status']) : null;

    $where = [];
    $params = [];

    if ($customer_id) {
        $where[] = 'r.customer_id = ?';
        $params[] = $customer_id;
    }

    if ($q) {
        // if numeric, allow matching id
        if (ctype_digit($q)) {
            $where[] = '(r.order_number LIKE ? OR r.id = ?)';
            $params[] = '%' . $q . '%';
            $params[] = (int)$q;
        } else {
            $where[] = 'r.order_number LIKE ?';
            $params[] = '%' . $q . '%';
        }
    }

    if ($status && $status !== 'all') {
        $where[] = 'r.status = ?';
        $params[] = $status;
    } else {
        // By default exclude completed orders (status = 'done', 'concluido' or 'completada')
        $where[] = "(r.status IS NULL OR LOWER(r.status) NOT IN ('done','concluido','completada'))";
    }

    $sql = 'SELECT r.*, c.name AS customer_name, c.contact_name AS customer_contact_name, c.contact_phone AS customer_contact_phone, c.email AS customer_email, t.name AS technician_name, d.serial AS device_serial, d.brand AS device_brand, d.model AS device_model FROM repairs r LEFT JOIN customers c ON c.id = r.customer_id LEFT JOIN technicians t ON t.id = r.technician_id LEFT JOIN devices d ON d.id = r.device_id';
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY COALESCE(r.scheduled_at, r.created_at) ASC, r.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    // generate next order_number like ORD-000001
    try{
        $r = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(order_number, 5) AS UNSIGNED)), 0) AS maxn FROM repairs WHERE order_number LIKE 'ORD-%'")->fetch();
        $next = ((int)($r['maxn'] ?? 0)) + 1;
        $order_number = sprintf('ORD-%06d', $next);
    }catch(Exception $e){ $order_number = null; }

    $order_date = isset($input['order_date']) && $input['order_date'] ? $input['order_date'] : date('Y-m-d H:i:s');

    $stmt = $pdo->prepare('INSERT INTO repairs (customer_id, device_model, problem, status, technician_id, scheduled_at, contact, device_id, order_number, order_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $input['customer_id'] ?? null,
        $input['device_model'] ?? null,
        $input['problem'] ?? null,
        normalize_repair_status($input['status'] ?? 'en reparacion'),
        $input['technician_id'] ?? null,
        $input['scheduled_at'] ?? null,
        $input['contact'] ?? null,
        $input['device_id'] ?? null,
        $order_number,
        $order_date,
    ]);
    echo json_encode(['id' => (int) $pdo->lastInsertId(), 'order_number' => $order_number, 'order_date' => $order_date], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'PUT') {
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Falta id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Prevent editing if order is completed (Completada / done)
    try{
        $chk = $pdo->prepare('SELECT status FROM repairs WHERE id = ?');
        $chk->execute([$id]);
        $cur = $chk->fetch();
        if($cur && isset($cur['status']) && in_array(strtolower($cur['status']), ['done','concluido','completada'])){
            http_response_code(403);
            echo json_encode(['error' => 'No se puede editar una orden concluida'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }catch(Throwable $e){ /* ignore and continue */ }

    $stmt = $pdo->prepare('UPDATE repairs SET customer_id = ?, device_id = ?, device_model = ?, problem = ?, status = ?, technician_id = ?, scheduled_at = ?, contact = ? WHERE id = ?');
    $stmt->execute([
        $input['customer_id'] ?? null,
        $input['device_id'] ?? null,
        $input['device_model'] ?? null,
        $input['problem'] ?? null,
        normalize_repair_status($input['status'] ?? 'en reparacion'),
        $input['technician_id'] ?? null,
        $input['scheduled_at'] ?? null,
        $input['contact'] ?? null,
        $id,
    ]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'DELETE') {
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Falta id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM repairs WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
