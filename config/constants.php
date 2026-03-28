<?php
// Определяем базовые пути
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('HELPERS_PATH', ROOT_PATH . '/helpers');
define('MODELS_PATH', ROOT_PATH . '/models');
define('REPOSITORIES_PATH', ROOT_PATH . '/repositories');
define('SERVICES_PATH', ROOT_PATH . '/services');
define('CONTROLLERS_PATH', ROOT_PATH . '/controllers');
define('VIEWS_PATH', ROOT_PATH . '/views');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('ASSETS_PATH', ROOT_PATH . '/assets');

define('DEFAULT_TIMEZONE', 'Europe/Moscow');
date_default_timezone_set(DEFAULT_TIMEZONE);