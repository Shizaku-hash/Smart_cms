<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/Core/Helpers.php';

use App\Core\Application;

$router = require dirname(__DIR__) . '/config/routes.php';
$app = new Application($router);
$app->run();
