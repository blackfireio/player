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
use Symfony\Component\Console\Style\SymfonyStyle;

final class ValidateCommand extends Command
{
    public const EXIT_CODE_FAILURE = 64;

    protected function configure()
    {
        $this
            ->setName('validate')
            ->setDefinition([
                new InputArgument('file', InputArgument::OPTIONAL, 'The file defining the scenarios'),
                new InputOption('json', '', InputOption::VALUE_NONE, 'Outputs result as JSON', null),
                new InputOption('endpoint', '', InputOption::VALUE_REQUIRED, 'Override the scenario endpoint', null),
                new InputOption('variable', '', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Declare or override a variable value', null),
            ])
            ->setDescription('Validate scenario files')
            ->setHelp('Read https://blackfire.io/docs/builds-cookbooks/player to learn about all supported options.')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $initializer = new CommandInitializer();
        $initializer($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $variables = (new ScenarioHydrator())->getVariables($input);
        $validator = new BkfValidator();
        if (!$input->getArgument('file') || 'php://stdin' === $input->getArgument('file')) {
            $result = $validator->validate(file_get_contents('php://stdin'), $variables, true);
        } else {
            $result = $validator->validateFile($input->getArgument('file'), $variables, true);
        }

        if ($input->getOption('json')) {
            $output->writeln(JsonOutput::encode([
                'message' => $result->isSuccess() ? 'The scenarios are valid.' : 'The scenarios are not valid.',
                'success' => $result->isSuccess(),
                'errors' => $result->getErrors(),
                'missing_variables' => $result->getMissingVariables(),
                'code' => $result->isSuccess() ? 0 : self::EXIT_CODE_FAILURE,
            ]));
        } elseif ($result->isSuccess()) {
            $output->writeln('<info>The scenarios are valid.</>');
            if ($missingVariables = $result->getMissingVariables()) {
                $io = new SymfonyStyle($input, $output);
                $io->note(array_merge(['You need to define the following variables using the `--variable` option:'], $missingVariables));
            }
        } else {
            $output->writeln('<info>The scenarios are not valid:</>');

            foreach ($result->getErrors() as $error) {
                $output->writeln(" - $error");
            }

            return self::EXIT_CODE_FAILURE;
        }

        return 0;
    }
}
