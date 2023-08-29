#!/usr/bin/env php
<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

set_time_limit(0);

require_once __DIR__.'/../vendor/autoload.php';

use Blackfire\Player\Console\Application;
use Blackfire\Player\Console\OutputErrorHandler;
use Blackfire\Player\SentrySupport;

$transactionId = \uuid_create(UUID_TYPE_RANDOM);
SentrySupport::init($transactionId);

$application = new Application(null, null, $transactionId);
$outputErrorHandler = new OutputErrorHandler();
$outputErrorHandler->install($application);

$application->run();
