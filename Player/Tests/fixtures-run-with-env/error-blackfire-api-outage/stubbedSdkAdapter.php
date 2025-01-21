<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Blackfire\Player\Exception\ApiCallException;
use Blackfire\Player\Tests\Adapter\StubbedSdkAdapter;

return new StubbedSdkAdapter('Blackfire Test', function (string $uuid): void {
    throw new ApiCallException('404: Error while fetching profile from the API.', 404);
});
