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

use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ExpressionLanguage\Provider as LanguageProvider;
use Blackfire\Player\Extension\BlackfireExtension;
use Blackfire\Player\Extension\CliFeedbackExtension;
use Blackfire\Player\Extension\DisableInternalNetworkExtension;
use Blackfire\Player\Extension\SecurityExtension;
use Blackfire\Player\Extension\TracerExtension;
use Blackfire\Player\Guzzle\CurlFactory;
use Blackfire\Player\Guzzle\Runner;
use Blackfire\Player\Player;
use Blackfire\Player\Result;
use Blackfire\Player\Results;
use Blackfire\Player\Serializer\ScenarioSetSerializer;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Handler\Proxy;
use GuzzleHttp\HandlerStack;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
final class PlayerCommand extends Command
{
    public const EXIT_CODE_EXPECTATION_ERROR = 64;
    public const EXIT_CODE_SCENARIO_ERROR = 65;
    public const EXIT_CODE_SCENARIO_ERROR_NON_FATAL = 66;

    protected function configure()
    {
        $this
            ->setName('run')
            ->setDefinition([
                new InputArgument('file', InputArgument::OPTIONAL, 'The file defining the scenarios'),
                new InputOption('concurrency', 'c', InputOption::VALUE_REQUIRED, 'The number of clients to create', 1),
                new InputOption('endpoint', '', InputOption::VALUE_REQUIRED, 'Override the scenario endpoint', null),
                new InputOption('json', '', InputOption::VALUE_NONE, 'Outputs execution report as JSON', null),
                new InputOption('variable', '', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Declare or override a variable value', null),
                new InputOption('tracer', '', InputOption::VALUE_NONE, 'Store debug information on disk', null),
                new InputOption('disable-internal-network', '', InputOption::VALUE_NONE, 'Disable internal network', null),
                new InputOption('sandbox', '', InputOption::VALUE_NONE, 'Enable the sandbox mode', null),
                new InputOption('ssl-no-verify', '', InputOption::VALUE_NONE, 'Disable SSL certificate verification', null),
                new InputOption('blackfire-env', '', InputOption::VALUE_REQUIRED, 'The blackfire environment to use'),
            ])
            ->setDescription('Runs scenario files')
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
        $resultOutput = $output;
        if ($output instanceof ConsoleOutput) {
            $output = $output->getErrorOutput();
            $output->setFormatter($resultOutput->getFormatter());
        }

        $json = $input->getOption('json');
        $sslNoVerify = $input->getOption('ssl-no-verify');

        $clients = [$this->createClient($sslNoVerify)];
        $concurrency = $input->getOption('concurrency');
        for ($i = 1; $i < $concurrency; ++$i) {
            $clients[] = $this->createClient($sslNoVerify);
        }

        $runner = new Runner($clients);
        $sandbox = $input->getOption('sandbox');

        if (!$input->getArgument('file') || 'php://stdin' === $input->getArgument('file')) {
            $stdin = fopen('php://stdin', 'rb');
            $copy = fopen('php://memory', 'r+b');
            stream_copy_to_stream($stdin, $copy);
            $input->setArgument('file', $copy);
        } else {
            $extension = pathinfo($input->getArgument('file'), \PATHINFO_EXTENSION);
            if ('yml' === $extension || 'yaml' === $extension) {
                $sandbox = true;
            }
        }

        $language = new ExpressionLanguage(null, [new LanguageProvider(null, $sandbox)]);
        $player = new Player($runner, $language);
        $player->addExtension(new SecurityExtension());
        $player->addExtension(new BlackfireExtension($language, $input->getOption('blackfire-env'), $output), 510);
        $player->addExtension(new CliFeedbackExtension($output, (new Terminal())->getWidth()));
        if ($input->getOption('tracer')) {
            $player->addExtension(new TracerExtension($output));
        }
        if ($input->getOption('disable-internal-network')) {
            $player->addExtension(new DisableInternalNetworkExtension());
        }

        $scenarios = (new ScenarioHydrator())->hydrate($input);

        $results = $player->run($scenarios);

        $exitCode = 0;
        $message = 'Build run successfully';

        if ($results->isFatalError()) {
            $exitCode = self::EXIT_CODE_SCENARIO_ERROR;
            $message = 'Build encountered a fatal error';
        } elseif ($results->isExpectationError()) {
            $exitCode = self::EXIT_CODE_EXPECTATION_ERROR;
            $message = 'Some expectation failed';
        } elseif ($results->isErrored()) {
            $exitCode = self::EXIT_CODE_SCENARIO_ERROR_NON_FATAL;
            $message = 'An error occurred';
        }

        if ($json) {
            $file = $input->getArgument('file');

            if (\is_resource($file)) {
                fseek($file, 0);

                $extraInput = [
                    'path' => 'php://stdin',
                    'content' => @stream_get_contents($file),
                ];
            } else {
                $extraInput = [
                    'path' => $file,
                    'content' => @file_get_contents($file),
                ];
            }

            $scenarioSerializer = new ScenarioSetSerializer();

            $resultOutput->writeln(JsonOutput::encode([
                'name' => $scenarios->getName(),
                'results' => $this->createReport($results),
                'message' => $message,
                'code' => $exitCode,
                'success' => true,
                'input' => $extraInput,
                'blackfire_build' => $scenarioSerializer->normalize($scenarios),
            ]));
        }

        return $exitCode;
    }

    private function createClient($sslNoVerify)
    {
        $handler = $this->createCurlHandler();
        $stack = HandlerStack::create($handler);

        return new GuzzleClient([
            'handler' => $stack,
            'cookies' => true,
            'allow_redirects' => false,
            'http_errors' => false,
            'verify' => !$sslNoVerify,
        ]);
    }

    /**
     * Adapted from \GuzzleHttp\choose_handler() to allow setting the 'handle_factory" option.
     */
    private function createCurlHandler()
    {
        $handlerOptions = [
            'handle_factory' => new CurlFactory(3),
        ];

        if (\function_exists('curl_multi_exec') && \function_exists('curl_exec')) {
            return Proxy::wrapSync(new CurlMultiHandler($handlerOptions), new CurlHandler($handlerOptions));
        }

        if (\function_exists('curl_exec')) {
            return new CurlHandler($handlerOptions);
        }

        if (\function_exists('curl_multi_exec')) {
            return new CurlMultiHandler($handlerOptions);
        }

        throw new \RuntimeException('Blackfire Player requires cURL.');
    }

    private function createReport(Results $results)
    {
        $report = [];

        /** @var Result $result */
        foreach ($results as $key => $result) {
            $error = $result->getError();

            $report[] = [
                'scenario' => $result->getScenarioName(),
                'values' => $result->getValues()->all(),
                'error' => $error ? ['message' => $error->getMessage(), 'code' => $error->getCode()] : null,
            ];
        }

        return $report;
    }
}
