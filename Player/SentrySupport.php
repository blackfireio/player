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
use Sentry\EventId;
use Sentry\Severity;

/**
 * @internal
 */
class SentrySupport
{
    public static function init(string $transactionId): void
    {
        if (!$dsn = getenv('BLACKFIRE_PLAYER_SENTRY_DSN')) {
            return;
        }

        $version = Player::version();

        \Sentry\init([
            'dsn' => $dsn,
            'max_breadcrumbs' => 50,
            'release' => $version,
            'error_types' => \E_ALL & ~\E_DEPRECATED & ~\E_USER_DEPRECATED,
            'send_default_pii' => true,
            'max_value_length' => 4096,
        ]);

        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($transactionId) {
            $scope->setTag('transaction_id', $transactionId);
        });

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

    public static function captureException(\Throwable $throwable, array $eventHint = [])
    {
        \Sentry\captureException($throwable, EventHint::fromArray($eventHint));
    }

    public static function captureMessage(string $message, Severity|null $level = null, EventHint|null $hint = null): EventId|null
    {
        return \Sentry\captureMessage($message, $level, $hint);
    }
}
