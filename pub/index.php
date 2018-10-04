<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

#phpinfo(); exit;

require_once '../core/Buckyball/Core/App.php';
Buckyball\Core\App::factory()->run();