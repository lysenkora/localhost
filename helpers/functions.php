<?php
function dd(...$vars) {
    foreach ($vars as $var) {
        var_dump($var);
    }
    die();
}

function view($path, $data = []) {
    extract($data);
    $file = VIEWS_PATH . '/' . str_replace('.', '/', $path) . '.php';
    
    if (file_exists($file)) {
        require $file;
    } else {
        throw new Exception("View not found: {$file}");
    }
}

function asset($path) {
    return '/assets/' . ltrim($path, '/');
}

function url($path = '') {
    return '/' . ltrim($path, '/');
}

function redirect($url) {
    header("Location: " . url($url));
    exit;
}

function old($key, $default = '') {
    return $_SESSION['old'][$key] ?? $default;
}

function flash($key, $value = null) {
    if ($value === null) {
        $value = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $value;
    }
    $_SESSION['flash'][$key] = $value;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
}