<?php

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/jwt.php';

header('Content-Type: application/json; charset=utf-8');
$path = $_SERVER['REQUEST_URI'] ?? '/auth.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST' && preg_match('#/auth.php/login$#', $path)) {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $stmt = get_pdo()->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$data['username'] ?? '']);
    $user = $stmt->fetch();

    if ($user && password_verify($data['password'] ?? '', $user['password_hash'])) {
        echo json_encode(['token' => jwt_sign(['id' => (int) $user['id'], 'username' => $user['username'], 'role' => $user['role']])], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(401);
    echo json_encode(['error' => 'Credenciales inválidas'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'GET' && preg_match('#/auth.php/me$#', $path)) {
    $authorization = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if ($authorization === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (preg_match('/Bearer\s+(.*)$/i', $authorization, $matches)) {
        $payload = jwt_verify(trim($matches[1]));
        if ($payload) {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    http_response_code(401);
    echo json_encode(['error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST' && preg_match('#/auth.php/logout$#', $path)) {
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Ruta no encontrada'], JSON_UNESCAPED_UNICODE);
