<?php
require_once __DIR__ . '/../src/db.php';
$pdo = get_pdo();
$categories = [
    'Electrónica',
    'Accesorios',
    'Repuestos',
    'Herramientas',
    'Cables',
    'Baterías'
];
$inserted = 0;
foreach ($categories as $name) {
    $stmt = $pdo->prepare('INSERT IGNORE INTO categories (name, active) VALUES (?, 1)');
    if ($stmt->execute([$name])) $inserted += $stmt->rowCount();
}
echo "Seed complete. Inserted: $inserted\n";
