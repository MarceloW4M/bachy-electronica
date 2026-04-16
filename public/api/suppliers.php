<?php
require_once __DIR__ . '/../../src/db.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if($method==='GET'){
    if($id){ $stmt=$pdo->prepare('SELECT * FROM suppliers WHERE id=?'); $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ http_response_code(404); echo json_encode(['error'=>'Proveedor no encontrado'], JSON_UNESCAPED_UNICODE); exit;} echo json_encode($r, JSON_UNESCAPED_UNICODE); exit; }
    if(isset($_GET['all']) && $_GET['all']=='1'){ echo json_encode($pdo->query('SELECT * FROM suppliers ORDER BY name')->fetchAll(), JSON_UNESCAPED_UNICODE); exit; }
    if(isset($_GET['active'])){ $stmt=$pdo->prepare('SELECT * FROM suppliers WHERE active = ? ORDER BY name'); $stmt->execute([(int)$_GET['active']]); echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE); exit; }
    echo json_encode($pdo->query('SELECT * FROM suppliers WHERE active = 1 ORDER BY name')->fetchAll(), JSON_UNESCAPED_UNICODE); exit;
}

if($method==='POST'){
    if(empty($input['name'])){ http_response_code(422); echo json_encode(['error'=>'Nombre obligatorio'], JSON_UNESCAPED_UNICODE); exit; }
    $stmt=$pdo->prepare('INSERT INTO suppliers (name, contact, phone, email, address, cuit, contact_phone, province_id, city_id, postal_code, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $input['name'], $input['contact'] ?? null, $input['phone'] ?? null, $input['email'] ?? null,
        $input['address'] ?? null, $input['cuit'] ?? null, $input['contact_phone'] ?? null,
        isset($input['province_id']) ? (int)$input['province_id'] : null,
        isset($input['city_id']) ? (int)$input['city_id'] : null,
        $input['postal_code'] ?? null,
        isset($input['active'])?(int)$input['active']:1
    ]);
    echo json_encode(['id'=>(int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE); exit;
}

if($method==='PUT'){
    if(!$id){ http_response_code(400); echo json_encode(['error'=>'Falta id'], JSON_UNESCAPED_UNICODE); exit; }
    $fields=[]; $params=[];
    if(isset($input['name'])){ $fields[]='name=?'; $params[]=$input['name']; }
    if(array_key_exists('contact',$input)){ $fields[]='contact=?'; $params[]=$input['contact']; }
    if(array_key_exists('phone',$input)){ $fields[]='phone=?'; $params[]=$input['phone']; }
    if(array_key_exists('email',$input)){ $fields[]='email=?'; $params[]=$input['email']; }
    if(array_key_exists('address',$input)){ $fields[]='address=?'; $params[]=$input['address']; }
    if(array_key_exists('cuit',$input)){ $fields[]='cuit=?'; $params[]=$input['cuit']; }
    if(array_key_exists('contact_phone',$input)){ $fields[]='contact_phone=?'; $params[]=$input['contact_phone']; }
    if(array_key_exists('province_id',$input)){ $fields[]='province_id=?'; $params[]=$input['province_id']!==null ? (int)$input['province_id'] : null; }
    if(array_key_exists('city_id',$input)){ $fields[]='city_id=?'; $params[]=$input['city_id']!==null ? (int)$input['city_id'] : null; }
    if(array_key_exists('postal_code',$input)){ $fields[]='postal_code=?'; $params[]=$input['postal_code']; }
    if(array_key_exists('active',$input)){ $fields[]='active=?'; $params[]=(int)$input['active']; }
    if(empty($fields)){ echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit; }
    $params[]=$id; $sql='UPDATE suppliers SET '.implode(', ',$fields).' WHERE id=?'; $stmt=$pdo->prepare($sql); $stmt->execute($params); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
}

if($method==='DELETE'){
    if(!$id){ http_response_code(400); echo json_encode(['error'=>'Falta id'], JSON_UNESCAPED_UNICODE); exit; }
    $stmt=$pdo->prepare('UPDATE suppliers SET active = 0 WHERE id = ?'); $stmt->execute([$id]); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
}

http_response_code(405); echo json_encode(['error'=>'Método no permitido'], JSON_UNESCAPED_UNICODE);
