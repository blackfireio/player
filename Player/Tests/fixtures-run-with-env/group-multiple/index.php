<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once '../../Mock/mockedProbeEndpoint.php';

mockedProbeEndpoint(function () {
    $counter = (int) ($_COOKIE['counter'] ?? 0);
    ++$counter;
    header('Set-Cookie: counter='.$counter);

    echo sprintf(
        '%s %d',
        $_GET['q'] ?? '-',
        $counter
    );
});
