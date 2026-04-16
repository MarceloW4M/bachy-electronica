<?php
require_once __DIR__ . '/../../src/db.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if($method==='GET'){
  if($id){
    $stmt=$pdo->prepare('SELECT * FROM invoices WHERE id=?'); $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ http_response_code(404); echo json_encode(['error'=>'Factura no encontrada']); exit; }
    $stmt=$pdo->prepare('SELECT ii.*, i.name as item_name FROM invoice_items ii LEFT JOIN items i ON i.id=ii.item_id WHERE ii.invoice_id=?'); $stmt->execute([$id]); $r['items']=$stmt->fetchAll(); echo json_encode($r, JSON_UNESCAPED_UNICODE); exit;
  }
  echo json_encode($pdo->query('SELECT * FROM invoices ORDER BY created_at DESC')->fetchAll(), JSON_UNESCAPED_UNICODE); exit;
}

if($method==='POST'){
  if(empty($input['items'])||!is_array($input['items'])){ http_response_code(422); echo json_encode(['error'=>'Items obligatorios']); exit; }
  try{
    $pdo->beginTransaction();
    $total=0; foreach($input['items'] as $it){ $q=(int)($it['quantity']??1); $p=(float)($it['unit_price']??0); $total += $q*$p; }
    $stmt=$pdo->prepare('INSERT INTO invoices (customer_id, total) VALUES (?, ?)'); $stmt->execute([$input['customer_id'] ?? null, $total]); $id=(int)$pdo->lastInsertId();
    $stmtItem=$pdo->prepare('INSERT INTO invoice_items (invoice_id, item_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
    foreach($input['items'] as $it){ $stmtItem->execute([$id, (int)$it['item_id'], (int)$it['quantity'], (float)$it['unit_price']]); }
    $pdo->commit(); echo json_encode(['id'=>$id], JSON_UNESCAPED_UNICODE);
  }catch(Exception $e){ $pdo->rollBack(); http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
  exit;
}

http_response_code(405); echo json_encode(['error'=>'Método no permitido']);
