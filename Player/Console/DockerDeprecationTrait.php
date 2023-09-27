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

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
trait DockerDeprecationTrait
{
    private function ensureCommandIsRunInDockerContainer(OutputInterface $output): void
    {
        if (!isset($_ENV['USING_PLAYER_DOCKER_RELEASE'])) {
            $output->writeln('<error>You should use the Blackfire Player using the Docker release.</error>');
            $output->writeln('<error>Blackfire Player v3 will not provide support other than the Docker release, see https://blackfire.io/docs/builds-cookbooks/player#usage.</error>');
            $output->writeln('');
        }
    }
}
