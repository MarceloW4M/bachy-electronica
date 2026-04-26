<?php
require_once __DIR__ . '/../src/db.php';

$pdo = get_pdo();
echo "Buscando provincias con nombres duplicados (insensible a mayúsculas/espacios)...\n";

$sql = "SELECT LOWER(TRIM(name)) as key_name, GROUP_CONCAT(id ORDER BY id) as ids, COUNT(1) as cnt
        FROM provinces
        GROUP BY key_name
        HAVING cnt > 1";

$stmt = $pdo->query($sql);
$dups = $stmt->fetchAll();
if (!$dups) {
    echo "No se encontraron provincias duplicadas.\n";
    exit(0);
}

foreach ($dups as $d) {
    $key = $d['key_name'];
    $ids = explode(',', $d['ids']);
    echo "\nNombre normalizado: " . $key . "\n";
    echo "IDs: " . implode(', ', $ids) . "\n";
    // show actual names per id
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $q = $pdo->prepare("SELECT id, name FROM provinces WHERE id IN ($placeholders) ORDER BY id");
    $q->execute($ids);
    $rows = $q->fetchAll();
    foreach ($rows as $r) {
        echo "  [{$r['id']}] {$r['name']}\n";
    }

    // Suggest dedupe SQL: keep lowest id
    $keep = (int)$ids[0];
    $others = array_slice($ids, 1);
    echo "\nSugerencia para desduplicar (mantener id={$keep}):\n";
    echo "-- reasignar cities -> province_id (si aplica)\n";
    echo "UPDATE cities SET province_id = {$keep} WHERE province_id IN (" . implode(',', $others) . ");\n";
    echo "-- reasignar customers -> province_id (si aplica)\n";
    echo "UPDATE customers SET province_id = {$keep} WHERE province_id IN (" . implode(',', $others) . ");\n";
    echo "-- eliminar provincias duplicadas\n";
    echo "DELETE FROM provinces WHERE id IN (" . implode(',', $others) . ");\n";
}

echo "\nTerminado. Revise y ejecute las sentencias sugeridas con cuidado. Haga backup antes de aplicarlas.\n";

?>
