<?php
require_once __DIR__ . '/../../src/db.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if($method==='GET'){
  if($id){
    $stmt=$pdo->prepare('SELECT * FROM cash_closures WHERE id=?'); $stmt->execute([$id]); $r=$stmt->fetch(); if(!$r){ http_response_code(404); echo json_encode(['error'=>'Cierre no encontrado']); exit; }
    echo json_encode($r, JSON_UNESCAPED_UNICODE); exit;
  }
  echo json_encode($pdo->query('SELECT * FROM cash_closures ORDER BY closure_date DESC')->fetchAll(), JSON_UNESCAPED_UNICODE); exit;
}

if($method==='POST'){
  if(empty($input['closure_date'])){ http_response_code(422); echo json_encode(['error'=>'closure_date obligatorio']); exit; }
  $stmt=$pdo->prepare('INSERT INTO cash_closures (closure_date, opening_amount, closing_amount, notes) VALUES (?, ?, ?, ?)');
  $stmt->execute([$input['closure_date'], (float)($input['opening_amount']??0), (float)($input['closing_amount']??0), $input['notes']??null]);
  echo json_encode(['id'=>(int)$pdo->lastInsertId()], JSON_UNESCAPED_UNICODE); exit;
}

http_response_code(405); echo json_encode(['error'=>'Método no permitido']);
