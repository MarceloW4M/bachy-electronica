<?php
require_once __DIR__ . '/../../src/db.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if($method==='GET'){
    if($id){
        $stmt=$pdo->prepare('SELECT i.*, c.name AS category_name, b.name AS brand_name, m.name AS model_name FROM items i LEFT JOIN categories c ON c.id = i.category_id LEFT JOIN brands b ON b.id = i.brand_id LEFT JOIN models m ON m.id = i.model_id WHERE i.id=?');
        $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ http_response_code(404); echo json_encode(['error'=>'Artículo no encontrado'], JSON_UNESCAPED_UNICODE); exit;} echo json_encode($r, JSON_UNESCAPED_UNICODE); exit;
    }
    if(isset($_GET['all']) && $_GET['all']=='1'){ echo json_encode($pdo->query('SELECT i.*, c.name AS category_name, b.name AS brand_name, m.name AS model_name FROM items i LEFT JOIN categories c ON c.id = i.category_id LEFT JOIN brands b ON b.id = i.brand_id LEFT JOIN models m ON m.id = i.model_id ORDER BY i.name')->fetchAll(), JSON_UNESCAPED_UNICODE); exit; }
    if(isset($_GET['active'])){ $stmt=$pdo->prepare('SELECT i.*, c.name AS category_name, b.name AS brand_name, m.name AS model_name FROM items i LEFT JOIN categories c ON c.id = i.category_id LEFT JOIN brands b ON b.id = i.brand_id LEFT JOIN models m ON m.id = i.model_id WHERE i.active = ? ORDER BY i.name'); $stmt->execute([(int)$_GET['active']]); echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE); exit; }
    echo json_encode($pdo->query('SELECT i.*, c.name AS category_name, b.name AS brand_name, m.name AS model_name FROM items i LEFT JOIN categories c ON c.id = i.category_id LEFT JOIN brands b ON b.id = i.brand_id LEFT JOIN models m ON m.id = i.model_id WHERE i.active = 1 ORDER BY i.name')->fetchAll(), JSON_UNESCAPED_UNICODE); exit;
}

if($method==='POST'){
    if(empty($input['name'])){ http_response_code(422); echo json_encode(['error'=>'Nombre obligatorio'], JSON_UNESCAPED_UNICODE); exit; }
    // If SKU provided, ensure uniqueness
    $providedSku = isset($input['sku']) && trim((string)$input['sku']) !== '' ? trim((string)$input['sku']) : null;
    if ($providedSku) {
        $s = $pdo->prepare('SELECT id FROM items WHERE sku = ? LIMIT 1');
        $s->execute([$providedSku]);
        if ($s->fetch()) { http_response_code(422); echo json_encode(['error'=>'SKU ya existente'], JSON_UNESCAPED_UNICODE); exit; }
    }

    $stmt=$pdo->prepare('INSERT INTO items (name, sku, category_id, brand_id, model_id, barcode, qr_code, unit_price, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $input['name'], $providedSku, $input['category_id'] ?? null,
        $input['brand_id'] ?? null, $input['model_id'] ?? null,
        $input['barcode'] ?? null, $input['qr_code'] ?? null,
        isset($input['unit_price']) ? (float)$input['unit_price'] : 0,
        isset($input['active'])?(int)$input['active']:1
    ]);
    $newId = (int)$pdo->lastInsertId();

    // If no SKU provided, generate one based on ID (unique)
    if (!$providedSku) {
        $gen = 'IT-' . str_pad($newId, 6, '0', STR_PAD_LEFT);
        $u = $pdo->prepare('UPDATE items SET sku = ? WHERE id = ?');
        $u->execute([$gen, $newId]);
        echo json_encode(['id'=>$newId, 'sku'=>$gen], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['id'=>$newId, 'sku'=>$providedSku], JSON_UNESCAPED_UNICODE);
    exit;
}

