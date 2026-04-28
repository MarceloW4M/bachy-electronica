<?php
require_once __DIR__ . '/../src/db.php';

$pdo = get_pdo();
echo "Buscando ciudades duplicadas por provincia (insensible a mayúsculas/espacios)...\n";

$sql = "SELECT province_id, LOWER(TRIM(name)) as key_name, GROUP_CONCAT(id ORDER BY id) as ids, COUNT(1) as cnt
        FROM cities
        GROUP BY province_id, key_name
        HAVING cnt > 1";

$stmt = $pdo->query($sql);
$dups = $stmt->fetchAll();
if (!$dups) {
    echo "No se encontraron ciudades duplicadas.\n";
    exit(0);
}

foreach ($dups as $d) {
    $prov = (int)$d['province_id'];
    $key = $d['key_name'];
    $ids = explode(',', $d['ids']);
    echo "\nProvincia ID: {$prov} - Nombre normalizado: {$key}\n";
    echo "IDs: " . implode(', ', $ids) . "\n";
    // show actual names per id
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $q = $pdo->prepare("SELECT id, name FROM cities WHERE id IN ($placeholders) ORDER BY id");
    $q->execute($ids);
    $rows = $q->fetchAll();
    foreach ($rows as $r) {
        echo "  [{$r['id']}] {$r['name']}\n";
    }

    // Suggest dedupe SQL: keep lowest id
    $keep = (int)$ids[0];
    $others = array_slice($ids, 1);
    echo "\nSugerencia para desduplicar (mantener id={$keep}):\n";
    echo "-- reasignar customers -> city_id (si aplica)\n";
    echo "UPDATE customers SET city_id = {$keep} WHERE city_id IN (" . implode(',', $others) . ");\n";
    echo "-- reasignar otras referencias si existen (ajustar tablas/columnas)\n";
    echo "-- e.g. UPDATE some_table SET city_id = {$keep} WHERE city_id IN (" . implode(',', $others) . ");\n";
    echo "-- eliminar ciudades duplicadas\n";
    echo "DELETE FROM cities WHERE id IN (" . implode(',', $others) . ");\n";
}

echo "\nTerminado. Revise y ejecute las sentencias sugeridas con cuidado. Haga backup antes de aplicarlas.\n";

?>
