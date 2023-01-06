<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player;

/**
 * @internal
 */
class Json
{
    public static function decode(string $json)
    {
        try {
            return json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('Error while decoding JSON: "%s".', $e->getMessage()), $e->getCode(), $e);
        }
    }

    public static function encode($value, $options = 0): string
    {
        try {
            return json_encode($value, \JSON_THROW_ON_ERROR | $options);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(sprintf('Unable to encode data into JSON: "%s".', $e->getMessage()), $e->getCode(), $e);
        }
    }
}