if($method==='PUT'){
    if(!$id){ http_response_code(400); echo json_encode(['error'=>'Falta id'], JSON_UNESCAPED_UNICODE); exit; }

    $fields=[]; $params=[];
    if(isset($input['name'])){ $fields[]='name=?'; $params[]=$input['name']; }
    // Handle SKU: if provided and non-empty validate uniqueness; if provided empty, skip (we may autogenerate later)
    if(array_key_exists('sku',$input)){
        $sku_val = trim((string)$input['sku']);
        if($sku_val !== ''){
            $s = $pdo->prepare('SELECT id FROM items WHERE sku = ? AND id != ? LIMIT 1');
            $s->execute([$sku_val, $id]);
            if($s->fetch()){ http_response_code(422); echo json_encode(['error'=>'SKU ya existente'], JSON_UNESCAPED_UNICODE); exit; }
            $fields[] = 'sku=?'; $params[] = $sku_val;
        }
        // if empty string provided, do not set sku field now; we'll generate after update if necessary
    }
    if(array_key_exists('category_id',$input)){ $fields[]='category_id=?'; $params[]= $input['category_id']!==null ? (int)$input['category_id'] : null; }
    if(array_key_exists('brand_id',$input)){ $fields[]='brand_id=?'; $params[]= $input['brand_id']!==null ? (int)$input['brand_id'] : null; }
    if(array_key_exists('model_id',$input)){ $fields[]='model_id=?'; $params[]= $input['model_id']!==null ? (int)$input['model_id'] : null; }
    if(array_key_exists('barcode',$input)){ $fields[]='barcode=?'; $params[]=$input['barcode']; }
    if(array_key_exists('qr_code',$input)){ $fields[]='qr_code=?'; $params[]=$input['qr_code']; }
    if(array_key_exists('unit_price',$input)){ $fields[]='unit_price=?'; $params[]=(float)$input['unit_price']; }
    if(array_key_exists('active',$input)){ $fields[]='active=?'; $params[]=(int)$input['active']; }

    // If no fields to update, still check/generate SKU if missing
    if(empty($fields)){
        $stmt_fetch = $pdo->prepare('SELECT sku FROM items WHERE id = ?'); $stmt_fetch->execute([$id]); $r = $stmt_fetch->fetch(); $currentSku = $r['sku'] ?? null;
        if(!$currentSku){ $gen = 'IT-' . str_pad($id, 6, '0', STR_PAD_LEFT); $u=$pdo->prepare('UPDATE items SET sku = ? WHERE id = ?'); $u->execute([$gen, $id]); echo json_encode(['ok'=>true,'sku'=>$gen], JSON_UNESCAPED_UNICODE); exit; }
        echo json_encode(['ok'=>true,'sku'=>$currentSku], JSON_UNESCAPED_UNICODE); exit;
    }

    $params[]=$id; $sql='UPDATE items SET '.implode(', ',$fields).' WHERE id=?'; $stmt=$pdo->prepare($sql); $stmt->execute($params);

    // After update, ensure SKU exists; if still empty, generate one
    $stmt_fetch = $pdo->prepare('SELECT sku FROM items WHERE id = ?'); $stmt_fetch->execute([$id]); $r = $stmt_fetch->fetch(); $currentSku = $r['sku'] ?? null;
    if(!$currentSku){ $gen = 'IT-' . str_pad($id, 6, '0', STR_PAD_LEFT); $u=$pdo->prepare('UPDATE items SET sku = ? WHERE id = ?'); $u->execute([$gen, $id]); echo json_encode(['ok'=>true,'sku'=>$gen], JSON_UNESCAPED_UNICODE); exit; }

    echo json_encode(['ok'=>true,'sku'=>$currentSku], JSON_UNESCAPED_UNICODE); exit;
}

if($method==='DELETE'){
    if(!$id){ http_response_code(400); echo json_encode(['error'=>'Falta id'], JSON_UNESCAPED_UNICODE); exit; }
    $stmt=$pdo->prepare('UPDATE items SET active = 0 WHERE id = ?'); $stmt->execute([$id]); echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE); exit;
}

http_response_code(405); echo json_encode(['error'=>'Método no permitido'], JSON_UNESCAPED_UNICODE);
