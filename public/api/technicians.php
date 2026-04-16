<?php

require_once __DIR__ . '/../../src/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if ($method === 'GET') {
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM technicians WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Técnico no encontrado'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode($row, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // List: by default return only active technicians for use in forms.
    // Pass `all=1` to get all technicians (admin view), or `active=0/1` to filter explicitly.
    if (isset($_GET['all']) && $_GET['all'] == '1') {
        $rows = $pdo->query('SELECT * FROM technicians ORDER BY created_at DESC, id DESC')->fetchAll();
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (isset($_GET['active'])) {
        $act = (int) $_GET['active'];
        $stmt = $pdo->prepare('SELECT * FROM technicians WHERE active = ? ORDER BY name ASC');
        $stmt->execute([$act]);
        $rows = $stmt->fetchAll();
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // default
    $stmt = $pdo->prepare('SELECT * FROM technicians WHERE active = 1 ORDER BY name ASC');
    $stmt->execute();
    $rows = $stmt->fetchAll();
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    if (empty($input['name'])) {
        http_response_code(422);
        echo json_encode(['error' => 'El nombre es obligatorio'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO technicians (name, phone, email, active) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $input['name'],
        $input['phone'] ?? null,
        $input['email'] ?? null,
        isset($input['active']) ? (int)$input['active'] : 1,
    ]);

    echo json_encode(['id' => (int) $pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'PUT') {
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Falta id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fields = [];
    $params = [];
    if (isset($input['name'])) { $fields[] = 'name = ?'; $params[] = $input['name']; }
    if (array_key_exists('phone', $input)) { $fields[] = 'phone = ?'; $params[] = $input['phone']; }
    if (array_key_exists('email', $input)) { $fields[] = 'email = ?'; $params[] = $input['email']; }
    if (array_key_exists('active', $input)) { $fields[] = 'active = ?'; $params[] = (int)$input['active']; }

    if (empty($fields)) {
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $params[] = $id;
    $sql = 'UPDATE technicians SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'DELETE') {
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Falta id'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Soft-delete: mark inactive
    $stmt = $pdo->prepare('UPDATE technicians SET active = 0 WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
