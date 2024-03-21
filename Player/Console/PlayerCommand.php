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

use Blackfire\Client;
use Blackfire\ClientConfiguration;
use Blackfire\Player\Adapter\BlackfireSdkAdapter;
use Blackfire\Player\Adapter\BlackfireSdkAdapterInterface;
use Blackfire\Player\BuildApi;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ExpressionLanguage\Provider as LanguageProvider;
use Blackfire\Player\Extension\BlackfireEnvResolver;
use Blackfire\Player\Extension\BlackfireExtension;
use Blackfire\Player\Extension\CliFeedbackExtension;
use Blackfire\Player\Extension\DisableInternalNetworkExtension;
use Blackfire\Player\Extension\ExpectationExtension;
use Blackfire\Player\Extension\FollowExtension;
use Blackfire\Player\Extension\InteractiveStepByStepExtension;
use Blackfire\Player\Extension\NameResolverExtension;
use Blackfire\Player\Extension\ResetCookieJarExtension;
use Blackfire\Player\Extension\ResponseChecker;
use Blackfire\Player\Extension\SecurityExtension;
use Blackfire\Player\Extension\ThrowableExtension;
use Blackfire\Player\Extension\TmpDirExtension;
use Blackfire\Player\Extension\TracerExtension;
use Blackfire\Player\Extension\WaitExtension;
use Blackfire\Player\Extension\WatchdogExtension;
use Blackfire\Player\Http\CookieJar;
use Blackfire\Player\ParserFactory;
use Blackfire\Player\Player;
use Blackfire\Player\PlayerNext;
use Blackfire\Player\Reporter\JsonViewReporter;
use Blackfire\Player\ScenarioSetResult;
use Blackfire\Player\Serializer\ScenarioSetSerializer;
use Blackfire\Player\StepProcessor\BlockStepProcessor;
use Blackfire\Player\StepProcessor\ChainProcessor;
use Blackfire\Player\StepProcessor\ClickStepProcessor;
use Blackfire\Player\StepProcessor\ConditionStepProcessor;
use Blackfire\Player\StepProcessor\ExpressionEvaluator;
use Blackfire\Player\StepProcessor\FollowStepProcessor;
use Blackfire\Player\StepProcessor\LoopStepProcessor;
use Blackfire\Player\StepProcessor\ReloadStepProcessor;
use Blackfire\Player\StepProcessor\RequestStepProcessor;
use Blackfire\Player\StepProcessor\StepContextFactory;
use Blackfire\Player\StepProcessor\SubmitStepProcessor;
use Blackfire\Player\StepProcessor\UriResolver;
use Blackfire\Player\StepProcessor\VariablesEvaluator;
use Blackfire\Player\StepProcessor\VisitStepProcessor;
use Blackfire\Player\StepProcessor\WhileStepProcessor;
use Blackfire\Player\VariableResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Dumper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
    public const EXIT_CODE_BLACKFIRE_NETWORK_ERROR = 67;

    private HttpClientInterface|null $blackfireHttpClient;
    private BlackfireSdkAdapterInterface|null $blackfireSdkAdapter;
    private string $transactionId;

    public function __construct(
        HttpClientInterface|null $blackfireHttpClient, BlackfireSdkAdapterInterface|null $blackfireSdkAdapter, string $transactionId)
    {
        $this->blackfireHttpClient = $blackfireHttpClient;
        $this->blackfireSdkAdapter = $blackfireSdkAdapter;
        $this->transactionId = $transactionId;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('run')
            ->setDefinition([
                new InputArgument('file', InputArgument::OPTIONAL, 'The file defining the scenarios'),
                new InputOption('config', '', InputOption::VALUE_OPTIONAL, 'The configuration file to retrieve configuration from', null),
                new InputOption('concurrency', 'c', InputOption::VALUE_REQUIRED, 'The number of clients to create', 1),
                new InputOption('endpoint', '', InputOption::VALUE_REQUIRED, 'Override the scenario endpoint', null),
                new InputOption('json', '', InputOption::VALUE_NONE, 'Outputs execution report as JSON', null),
                new InputOption('variable', '', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Declare or override a variable value', null),
                new InputOption('tracer', '', InputOption::VALUE_NONE, 'Store debug information on disk', null),
                new InputOption('disable-internal-network', '', InputOption::VALUE_NONE, 'Disable internal network', null),
                new InputOption('sandbox', '', InputOption::VALUE_NONE, 'Enable the sandbox mode', null),
                new InputOption('ssl-no-verify', '', InputOption::VALUE_NONE, 'Disable SSL certificate verification', null),
                new InputOption('blackfire-env', '', InputOption::VALUE_REQUIRED, 'The blackfire environment to use'),
                new InputOption('step', '', InputOption::VALUE_NONE, 'Interactive execution. Ask user validation before every step.', null),
            ])
            ->setDescription('Runs scenario files')
            ->setHelp('Read https://docs.blackfire.io/builds-cookbooks/player to learn about all supported options.')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $initializer = new CommandInitializer();
        $initializer($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $resultOutput = $output;
        if ($output instanceof ConsoleOutput) {
            $output = $output->getErrorOutput();
            $output->setFormatter($resultOutput->getFormatter());
        }

        // The Blackfire SDK Adapter is always null in production. We only inject one for testing purpose.
        if (!$this->blackfireSdkAdapter) {
            $clientConfiguration = null;
            if (null !== $configFile = $input->getOption('config')) {
                $clientConfiguration = ClientConfiguration::createFromFile($configFile);
            }

            if (!$clientConfiguration) {
                $clientConfiguration = new ClientConfiguration();
            }

            $blackfire = new Client($clientConfiguration);
            $blackfire->getConfiguration()->setUserAgentSuffix(sprintf('Blackfire Player/%s', Player::version()));

            $this->blackfireSdkAdapter = new BlackfireSdkAdapter($blackfire);
        }

        [
            'client_id' => $clientId,
            'client_token' => $clientToken,
            'endpoint' => $endpoint,
        ] = $this->getConfig($this->blackfireSdkAdapter);

        if (!$this->blackfireHttpClient) {
            $errorMessagePattern = 'Missing required "%s" configuration. Either configure it using "%s" environment variable or in your .blackfire.ini file';

            if (!$clientId) {
                throw new \InvalidArgumentException(sprintf($errorMessagePattern, 'client_id', 'BLACKFIRE_CLIENT_ID'));
            }

            if (!$clientToken) {
                throw new \InvalidArgumentException(sprintf($errorMessagePattern, 'client_token', 'BLACKFIRE_CLIENT_TOKEN'));
            }

            $this->blackfireHttpClient = HttpClient::create([
                'base_uri' => $endpoint,
                'auth_basic' => [$clientId, $clientToken],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => sprintf('Blackfire Player/%s', Player::version()),
                    'X-Request-Id' => $this->transactionId,
                ],
            ]);
        }

        $json = $input->getOption('json');
        $sslNoVerify = $input->getOption('ssl-no-verify');
        $concurrency = $input->getOption('concurrency');
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

        $cookieJar = new CookieJar();
        $uriResolver = new UriResolver();
        $filesystem = new Filesystem();
        $scenarioSerializer = new ScenarioSetSerializer();

        $httpClientOptions = [
            'max_redirects' => 0,
            'verify_peer' => !$sslNoVerify,
            'verify_host' => !$sslNoVerify,
        ];

        $httpClient = HttpClient::create($httpClientOptions);

        $authBasic = array_filter([
            $this->getEnvOrDefault('BLACKFIRE_BASIC_AUTH_USERNAME'),
            $this->getEnvOrDefault('BLACKFIRE_BASIC_AUTH_PASSWORD'),
        ]);

        if (!empty($authBasic) && $input->getOption('endpoint')) {
            $httpClient = ScopingHttpClient::forBaseUri($httpClient, $input->getOption('endpoint'), [
                ...$httpClientOptions,
                'auth_basic' => $authBasic,
            ]);
        }

        $language = new ExpressionLanguage(null, [new LanguageProvider(null, $sandbox)]);
        $buildApi = new BuildApi($this->blackfireSdkAdapter, $this->blackfireHttpClient);
        $expressionEvaluator = new ExpressionEvaluator($language);
        $variableResolver = new VariableResolver($language);
        $blackfireEnvResolver = new BlackfireEnvResolver($input->getOption('blackfire-env'), $language);
        $player = new PlayerNext(
            new StepContextFactory(new VariableResolver($language)),
            new JsonViewReporter($scenarioSerializer, $buildApi, $output),
            new ChainProcessor([
                new VisitStepProcessor($expressionEvaluator, $uriResolver),
                new ClickStepProcessor($expressionEvaluator, $uriResolver),
                new SubmitStepProcessor($expressionEvaluator, $uriResolver),
                new FollowStepProcessor($uriResolver),
                new ReloadStepProcessor(),
                new RequestStepProcessor($httpClient, $cookieJar),

                new LoopStepProcessor($expressionEvaluator),
                new WhileStepProcessor($expressionEvaluator),
                new ConditionStepProcessor($expressionEvaluator),
                new BlockStepProcessor(),
            ]),
            new VariablesEvaluator($language),
        );

        if ($input->getOption('step')) {
            $player->addExtension(new InteractiveStepByStepExtension($this->getHelper('question'), $input, $output, $variableResolver), 2048);
        }

        $player->addExtension(new TmpDirExtension($filesystem));
        $player->addExtension(new ExpectationExtension(new ResponseChecker($language)), 512);
        $player->addExtension(new NameResolverExtension($language, $variableResolver), 1024);
        $player->addExtension(new WaitExtension($language));
        $player->addExtension(new FollowExtension($language));
        $player->addExtension(new WatchdogExtension());
        $player->addExtension(new SecurityExtension());
        $player->addExtension(new ThrowableExtension());
        $player->addExtension(new ResetCookieJarExtension($cookieJar));
        $player->addExtension(new BlackfireExtension(
            $language,
            $blackfireEnvResolver,
            $buildApi,
            $this->blackfireSdkAdapter,
        ), 510);
        $player->addExtension(new CliFeedbackExtension(
            $output,
            new Dumper($output),
            (new Terminal())->getWidth())
        );
        if ($input->getOption('tracer')) {
            $player->addExtension(new TracerExtension($output, $filesystem));
        }
        if ($input->getOption('disable-internal-network')) {
            $player->addExtension(new DisableInternalNetworkExtension());
        }

        $scenarios = (new ScenarioHydrator(new ParserFactory($language)))->hydrate($input);
        $results = $player->run($scenarios, $concurrency);

        $exitCode = 0;
        $message = 'Build run successfully';

        if ($results->isBlackfireNetworkError()) {
            $exitCode = self::EXIT_CODE_BLACKFIRE_NETWORK_ERROR;
            $message = 'Build encountered an error while reaching the Blackfire APIs';
        } elseif ($results->isFatalError()) {
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

    private function createReport(ScenarioSetResult $results): array
    {
        $report = [];

        foreach ($results->getScenarioResults() as $scenarioResult) {
            $error = $scenarioResult->getError();

            $report[] = [
                'scenario' => $scenarioResult->getScenarioName(),
                'values' => $scenarioResult->getValues(),
                'error' => $error ? ['message' => $error->getMessage(), 'code' => $error->getCode()] : null,
            ];
        }

        return $report;
    }

    private function getEnvOrDefault(string $envVar, string|null $default = null): string|null
    {
        $env = getenv($envVar);
        if (!$env) {
            $env = $default;
        }

        return $env;
    }

    private function getEnvOrThrow(string $envVar): string
    {
        $env = getenv($envVar);
        if (!$env) {
            throw new \InvalidArgumentException(sprintf('Missing required "%s" environment variable', $envVar));
        }

        return $env;
    }

    private function getConfig(BlackfireSdkAdapterInterface $adapter): array
    {
        $clientId = $adapter->getConfiguration()->getClientId();
        $clientToken = $adapter->getConfiguration()->getClientToken();
        $endpoint = $adapter->getConfiguration()->getEndPoint();

        return [
            'client_id' => $clientId,
            'client_token' => $clientToken,
            'endpoint' => $endpoint,
        ];
    }
}
