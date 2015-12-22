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

use Blackfire\Client as BlackfireClient;
use Blackfire\ClientConfiguration as BlackfireClientConfiguration;
use Blackfire\Player\Player;
use Blackfire\Player\Extension\BlackfireExtension;
use Blackfire\Player\Loader\YamlLoader;
use Blackfire\Player\ScenarioSet;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;

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
                new InputArgument('files', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The files defining the scenarios'),
                new InputOption('concurrency', 'c', InputOption::VALUE_REQUIRED, 'The number of clients to create', 1),
                new InputOption('endpoint', '', InputOption::VALUE_REQUIRED, 'Override the scenario endpoint', null),
                new InputOption('output', 'o', InputOption::VALUE_REQUIRED, 'Saves the extracted values', null),
                new InputOption('blackfire', '', InputOption::VALUE_REQUIRED, 'Enabled Blackfire and use the specified environment', null),
                new InputOption('variables', '', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Override a variable value', null),
            ])
            ->setDescription('Runs a scenario YAML file')
            ->setHelp(<<<EOF
Read https://blackfire.io/docs/player/cli to learn about all supported options.
EOF
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new ConsoleLogger($output);

        $clients = [$this->createClient()];
        for ($i = 1; $i < $input->getOption('concurrency'); ++$i) {
            $clients[] = $this->createClient();
        }

        $player = new Player($clients);
        $player->setLogger($logger);

        if ($env = $input->getOption('blackfire')) {
            $blackfireConfig = new BlackfireClientConfiguration();
            $blackfireConfig->setEnv($env);
            $blackfire = new BlackfireClient($blackfireConfig);

            $player->addExtension(new BlackfireExtension($blackfire, $logger));
        }

        $variables = [];
        foreach ($input->getOption('variables') as $variable) {
            list($key, $value) = explode('=', $variable, 2);
            $variables[$key] = $value;
        }

        $loader = new YamlLoader();
        $scenarios = new ScenarioSet();
        foreach ($input->getArgument('files') as $file) {
            if (!is_file($file)) {
                throw new \InvalidArgumentException(sprintf('File "%s" does not exist.', $file));
            }

            foreach ($loader->load(file_get_contents($file)) as $reference => $scenario) {
                if ($input->getOption('endpoint')) {
                    $scenario->endpoint($input->getOption('endpoint'));
                }

                foreach ($variables as $key => $value) {
                    $scenario->value($key, $value);
                }

                $scenarios->add($scenario, $reference);
            }
        }

        $results = $player->runMulti($scenarios);

        if ($output = $input->getOption('output')) {
            $values = [];
            foreach ($results as $result) {
                $values[] = $result->getValues()->all();
            }

            file_put_contents($output, json_encode($values, JSON_PRETTY_PRINT));
        }

        // any scenario with an error?
        foreach ($results as $result) {
            if ($result->isErrored()) {
                return 1;
            }
        }
    }

    private function createClient()
    {
        return new GuzzleClient([
            'cookies' => true,
        ]);
    }
}
