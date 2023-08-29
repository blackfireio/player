<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Console;

use Blackfire\Player\Json;

/**
 * @internal
 */
final class JsonOutput
{
    public static function encode(mixed $data): string
    {
        return Json::encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
    }

    public static function error(\Throwable $error, array $data = []): string
    {
        return self::encode(array_replace([
            'message' => $error->getMessage(),
            'success' => false,
        ], $data));
    }
}
