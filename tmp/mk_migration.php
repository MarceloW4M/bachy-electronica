<?php
$in = __DIR__ . '/cities_dups_output.txt';
$out = __DIR__ . '/../db/migrations/20260426_dedupe_cities.sql';
$lines = file($in);
$f = fopen($out, 'w');
fwrite($f, "-- Migration: dedupe cities by reassigning customers then deleting duplicate city rows\nSTART TRANSACTION;\n");
foreach ($lines as $l) {
    if (preg_match('/^(UPDATE|DELETE)/', $l)) {
        fwrite($f, $l);
    }
}
fwrite($f, "COMMIT;\n");
fclose($f);
echo "WROTE $out\n";
?>
