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

use Sentry\Breadcrumb;
use Sentry\EventHint;

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

        if ($envVar = getenv('BLACKFIRE_BUILD_UUID')) {
            self::addBreadcrumb(sprintf('Starting build uuid "%s"', $envVar));
        }
        if ($envVar = getenv('BLACKFIRE_ENDPOINT')) {
            self::addBreadcrumb(sprintf('Running on endpoint %s', $envVar));
        }
        if ($envVar = getenv('BLACKFIRE_CLIENT_ID')) {
            self::addBreadcrumb(sprintf('Using client id %s', $envVar));
        }
        if ($envVar = getenv('BLACKFIRE_PLAYER_SCENARIO')) {
            self::addBreadcrumb('Running scenario', ['scenario' => $envVar]);
        }
    }

    public static function addBreadcrumb(string $message, array $metadata = []): void
    {
        \Sentry\addBreadcrumb(Breadcrumb::fromArray([
            'level' => Breadcrumb::LEVEL_INFO,
            'category' => 'blackfire',
            'message' => $message,
            'data' => $metadata,
        ]));
    }

    public static function captureMessage(string $message, array $hint = []): void
    {
        $hint = EventHint::fromArray($hint);
        \Sentry\captureMessage($message, null, $hint);
    }
}
