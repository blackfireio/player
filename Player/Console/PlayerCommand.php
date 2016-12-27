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

use Blackfire\Player\Extension\CliFeedbackExtension;
use Blackfire\Player\Guzzle\Runner;
use Blackfire\Player\Parser;
use Blackfire\Player\Player;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class PlayerCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('run')
            ->setDefinition([
                new InputArgument('file', InputArgument::REQUIRED, 'The file defining the scenarios'),
                new InputOption('concurrency', 'c', InputOption::VALUE_REQUIRED, 'The number of clients to create', 1),
                new InputOption('endpoint', '', InputOption::VALUE_REQUIRED, 'Override the scenario endpoint', null),
                new InputOption('json', '', InputOption::VALUE_NONE, 'Outputs variable values as JSON', null),
                new InputOption('variable', '', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Override a variable value', null),
                new InputOption('validate', '', InputOption::VALUE_NONE, 'Validate syntax without running', null),
                new InputOption('tracer', '', InputOption::VALUE_NONE, 'Store debug information on disk', null),
            ])
            ->setDescription('Runs scenario files')
            ->setHelp(<<<'EOF'
Read https://blackfire.io/docs/player/cli to learn about all supported options.
EOF
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $output->getFormatter()->setStyle('title', new OutputFormatterStyle('black', 'yellow'));
        $output->getFormatter()->setStyle('debug', new OutputFormatterStyle('red', 'black'));
        $output->getFormatter()->setStyle('failure', new OutputFormatterStyle('white', 'red'));
        $output->getFormatter()->setStyle('success', new OutputFormatterStyle('white', 'green'));
        $output->getFormatter()->setStyle('detail', new OutputFormatterStyle('white', 'blue'));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $clients = [$this->createClient($output)];
        for ($i = 1; $i < $input->getOption('concurrency'); ++$i) {
            $clients[] = $this->createClient($output);
        }

        $runner = new Runner($clients);

        $player = new Player($runner, $input->getOption('tracer'));
        if (!$input->getOption('json')) {
            $player->addExtension(new CliFeedbackExtension($output, $this->getApplication()->getTerminalDimensions()));
        }

        $variables = [];
        foreach ($input->getOption('variable') as $variable) {
            list($key, $value) = explode('=', $variable, 2);
            $variables[$key] = $value;
        }

        $parser = new Parser();
        $scenarios = $parser->load($input->getArgument('file'));

        // FIXME: should be set on the ScenarioSet directly
        // but for this, we need an enterStep() for the ScenarioSet, which we don't have yet
        foreach ($scenarios as $scenario) {
            if (null !== $input->getOption('endpoint')) {
                $scenario->endpoint($this->escapeValue($input->getOption('endpoint')));
            }

            foreach ($parser->getGlobalVariables() as $key => $value) {
                // Override only if the endpoint is not already defined in the step
                if ($key === 'endpoint' && null === $scenario->getEndpoint() && null === $input->getOption('endpoint')) {
                    $scenario->endpoint($this->escapeValue($value));
                }

                $scenario->set($key, $this->escapeValue($value));
            }

            foreach ($variables as $key => $value) {
                $scenario->set($key, $this->escapeValue($value));
            }
        }

        if ($input->getOption('validate')) {
            $output->writeln('<info>The scenarios are valid.</>');

            return;
        }

        $results = $player->run($scenarios);

        if ($input->getOption('json')) {
            $output->writeln(json_encode($results->getValues(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // any scenario with an error?
        if ($results->isErrored()) {
            return 1;
        }
    }

    private function createClient(OutputInterface $output)
    {
        return new GuzzleClient(['cookies' => true, 'allow_redirects' => false, 'http_errors' => false]);
    }

    private function escapeValue($value)
    {
        return sprintf("'%s'", $value);
    }
}
