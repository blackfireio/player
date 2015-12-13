<?php

if (file_exists($autoloader = __DIR__.'/../../../autoload.php')) {
    require_once $autoloader;
} elseif (file_exists($autoloader = __DIR__.'/../vendor/autoload.php')) {
    require_once $autoloader;
} else {
    throw new \RuntimeException('Unable to find the Composer autoloader.');
}

use Blackfire\Player\Console\Application;

$application = new Application();
$application->run();
