<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Please authenticate"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'PLEASE LOGIN';
    exit;
}

if ('admin' !== $_SERVER['PHP_AUTH_USER'] || 'admin' !== $_SERVER['PHP_AUTH_PW']) {
    header('HTTP/1.0 403 Forbidden');
    echo 'UNAUTHORIZED ACCESS';
    exit;
}

echo 'Hey';
