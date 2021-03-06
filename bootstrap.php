<?php

namespace Lightning;

// Set required global parameters.
if (!defined('HOME_PATH')) {
    define('HOME_PATH', __DIR__ . '/..');
}

if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', HOME_PATH . '/Source/Config');
}

use lightningsdk\core\Tools\Logger;
use lightningsdk\core\Tools\Performance;

// Set the autoloader to the Lightning autoloader.
require_once __DIR__ . '/Tools/ClassLoader.php';
spl_autoload_register(['lightningsdk\\core\\Tools\\ClassLoader', 'classAutoloader']);
require_once HOME_PATH . '/vendor/autoload.php';

$configurationClass = $configurationClass ?? 'lightningsdk\core\Tools\Configuration';
$configurationClass::bootstrap($bootstrapConfig ?? []);

Performance::startTimer();
