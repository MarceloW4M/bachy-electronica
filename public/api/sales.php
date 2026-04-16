<?php
require_once __DIR__ . '/../../src/db.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if($method === 'GET'){
    if($id){
        $stmt = $pdo->prepare('SELECT * FROM sales WHERE id = ?'); $stmt->execute([$id]); $s=$stmt->fetch(); if(!$s){ http_response_code(404); echo json_encode(['error'=>'Venta no encontrada'], JSON_UNESCAPED_UNICODE); exit; }
        $stmt = $pdo->prepare('SELECT si.*, i.name as item_name FROM sale_items si LEFT JOIN items i ON i.id = si.item_id WHERE si.sale_id = ?'); $stmt->execute([$id]); $items=$stmt->fetchAll(); $s['items']=$items; echo json_encode($s, JSON_UNESCAPED_UNICODE); exit;
    }
    echo json_encode($pdo->query('SELECT s.*, c.name as customer_name FROM sales s LEFT JOIN customers c ON c.id = s.customer_id ORDER BY s.created_at DESC')->fetchAll(), JSON_UNESCAPED_UNICODE); exit;
}

if($method === 'POST'){
    if(empty($input['items']) || !is_array($input['items'])){ http_response_code(422); echo json_encode(['error'=>'Items obligatorios'], JSON_UNESCAPED_UNICODE); exit; }
    try{
        $pdo->beginTransaction();
        $total = 0;
        foreach($input['items'] as $it){
            $q = isset($it['quantity']) ? (int)$it['quantity'] : 1;
            $p = isset($it['unit_price']) ? (float)$it['unit_price'] : 0;
            $total += $q * $p;
        }
        $stmt = $pdo->prepare('INSERT INTO sales (customer_id, total) VALUES (?, ?)');
        $stmt->execute([ $input['customer_id'] ?? null, $total ]);
        $saleId = (int)$pdo->lastInsertId();
        $stmtItem = $pdo->prepare('INSERT INTO sale_items (sale_id, item_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
        foreach($input['items'] as $it){
            $stmtItem->execute([$saleId, (int)$it['item_id'], (int)$it['quantity'], (float)$it['unit_price']]);
            // adjust stock if warehouse provided
            if(isset($input['warehouse_id'])){
                $wid = (int)$input['warehouse_id'];
                $itemId = (int)$it['item_id'];
                $qty = (int)$it['quantity'];
                $s = $pdo->prepare('SELECT id, quantity FROM stocks WHERE item_id = ? AND warehouse_id = ?'); $s->execute([$itemId, $wid]); $row = $s->fetch();
                $prevQty = $row ? (int)$row['quantity'] : 0;
                $newQty = $prevQty - $qty;
                if($row){ $u = $pdo->prepare('UPDATE stocks SET quantity = ? WHERE id = ?'); $u->execute([$newQty, $row['id']]); }
                else { $i = $pdo->prepare('INSERT INTO stocks (item_id, warehouse_id, quantity) VALUES (?, ?, ?)'); $i->execute([$itemId, $wid, $newQty]); }
                // record movement if table exists
                try{ $mstmt = $pdo->prepare('INSERT INTO stock_movements (item_id, warehouse_id, quantity_before, quantity_after, delta, note) VALUES (?, ?, ?, ?, ?, ?)'); $mstmt->execute([$itemId, $wid, $prevQty, $newQty, $newQty - $prevQty, 'Venta #'.$saleId]); }catch(Throwable $e){ }
            }
        }
        $pdo->commit();
        echo json_encode(['id'=>$saleId], JSON_UNESCAPED_UNICODE);
    }catch(Exception $e){ $pdo->rollBack(); http_response_code(500); echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE); }
    exit;
}

http_response_code(405); echo json_encode(['error'=>'Método no permitido'], JSON_UNESCAPED_UNICODE);
