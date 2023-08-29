<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

const MOCK_STATE_FILENAME = 'probe_mock_state.json';

function readMockState(string $mockStateLocation): array
{
    $mockState = [
        'location' => $mockStateLocation,
        'endpoints' => [],
    ];

    if (file_exists($mockStateLocation)) {
        $mockState = json_decode(file_get_contents($mockStateLocation), true);
    }

    return $mockState;
}

function clearMockState(): void
{
    @unlink(sys_get_temp_dir().'/'.MOCK_STATE_FILENAME);
}

function persistMockState(array $mockState): void
{
    file_put_contents($mockState['location'], json_encode($mockState, \JSON_PRETTY_PRINT));
}
