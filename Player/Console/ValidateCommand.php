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

use Blackfire\Player\Parser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Blackfire\Player\Exception\ExpressionSyntaxErrorException;
use Blackfire\Player\Exception\InvalidArgumentException;
use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\Exception\SyntaxErrorException;

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
        $parser = new Parser();

        try {
            $parser->load($input->getArgument('file'));
        } catch (SyntaxErrorException $e) {
            return $this->handleError($e, $input, $output);
        } catch (ExpressionSyntaxErrorException $e) {
            return $this->handleError($e, $input, $output);
        } catch (InvalidArgumentException $e) {
            return $this->handleError($e, $input, $output);
        } catch (LogicException $e) {
            return $this->handleError($e, $input, $output);
        }

        if ($input->getOption('json')) {
            $output->writeln(JsonOutput::encode([
                'message' => 'The scenarios are valid.',
                'success' => true,
                'errors' => null,
                'code' => 0,
            ]));
        } else {
            $output->writeln('<info>The scenarios are valid.</>');
        }
    }

    private function handleError(\Throwable $e, InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('json')) {
            $output->writeln(JsonOutput::encode([
                'message' => $e->getMessage(),
                'success' => false,
                'errors' => null,
                'code' => 0,
            ]));
        } else {
            $output->writeln($e->getMessage());
        }

        return 1;
    }
}
