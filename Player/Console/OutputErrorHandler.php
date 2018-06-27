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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Console\ConsoleEvents;

final class OutputErrorHandler
{
    public function install(Application $application)
    {
        $dispatcher = new EventDispatcher();

        $application->setDispatcher($dispatcher);

        $dispatcher->addListener(ConsoleEvents::ERROR, function (ConsoleErrorEvent $event) {
            if (($event->getInput()->hasOption('json') && $event->getInput()->getOption('json'))
                || ($event->getInput()->hasOption('full-report') && $event->getInput()->getOption('full-report'))
            ) {
                $event->getOutput()->writeln(JsonOutput::error($event->getError(), [
                    'errors' => [],
                ]));
            }
        });
    }
}
