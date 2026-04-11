<?php

require_once __DIR__ . '/../../src/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if ($method === 'GET') {
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Cliente no encontrado'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode($row, JSON_UNESCAPED_UNICODE);
        exit;
    }
    // support simple search for autocomplete: ?q=term
    if (isset($_GET['q'])) {
        $q = trim((string)$_GET['q']);
        $like = '%' . str_replace('%','\\%',$q) . '%';
        $stmt = $pdo->prepare('SELECT id, name, phone FROM customers WHERE name LIKE ? OR phone LIKE ? ORDER BY name LIMIT 50');
        $stmt->execute([$like, $like]);
        $rows = $stmt->fetchAll();
        echo json_encode($rows, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // support lookup by dni/cuit: ?dni=12345678
    if (isset($_GET['dni'])) {
        $dni = trim((string)$_GET['dni']);
        $dni_clean = preg_replace('/\D/', '', $dni);
        if ($dni_clean === '') {
            echo json_encode(null, JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmt = $pdo->prepare('SELECT id, name, dni_cuit FROM customers WHERE dni_cuit = ? LIMIT 1');
        $stmt->execute([$dni_clean]);
        $row = $stmt->fetch();
        if (!$row) {
            echo json_encode(null, JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode($row, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // support lookup by id_boot: ?id_boot=XYZ
    if (isset($_GET['id_boot'])) {
        $id_boot = trim((string)$_GET['id_boot']);
        if ($id_boot === '') {
            echo json_encode(null, JSON_UNESCAPED_UNICODE);
            exit;
        }
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id_boot = ? LIMIT 1');
        $stmt->execute([$id_boot]);
        $row = $stmt->fetch();
        if (!$row) {
            echo json_encode(null, JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode($row, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rows = $pdo->query('SELECT * FROM customers ORDER BY created_at DESC, id DESC')->fetchAll();
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST') {
    if (empty($input['name'])) {
        http_response_code(422);
        echo json_encode(['error' => 'El nombre es obligatorio'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validate DNI/CUIT: allow empty, otherwise only digits (max 32)
    $dni = isset($input['dni_cuit']) ? trim((string)$input['dni_cuit']) : null;
    if ($dni !== null && $dni !== '') {
        $dni_clean = preg_replace('/\D/', '', $dni);
        if ($dni_clean === '' || strlen($dni_clean) > 32) {
            http_response_code(422);
            echo json_encode(['error' => 'DNI/CUIT inválido'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        $dni_clean = null;
    }

    // Province / city handling: validate ids and derive postal_code from city if available
    $province_id = isset($input['province_id']) && $input['province_id'] !== '' ? (int)$input['province_id'] : null;
    $city_id = isset($input['city_id']) && $input['city_id'] !== '' ? (int)$input['city_id'] : null;
    $postal_code = null;
    if ($city_id) {
        $stmt = $pdo->prepare('SELECT province_id, postal_code FROM cities WHERE id = ?');
        $stmt->execute([$city_id]);
        $c = $stmt->fetch();
        if (!$c) {
            http_response_code(422);
            echo json_encode(['error' => 'Ciudad inválida'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($province_id && $c['province_id'] != $province_id) {
            http_response_code(422);
            echo json_encode(['error' => 'La ciudad no pertenece a la provincia seleccionada'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $province_id = (int)$c['province_id'];
        $postal_code = $c['postal_code'] ?? null;
    } else {
        $postal_code = isset($input['postal_code']) ? trim((string)$input['postal_code']) : null;
    }

    $stmt = $pdo->prepare('INSERT INTO customers (name, dni_cuit, phone, email, address, province_id, city_id, postal_code, contact_name, contact_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $input['name'],
        $dni_clean,
        $input['phone'] ?? null,
        $input['email'] ?? null,
        $input['address'] ?? null,
        $province_id,
        $city_id,
        $postal_code,
        $input['contact_name'] ?? null,
        $input['contact_phone'] ?? null,
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

    // Validate DNI/CUIT on update
    $dni = isset($input['dni_cuit']) ? trim((string)$input['dni_cuit']) : null;
    if ($dni !== null && $dni !== '') {
        $dni_clean = preg_replace('/\D/', '', $dni);
        if ($dni_clean === '' || strlen($dni_clean) > 32) {
            http_response_code(422);
            echo json_encode(['error' => 'DNI/CUIT inválido'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        $dni_clean = null;
    }

    // Province / city handling for update
    $province_id = isset($input['province_id']) && $input['province_id'] !== '' ? (int)$input['province_id'] : null;
    $city_id = isset($input['city_id']) && $input['city_id'] !== '' ? (int)$input['city_id'] : null;
    $postal_code = null;
    if ($city_id) {
        $stmt = $pdo->prepare('SELECT province_id, postal_code FROM cities WHERE id = ?');
        $stmt->execute([$city_id]);
        $c = $stmt->fetch();
        if (!$c) {
            http_response_code(422);
            echo json_encode(['error' => 'Ciudad inválida'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($province_id && $c['province_id'] != $province_id) {
            http_response_code(422);
            echo json_encode(['error' => 'La ciudad no pertenece a la provincia seleccionada'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $province_id = (int)$c['province_id'];
        $postal_code = $c['postal_code'] ?? null;
    } else {
        $postal_code = isset($input['postal_code']) ? trim((string)$input['postal_code']) : null;
    }

    // allow partial updates similar to suppliers API
    $fields = [];
    $params = [];
    if (isset($input['name'])){ $fields[] = 'name = ?'; $params[] = $input['name']; }
    if (array_key_exists('dni_cuit', $input)){ $fields[] = 'dni_cuit = ?'; $params[] = $dni_clean; }
    if (array_key_exists('phone', $input)){ $fields[] = 'phone = ?'; $params[] = $input['phone'] ?? null; }
    if (array_key_exists('email', $input)){ $fields[] = 'email = ?'; $params[] = $input['email'] ?? null; }
    if (array_key_exists('address', $input)){ $fields[] = 'address = ?'; $params[] = $input['address'] ?? null; }
    if (array_key_exists('province_id', $input)){ $fields[] = 'province_id = ?'; $params[] = $input['province_id'] !== null ? (int)$input['province_id'] : null; }
    if (array_key_exists('city_id', $input)){ $fields[] = 'city_id = ?'; $params[] = $input['city_id'] !== null ? (int)$input['city_id'] : null; }
    if (array_key_exists('postal_code', $input)){ $fields[] = 'postal_code = ?'; $params[] = $input['postal_code'] ?? null; }
    if (array_key_exists('contact_name', $input)){ $fields[] = 'contact_name = ?'; $params[] = $input['contact_name'] ?? null; }
    if (array_key_exists('contact_phone', $input)){ $fields[] = 'contact_phone = ?'; $params[] = $input['contact_phone'] ?? null; }

    if (empty($fields)){
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $params[] = $id;
    $sql = 'UPDATE customers SET ' . implode(', ', $fields) . ' WHERE id = ?';
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

    $stmt = $pdo->prepare('DELETE FROM customers WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
