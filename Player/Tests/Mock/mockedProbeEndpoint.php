<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/mockState.php';

const HEADER_BLACKFIRE_QUERY = 'x-blackfire-query';
const HEADER_BLACKFIRE_RESPONSE = 'x-blackfire-response';

function buildBkfResponseHeader(): string
{
    return http_build_query([
        'continue' => 'false',
    ]);
}

function mockedProbeEndpoint(callable $responseFactory): void
{
    $headers = getAllHeaders();

    // a clever way to reset the mock state
    if (isset($headers['X-Probe-Mock-Reset'])) {
        clearMockState();
        http_response_code(202);

        return;
    }

    $mockState = readMockState(sys_get_temp_dir().'/'.MOCK_STATE_FILENAME);

    $endpoint = $_SERVER['REQUEST_URI'];

    if (!isset($mockState['endpoints'][$endpoint])) {
        $mockState['endpoints'][$endpoint] = [
            'called_times' => 0,
        ];
    }

    ++$mockState['endpoints'][$endpoint]['called_times'];

    if (isset($headers[HEADER_BLACKFIRE_QUERY])) {
        // parse the blackfire query header
        parse_str((string) $headers[HEADER_BLACKFIRE_QUERY], $blackfireQuery);

        // compute a blackfire response header
        $blackfireResponse = buildBkfResponseHeader();

        // append it to the response
        header(HEADER_BLACKFIRE_RESPONSE.': '.$blackfireResponse);
    }

    persistMockState($mockState);

    $responseFactory();
}
