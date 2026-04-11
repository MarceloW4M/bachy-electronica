<?php

require_once __DIR__ . '/../../src/db.php';
header('Content-Type: application/json; charset=utf-8');
$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if ($method === 'POST') {
    // Expected payload: { repair_id, labour_amount, items: [{item_id, item_name, qty, unit_price}] }
    if (empty($input['repair_id'])) { http_response_code(422); echo json_encode(['error' => 'repair_id requerido'], JSON_UNESCAPED_UNICODE); exit; }
    $repair_id = (int)$input['repair_id'];
    $labour = isset($input['labour_amount']) ? (float)$input['labour_amount'] : 0.0;
    $items = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];

    try{
        $pdo->beginTransaction();
        // compute totals
        $subtotal = 0.0;
        foreach($items as $it){
            $qty = isset($it['qty']) ? (int)$it['qty'] : 1;
            $price = isset($it['unit_price']) ? (float)$it['unit_price'] : 0.0;
            $subtotal += ($qty * $price);
        }
        $total = $subtotal + $labour;

        // include labour_notes if provided
        $labour_notes = isset($input['labour_notes']) ? $input['labour_notes'] : null;
        // try insert with labour_notes column if exists
        try{
            $stmt = $pdo->prepare('INSERT INTO repair_reports (repair_id, labour_amount, labour_notes, total_amount) VALUES (?, ?, ?, ?)');
            $stmt->execute([$repair_id, $labour, $labour_notes, $total]);
        }catch(Throwable $e){
            // fallback if column doesn't exist
            $stmt = $pdo->prepare('INSERT INTO repair_reports (repair_id, labour_amount, total_amount) VALUES (?, ?, ?)');
            $stmt->execute([$repair_id, $labour, $total]);
        }
        $report_id = (int)$pdo->lastInsertId();

        if(!empty($items)){
            $ins = $pdo->prepare('INSERT INTO repair_report_lines (report_id, item_id, item_name, qty, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)');

            // determine default warehouse to consume parts from (first active)
            $whStmt = $pdo->query("SELECT id FROM warehouses WHERE active = 1 ORDER BY id LIMIT 1");
            $whRow = $whStmt->fetch();
            $defaultWarehouse = $whRow ? (int)$whRow['id'] : null;

            foreach($items as $it){
                $item_id = isset($it['item_id']) && $it['item_id']!==null ? (int)$it['item_id'] : null;
                $item_name = isset($it['item_name']) ? $it['item_name'] : null;
                $qty = isset($it['qty']) ? (int)$it['qty'] : 1;
                $unit_price = isset($it['unit_price']) ? (float)$it['unit_price'] : 0.0;
                $line_sub = $qty * $unit_price;
                $ins->execute([$report_id, $item_id, $item_name, $qty, $unit_price, $line_sub]);

                // If item_id available and we have a default warehouse, decrement stock and record movement
                if($item_id && $defaultWarehouse){
                    try{
                        // find existing stock row
                        $s = $pdo->prepare('SELECT id, quantity FROM stocks WHERE item_id = ? AND warehouse_id = ?');
                        $s->execute([$item_id, $defaultWarehouse]);
                        $stock = $s->fetch();
                        if($stock){
                            $prevQty = (int)$stock['quantity'];
                            $newQty = $prevQty - $qty;
                            $u = $pdo->prepare('UPDATE stocks SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                            $u->execute([$newQty, $stock['id']]);
                        } else {
                            // create stock row with negative or zero quantity
                            $prevQty = 0;
                            $newQty = 0 - $qty;
                            $i = $pdo->prepare('INSERT INTO stocks (item_id, warehouse_id, quantity) VALUES (?, ?, ?)');
                            $i->execute([$item_id, $defaultWarehouse, $newQty]);
                        }
                        // record movement
                        $note = 'Uso por informe #' . $report_id;
                        $m = $pdo->prepare('INSERT INTO stock_movements (item_id, warehouse_id, quantity_before, quantity_after, delta, note) VALUES (?, ?, ?, ?, ?, ?)');
                        $m->execute([$item_id, $defaultWarehouse, $prevQty, $newQty, $newQty - $prevQty, $note]);
                    }catch(Throwable $e){
                        // ignore stock errors to avoid blocking report saving
                    }
                }
            }
        }

        // Update the parent repair with calculated totals and mark as completed (Completada)
        try{
            $upd = $pdo->prepare('UPDATE repairs SET parts_total = ?, labour_price = ?, total_amount = ?, status = ? WHERE id = ?');
            $upd->execute([round($subtotal,2), round($labour,2), round($total,2), 'Completada', $repair_id]);
        }catch(Throwable $e){
            // If update fails (e.g., columns not present), ignore so report saving is not blocked.
        }

        $pdo->commit();
        echo json_encode(['ok' => true, 'report_id' => $report_id, 'total' => $total], JSON_UNESCAPED_UNICODE);
        exit;
    }catch(Exception $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo guardar informe', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// For convenience, support GET to fetch reports for a repair: ?repair_id=NN
if ($method === 'GET') {
    $repair_id = isset($_GET['repair_id']) ? (int)$_GET['repair_id'] : null;
    if(!$repair_id){ http_response_code(400); echo json_encode(['error'=>'repair_id requerido'], JSON_UNESCAPED_UNICODE); exit; }
    $stmt = $pdo->prepare('SELECT * FROM repair_reports WHERE repair_id = ? ORDER BY created_at DESC');
    $stmt->execute([$repair_id]);
    $reports = $stmt->fetchAll();
    foreach($reports as &$r){
        $r['lines'] = $pdo->prepare('SELECT * FROM repair_report_lines WHERE report_id = ?')->execute([$r['id']]) ? [] : [];
    }
    echo json_encode($reports, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['error'=>'Método no permitido'], JSON_UNESCAPED_UNICODE);
