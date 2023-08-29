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

use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
final class OutputErrorHandler
{
    public function install(SymfonyApplication $application): void
    {
        $dispatcher = new EventDispatcher();

        $application->setDispatcher($dispatcher);

        $dispatcher->addListener(ConsoleEvents::ERROR, function (ConsoleErrorEvent $event) {
            if ($event->getInput()->hasOption('json') && $event->getInput()->getOption('json')) {
                $extra = ['errors' => []];

                if ($event->getInput()->hasArgument('file')) {
                    $file = $event->getInput()->getArgument('file');

                    if (\is_resource($file)) {
                        fseek($file, 0);

                        $extra['input'] = [
                            'path' => 'php://stdin',
                            'content' => @stream_get_contents($file),
                        ];
                    } else {
                        $extra['input'] = [
                            'path' => $file,
                            'content' => @file_get_contents($file),
                        ];
                    }
                }

                $event->getOutput()->writeln(JsonOutput::error($event->getError(), $extra));
            } else {
                $output = $event->getOutput();
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln(sprintf('<error>%s</error>', $event->getError()->getMessage()));
                    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
                        $output->writeln(sprintf('<error>%s</error>', $event->getError()->getTraceAsString()));
                    }
                }
            }
        });
    }
}
