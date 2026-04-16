<?php

require_once __DIR__ . '/../../src/db.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = get_pdo();

$rows = $pdo->query('SELECT id, name FROM provinces ORDER BY name')->fetchAll();
echo json_encode($rows, JSON_UNESCAPED_UNICODE);

?>
