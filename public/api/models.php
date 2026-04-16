<?php
require_once __DIR__ . '/../../src/db.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if ($method === 'GET') {
    if ($id) {
        $stmt = $pdo->prepare('SELECT m.*, b.name as brand_name FROM models m LEFT JOIN brands b ON b.id = m.brand_id WHERE m.id = ?');
        $stmt->execute([$id]); $row = $stmt->fetch(); if(!$row){ http_response_code(404); echo json_encode(['error'=>'Modelo no encontrado'], JSON_UNESCAPED_UNICODE); exit; } echo json_encode($row, JSON_UNESCAPED_UNICODE); exit;
    }
    if (isset($_GET['all']) && $_GET['all']=='1'){
        $rows = $pdo->query('SELECT m.*, b.name as brand_name FROM models m LEFT JOIN brands b ON b.id = m.brand_id ORDER BY b.name, m.name')->fetchAll(); echo json_encode($rows, JSON_UNESCAPED_UNICODE); exit;
    }
    if (isset($_GET['brand_id'])){
        $stmt = $pdo->prepare('SELECT m.*, b.name as brand_name FROM models m LEFT JOIN brands b ON b.id = m.brand_id WHERE m.brand_id = ? AND m.active = 1 ORDER BY m.name'); $stmt->execute([(int)$_GET['brand_id']]); echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE); exit;
    }
    if (isset($_GET['active'])){ $stmt = $pdo->prepare('SELECT m.*, b.name as brand_name FROM models m LEFT JOIN brands b ON b.id = m.brand_id WHERE m.active = ? ORDER BY b.name, m.name'); $stmt->execute([(int)$_GET['active']]); echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE); exit; }
    $stmt = $pdo->prepare('SELECT m.*, b.name as brand_name FROM models m LEFT JOIN brands b ON b.id = m.brand_id WHERE m.active = 1 ORDER BY b.name, m.name'); $stmt->execute(); echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE); exit;
}

if ($method === 'POST'){
    if (empty($input['brand_id']) || empty($input['name'])){ http_response_code(422); echo json_encode(['error'=>'brand_id y name son obligatorios'], JSON_UNESCAPED_UNICODE); exit; }
    $stmt = $pdo->prepare('INSERT INTO models (brand_id, name, active) VALUES (?, ?, ?)'); $stmt->execute([(int)$input['brand_id'], $input['name'], isset($input['active'])?(int)$input['active']:1]); echo json_encode(['id'=>(int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE); exit;
}

if ($method === 'PUT'){
    if (!$id){ http_response_code(400); echo json_encode(['error'=>'Falta id'], JSON_UNESCAPED_UNICODE); exit; }
    $fields=[]; $params=[]; if(isset($input['brand_id'])){ $fields[]='brand_id=?'; $params[]=(int)$input['brand_id']; } if(isset($input['name'])){ $fields[]='name=?'; $params[]=$input['name']; } if(array_key_exists('active',$input)){ $fields[]='active=?'; $params[]=(int)$input['active']; }
    if(empty($fields)){ echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit; }
    $params[]=$id; $sql='UPDATE models SET '.implode(', ',$fields).' WHERE id = ?'; $stmt=$pdo->prepare($sql); $stmt->execute($params); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
}

if ($method === 'DELETE'){
    if(!$id){ http_response_code(400); echo json_encode(['error'=>'Falta id'], JSON_UNESCAPED_UNICODE); exit; }
    $stmt = $pdo->prepare('UPDATE models SET active = 0 WHERE id = ?'); $stmt->execute([$id]); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
}

http_response_code(405); echo json_encode(['error'=>'Método no permitido'], JSON_UNESCAPED_UNICODE);

