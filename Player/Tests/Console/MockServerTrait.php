<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Console;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

trait MockServerTrait
{
    protected static null|Process $server = null;

    public static function getRunningServer(string $fixturesDir, string $port = null): Process
    {
        $port = $port ?? '8399';

        if (self::$server && !self::$server->isTerminated() && self::$server->isRunning()) {
            return self::$server;
        }

        if (self::$server) {
            self::$server->stop(0);
        }

        $finder = new PhpExecutableFinder();

        if (false === $binary = $finder->find()) {
            throw new \RuntimeException('Unable to find PHP binary to run server.');
        }

        self::$server = new Process([$binary, '-S', '0:'.$port, '-t', __DIR__.'/../'.$fixturesDir]);
        self::$server->start();

        usleep(250000);

        if (self::$server->isTerminated() && !self::$server->isSuccessful()) {
            throw new ProcessFailedException(self::$server);
        }

        return self::$server;
    }

    public static function stopServer(): void
    {
        if (self::$server) {
            self::$server->stop(0);
        }

        self::$server = null;
    }
}
