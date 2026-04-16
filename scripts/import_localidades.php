<?php
// Import CSV de localidades y CPs a las tablas provinces y cities existentes
require_once __DIR__ . '/../src/db.php';

$pdo = get_pdo();
$file = __DIR__ . '/../data/localidades_cp_maestro_clean.csv';
if (!file_exists($file)) {
    echo "CSV no encontrado: $file\n";
    exit(1);
}

// load existing provinces into map name->id
$provStmt = $pdo->query('SELECT id, name FROM provinces');
$provs = [];
foreach ($provStmt->fetchAll() as $p) {
    $provs[mb_strtolower($p['name'])] = (int)$p['id'];
}

$insertProv = $pdo->prepare('INSERT INTO provinces (name) VALUES (?)');
$insertCity = $pdo->prepare('INSERT INTO cities (province_id, name, postal_code) VALUES (?, ?, ?)');
$checkCity = $pdo->prepare('SELECT id FROM cities WHERE province_id = ? AND name = ? LIMIT 1');

$fh = fopen($file, 'r');
if (!$fh) { echo "No se puede abrir CSV\n"; exit(1); }

$header = fgetcsv($fh);
$countProv = 0; $countCity = 0;
while (($row = fgetcsv($fh)) !== false) {
    if (count($row) < 5) continue;
    $prov = trim($row[0]);
    $loc = trim($row[2]);
    $cp = trim($row[3]);
    if ($prov === '' || $loc === '') continue;
    $provKey = mb_strtolower($prov);
    if (!isset($provs[$provKey])) {
        try {
            $insertProv->execute([$prov]);
            $pid = (int)$pdo->lastInsertId();
            $provs[$provKey] = $pid;
            $countProv++;
        } catch (PDOException $e) {
            // Duplicate entry possibly due to encoding differences: fetch existing id
            $stmt = $pdo->prepare('SELECT id FROM provinces WHERE name = ? LIMIT 1');
            $stmt->execute([$prov]);
            $rowp = $stmt->fetch();
            if ($rowp) {
                $pid = (int)$rowp['id'];
                $provs[$provKey] = $pid;
            } else {
                throw $e;
            }
        }
    } else {
        $pid = $provs[$provKey];
    }

    // avoid duplicate cities per province
    $checkCity->execute([$pid, $loc]);
    if ($checkCity->fetch()) continue;
    $insertCity->execute([$pid, $loc, $cp !== '' ? $cp : null]);
    $countCity++;
}
fclose($fh);

echo "Provincias nuevas agregadas: $countProv\n";
echo "Ciudades nuevas agregadas: $countCity\n";

?>
