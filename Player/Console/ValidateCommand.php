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

use Blackfire\Player\Validator\BkfValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ValidateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('validate')
            ->setDefinition([
                new InputArgument('file', InputArgument::REQUIRED, 'The file defining the scenarios'),
                new InputOption('json', '', InputOption::VALUE_NONE, 'Outputs variable values as JSON', null),
            ])
            ->setDescription('Validate scenario files')
            ->setHelp('Read https://blackfire.io/docs/player to learn about all supported options.')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        (new CommandInitializer())($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $result = (new BkfValidator())->validateFile($input->getArgument('file'));

        if ($input->getOption('json')) {
            $output->writeln(JsonOutput::encode([
                'message' => $result->isSuccess() ? 'The scenarios are valid.' : 'The scenarios are not valid.',
                'success' => $result->isSuccess(),
                'errors' => $result->getErrors(),
                'code' => 0,
            ]));
        } elseif ($result->isSuccess()) {
            $output->writeln('<info>The scenarios are valid.</>');
        } else {
            $output->writeln('<info>The scenarios are not valid:</>');

            foreach ($result->getErrors() as $error) {
                $output->writeln(" - $error");
            }
        }
    }
}
