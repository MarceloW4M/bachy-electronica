<?php
require_once __DIR__ . '/../../src/db.php';

// Handles upload (POST) and delete (DELETE) of device photos
header('Content-Type: application/json; charset=utf-8');
$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    // expect multipart/form-data with device_id and files[]
    $device_id = isset($_POST['device_id']) ? (int)$_POST['device_id'] : null;
    if (!$device_id) { http_response_code(400); echo json_encode(['error'=>'Missing device_id'], JSON_UNESCAPED_UNICODE); exit; }

    // count existing
    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM device_photos WHERE device_id = ?');
    $stmt->execute([$device_id]);
    $cnt = (int)$stmt->fetchColumn();

    if (empty($_FILES['files'])) { http_response_code(400); echo json_encode(['error'=>'No files'], JSON_UNESCAPED_UNICODE); exit; }

    $files = $_FILES['files'];
    $uploaded = [];
    $uploadDir = __DIR__ . '/../uploads/devices/' . $device_id;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    for ($i=0;$i<count($files['name']);$i++){
        if ($cnt >= 4) break;
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $name = basename($files['name'][$i]);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $safe = uniqid() . '.' . $ext;
        $target = $uploadDir . '/' . $safe;
        if (move_uploaded_file($files['tmp_name'][$i], $target)){
            $stmt = $pdo->prepare('INSERT INTO device_photos (device_id, filename) VALUES (?,?)');
            $stmt->execute([$device_id, 'uploads/devices/'.$device_id.'/'.$safe]);
            $uploaded[] = $pdo->lastInsertId();
            $cnt++;
        }
    }

    echo json_encode(['uploaded' => $uploaded], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'DELETE') {
    // expect ?id=photo_id
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if (!$id) { http_response_code(400); echo json_encode(['error'=>'Missing id'], JSON_UNESCAPED_UNICODE); exit; }
    $stmt = $pdo->prepare('SELECT filename FROM device_photos WHERE id = ?');
    $stmt->execute([$id]);
    $f = $stmt->fetchColumn();
    if ($f) {
        $path = __DIR__ . '/../' . $f;
        if (file_exists($path)) @unlink($path);
    }
    $stmt = $pdo->prepare('DELETE FROM device_photos WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['error'=>'Method not allowed'], JSON_UNESCAPED_UNICODE);
