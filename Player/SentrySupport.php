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

class SentrySupport
{
    public static function init()
    {
        if (!$dsn = getenv('BLACKFIRE_PLAYER_SENTRY_DSN')) {
            return;
        }

        // This is variable is used to replace the version
        // by box, see https://github.com/box-project/box/blob/master/doc/configuration.md#replaceable-placeholders
        $version = '@git-version@';
        $testPart1 = '@';

        // let's not write the same string, otherwise it would be replaced !
        if ($testPart1.'git-version@' === $version) {
            $composer = Json::decode(file_get_contents(__DIR__.'/../composer.json'));
            $version = $composer['extra']['branch-alias']['dev-master'];
        }

        \Sentry\init([
            'dsn' => $dsn,
            'max_breadcrumbs' => 50,
            'release' => $version,
            'error_types' => \E_ALL & ~\E_DEPRECATED & ~\E_USER_DEPRECATED,
            'send_default_pii' => true,
            'max_value_length' => 4096,
        ]);
    }
}
