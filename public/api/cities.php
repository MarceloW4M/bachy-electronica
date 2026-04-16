<?php

require_once __DIR__ . '/../../src/db.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = get_pdo();
$province = isset($_GET['province_id']) ? (int)$_GET['province_id'] : null;
if ($province) {
    $stmt = $pdo->prepare('SELECT id, name, postal_code FROM cities WHERE province_id = ? ORDER BY name');
    $stmt->execute([$province]);
    $rows = $stmt->fetchAll();
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

$rows = $pdo->query('SELECT c.id, c.name, c.postal_code, p.name as province FROM cities c JOIN provinces p ON c.province_id = p.id ORDER BY p.name, c.name')->fetchAll();
echo json_encode($rows, JSON_UNESCAPED_UNICODE);

?>
