<?php

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'it_ops');
define('DB_USER', 'root');
define('DB_PASS', '');
