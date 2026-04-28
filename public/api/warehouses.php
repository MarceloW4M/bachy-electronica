<?php
require_once __DIR__ . '/../../src/db.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if($method==='GET'){
    if($id){ $stmt=$pdo->prepare('SELECT * FROM warehouses WHERE id=?'); $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ http_response_code(404); echo json_encode(['error'=>'Depósito no encontrado'], JSON_UNESCAPED_UNICODE); exit;} if(isset($r['location'])) $r['address'] = $r['location']; echo json_encode($r, JSON_UNESCAPED_UNICODE); exit; }
    if($id){ /* unreachable but keep */ }
    if(isset($_GET['all']) && $_GET['all']=='1'){
        $rows = $pdo->query('SELECT * FROM warehouses ORDER BY name')->fetchAll();
        foreach($rows as &$row){ if(isset($row['location'])) $row['address'] = $row['location']; }
        echo json_encode($rows, JSON_UNESCAPED_UNICODE); exit;
    }
    if(isset($_GET['active'])){
        $stmt=$pdo->prepare('SELECT * FROM warehouses WHERE active = ? ORDER BY name'); $stmt->execute([(int)$_GET['active']]); $rows = $stmt->fetchAll(); foreach($rows as &$row){ if(isset($row['location'])) $row['address'] = $row['location']; } echo json_encode($rows, JSON_UNESCAPED_UNICODE); exit; }
    $rows = $pdo->query('SELECT * FROM warehouses WHERE active = 1 ORDER BY name')->fetchAll(); foreach($rows as &$row){ if(isset($row['location'])) $row['address'] = $row['location']; } echo json_encode($rows, JSON_UNESCAPED_UNICODE); exit;
}

if($method==='POST'){
    if(empty($input['name'])){ http_response_code(422); echo json_encode(['error'=>'Nombre obligatorio'], JSON_UNESCAPED_UNICODE); exit; }
    $location = isset($input['location']) ? $input['location'] : (isset($input['address']) ? $input['address'] : null);
    $stmt=$pdo->prepare('INSERT INTO warehouses (name, location, active) VALUES (?, ?, ?)'); $stmt->execute([$input['name'], $location, isset($input['active'])?(int)$input['active']:1]); echo json_encode(['id'=>(int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE); exit;
}

if($method==='PUT'){
    if(!$id){ http_response_code(400); echo json_encode(['error'=>'Falta id'], JSON_UNESCAPED_UNICODE); exit; }
    $fields=[]; $params=[]; if(isset($input['name'])){ $fields[]='name=?'; $params[]=$input['name']; }
    if(array_key_exists('location',$input)){ $fields[]='location=?'; $params[]=$input['location']; }
    if(array_key_exists('address',$input)){ $fields[]='location=?'; $params[]=$input['address']; }
    if(array_key_exists('active',$input)){ $fields[]='active=?'; $params[]=(int)$input['active']; }
    if(empty($fields)){ echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit; }
    $params[]=$id; $sql='UPDATE warehouses SET '.implode(', ',$fields).' WHERE id=?'; $stmt=$pdo->prepare($sql); $stmt->execute($params); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
}

if($method==='DELETE'){
    if(!$id){ http_response_code(400); echo json_encode(['error'=>'Falta id'], JSON_UNESCAPED_UNICODE); exit; }
    $stmt=$pdo->prepare('UPDATE warehouses SET active = 0 WHERE id = ?'); $stmt->execute([$id]); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
}

http_response_code(405); echo json_encode(['error'=>'Método no permitido'], JSON_UNESCAPED_UNICODE);
