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

class Player
{
    public static function version(): string
    {
        static $v;

        if ($v) {
            return $v;
        }

        if (!empty($_ENV['BLACKFIRE_PLAYER_VERSION'])) {
            return $_ENV['BLACKFIRE_PLAYER_VERSION'];
        }

        // This is variable is used to replace the version
        // by box, see https://github.com/box-project/box/blob/master/doc/configuration.md#replaceable-placeholders
        $version = '@git-version@';
        // let's not write the same string, otherwise it would be replaced !
        if (file_exists(__DIR__.'/../composer.json')) {
            $composer = Json::decode(file_get_contents(__DIR__.'/../composer.json'));
            $version = $composer['extra']['branch-alias']['dev-master'];
        } else {
            $version = 'dev';
        }

        $v = $version;

        return $version;
    }
}
