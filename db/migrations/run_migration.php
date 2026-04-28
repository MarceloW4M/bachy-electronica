<?php
require_once __DIR__ . '/../../src/db.php';

$file = __DIR__ . '/20260427_normalize_repair_statuses.sql';
if ($argc > 1 && is_string($argv[1])) {
    $file = $argv[1];
}

if (!file_exists($file)) {
    fwrite(STDERR, "SQL file not found: $file\n");
    exit(2);
}

$sql = file_get_contents($file);
if ($sql === false) {
    fwrite(STDERR, "Could not read SQL file: $file\n");
    exit(3);
}

$pdo = null;
try{
    $pdo = get_pdo();
}catch(Throwable $e){
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(4);
}

try{
    $pdo->beginTransaction();
    $pdo->exec($sql);
    $pdo->commit();
    fwrite(STDOUT, "Migration executed successfully: $file\n");
    exit(0);
}catch(Throwable $e){
    if($pdo && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
