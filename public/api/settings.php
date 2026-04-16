<?php
require_once __DIR__ . '/../../src/db.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$key = isset($_GET['key']) ? trim((string)$_GET['key']) : null;
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if ($method === 'GET') {
    if ($key) {
        $stmt = $pdo->prepare('SELECT v FROM settings WHERE k = ?');
        $stmt->execute([$key]);
        $r = $stmt->fetch();
        if (!$r) { echo json_encode(null, JSON_UNESCAPED_UNICODE); exit; }
        echo json_encode($r['v'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $rows = $pdo->query('SELECT k, v FROM settings')->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[$r['k']] = $r['v'];
    echo json_encode($out, JSON_UNESCAPED_UNICODE); exit;
}

if ($method === 'PUT' || $method === 'POST') {
    if (empty($input['key'])) { http_response_code(422); echo json_encode(['error'=>'Missing key'], JSON_UNESCAPED_UNICODE); exit; }
    $k = trim((string)$input['key']);
    $v = isset($input['value']) ? (string)$input['value'] : '';
    $stmt = $pdo->prepare('INSERT INTO settings (`k`,`v`) VALUES (?,?) ON DUPLICATE KEY UPDATE `v` = VALUES(`v`), updated_at = CURRENT_TIMESTAMP');
    $stmt->execute([$k, $v]);
    echo json_encode(['ok'=>true,'key'=>$k,'value'=>$v], JSON_UNESCAPED_UNICODE); exit;
}

http_response_code(405); echo json_encode(['error'=>'Method not allowed'], JSON_UNESCAPED_UNICODE);
