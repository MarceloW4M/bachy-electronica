<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'app' => 'bachy',
    'status' => 'ok',
    'message' => 'Sistema base levantado',
], JSON_UNESCAPED_UNICODE);
