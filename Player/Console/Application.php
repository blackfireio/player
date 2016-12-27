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

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Application extends BaseApplication
{
    public function __construct()
    {
        error_reporting(-1);

        $version = '@git-version@';
        if ('@'.'git-version@' == $version) {
            $composer = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);
            $version = $composer['extra']['branch-alias']['dev-master'];
        }

        parent::__construct('Blackfire Player', $version);

        $this->add(new PlayerCommand());
    }

    public function renderException(\Exception $e, OutputInterface $output)
    {
        $output->writeln('');

        $errorMessages = array(
            '',
            ' Something went wrong!',
            ' Read the Player documentation at: https://blackfire.io/player ',
            '',
        );
        $formattedBlock = $this->getHelperSet()->get('formatter')->formatBlock($errorMessages, 'error');

        $output->writeln($formattedBlock);

        parent::renderException($e, $output);
    }
}
