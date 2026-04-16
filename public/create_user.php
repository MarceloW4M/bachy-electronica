<?php

require_once __DIR__ . '/../src/db.php';

if ($argc < 3) {
    fwrite(STDERR, "Uso: php public/create_user.php usuario clave\n");
    exit(1);
}

[$script, $username, $password] = $argv;
$pdo = get_pdo();
$stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
$stmt->execute([$username, password_hash($password, PASSWORD_BCRYPT), 'admin']);

echo "Usuario creado: {$username}\n";
