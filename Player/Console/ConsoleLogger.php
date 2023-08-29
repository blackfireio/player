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

use Psr\Log\LogLevel;
use Symfony\Component\Console\Logger\ConsoleLogger as BaseConsoleLogger;

/**
 * @internal
 */
final class ConsoleLogger extends BaseConsoleLogger
{
    private const ERROR_LEVELS = [
        LogLevel::EMERGENCY => 1,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 1,
        LogLevel::ERROR => 1,
    ];

    private bool $errored = false;

    public function log(mixed $level, mixed $message, array $context = []): void
    {
        parent::log($level, $message, $context);

        if (isset(self::ERROR_LEVELS[$level])) {
            $this->errored = true;
        }
    }

    public function hasErrored(): bool
    {
        return $this->errored;
    }
}
