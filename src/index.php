<?php

namespace Charcoal\Conductor;

use Charcoal\Conductor\Model\Conductor;

$autoload_paths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php'
];
foreach ($autoload_paths as $autoload_path) {
    if (file_exists($autoload_path)) {
        require_once($autoload_path);
        break;
    }
}

new Conductor();
