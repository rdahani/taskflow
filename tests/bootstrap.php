<?php
declare(strict_types=1);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
