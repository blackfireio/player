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

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class CommandInitializer
{
    public function __invoke(InputInterface $input, OutputInterface $output): void
    {
        $formatter = $output->getFormatter();

        $formatter->setStyle('title', new OutputFormatterStyle('black', 'yellow'));
        $formatter->setStyle('debug', new OutputFormatterStyle('red', 'black'));
        $formatter->setStyle('failure', new OutputFormatterStyle('white', 'red'));
        $formatter->setStyle('warning', new OutputFormatterStyle('white', 'yellow', ['bold']));
        $formatter->setStyle('success', new OutputFormatterStyle('white', 'green'));
        $formatter->setStyle('detail', new OutputFormatterStyle('white', 'blue'));
    }
}
