<?php
// Простейший тест - копия того, что должно быть в index.php
if (isset($_GET['page']) && $_GET['page'] == 'get_operations') {
    header('Content-Type: application/json');
    echo json_encode(['test' => 'simple', 'works' => true]);
    exit;
}
echo "This is HTML";