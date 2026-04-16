<?php

require_once __DIR__ . '/../src/db.php';

$pdo = get_pdo();
$sql = file_get_contents(__DIR__ . '/../db/migrations.sql');
$pdo->exec($sql);

echo "Migraciones ejecutadas correctamente\n";
