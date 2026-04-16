<?php
require_once __DIR__ . '/../src/db.php';
if ($argc < 2) { echo "Uso: php run_migration.php path/to/migration.sql\n"; exit(1); }
$path = $argv[1];
if (!file_exists($path)) { echo "File not found: $path\n"; exit(2); }
$sql = file_get_contents($path);
$pdo = get_pdo();
try{
    $pdo->beginTransaction();
    $pdo->exec($sql);
    $pdo->commit();
    echo "Migration applied: $path\n";
}catch(Exception $e){
    if($pdo->inTransaction()) $pdo->rollBack();
    echo "Error applying migration: " . $e->getMessage() . "\n";
    exit(3);
}
