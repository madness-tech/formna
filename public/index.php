<?php

// Start output buffering to prevent any whitespace from breaking JSON responses
ob_start();

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/../src/core/db.php';
require __DIR__ . '/../src/core/bootstrap.php';
require __DIR__ . '/../src/core/auth.php';
require __DIR__ . '/../src/core/helpers.php';
require __DIR__ . '/../src/core/middleware.php';
require __DIR__ . '/../src/modules/audit/logger.php';

$router = require __DIR__ . '/../src/config/routes.php';

$router->run();
