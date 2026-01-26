<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../Mock/mockedProbeEndpoint.php';

mockedProbeEndpoint(static function (): void {
    $i = $_GET['i'] ?? 0;

    if ($i >= 4) {
        echo 'OK';

        return;
    }

    header('HTTP/1.0 302 Found');
    header(sprintf('Location: %s?i=%d', $_SERVER['SCRIPT_NAME'], ++$i));

    echo 'Redirect';
});
