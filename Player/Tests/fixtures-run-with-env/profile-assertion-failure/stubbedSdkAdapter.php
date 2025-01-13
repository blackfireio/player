<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Blackfire\Player\Tests\Adapter\StubbedSdkAdapter;
use Blackfire\Profile;

return new StubbedSdkAdapter('Blackfire Test', function (string $uuid) {
    return new Profile(static fn () => [
        'report' => [
            'state' => 'failure',
            'tests' => [
                [
                    'name' => 'Pages should be light',
                    'state' => 'failing',
                    'failures' => [
                        'metrics.output.network_out < 220KB',
                    ],
                ],
            ],
        ],
        '_links' => [
            'graph_url' => [
                'href' => sprintf('https://app.blackfire.io/profiles/%s/graph', $uuid),
            ],
        ],
    ], $uuid);
}
);
