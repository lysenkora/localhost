<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'message' => 'Debug API works',
    'timestamp' => time(),
    'get_params' => $_GET
]);