<?php
require_once __DIR__ . '/../src/db.php';
$pdo = get_pdo();
$cols = [
  'address' => 'VARCHAR(255)',
  'cuit' => 'VARCHAR(50)',
  'contact_phone' => 'VARCHAR(50)',
  'province_id' => 'INT',
  'city_id' => 'INT',
  'postal_code' => 'VARCHAR(32)'
];
foreach($cols as $c => $type){
  $stmt = $pdo->prepare("SELECT COUNT(1) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = ?");
  $stmt->execute([$c]);
  $exists = (int)$stmt->fetchColumn();
  if($exists){ echo "$c exists\n"; continue; }
  try{
    $sql = "ALTER TABLE suppliers ADD COLUMN `$c` $type DEFAULT NULL";
    $pdo->exec($sql);
    echo "Added $c\n";
  }catch(Exception $e){ echo "Error adding $c: ". $e->getMessage() ."\n"; }
}
echo "done\n";
