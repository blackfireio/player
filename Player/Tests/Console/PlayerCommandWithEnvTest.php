<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Console;

use Blackfire\Player\Console\Application;
use Blackfire\Player\Enum\BuildStatus;
use Blackfire\Player\Tests\Adapter\StubbedSdkAdapter;
use Blackfire\Player\Tests\Http\JsonViewLoggerHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;

class PlayerCommandWithEnvTest extends TestCase
{
    use MockServerTrait;

    private const FIXTURES_DIR = 'fixtures-run-with-env';
    protected static string $port;

    private JsonViewLoggerHttpClient $jsonViewLoggerHttpClient;

    public static function setUpBeforeClass(): void
    {
        self::$port = getenv('BLACKFIRE_WS_PORT') ?: '8399';
        self::getRunningServer(self::FIXTURES_DIR);
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
    }

    protected function setUp(): void
    {
        $this->jsonViewLoggerHttpClient = new JsonViewLoggerHttpClient(new MockHttpClient());
        $this->jsonViewLoggerHttpClient->resetLastJsonView();
        @unlink(sys_get_temp_dir().'/probe_mock_state.json');
    }

    /** @dataProvider providePlayerWithEnvTests */
    public function testPlayerWithEnvironment($file, $expectedOutput, StubbedSdkAdapter $sdkAdapter, string $envName, array $testOptions)
    {
        $expectedExitCode = $testOptions['expected_exit_code'];
        $expectedReportOutput = $testOptions['report_file'];
        $cliOptions = $testOptions['cli_options'];

        $application = new Application(
            $sdkAdapter,
            $this->jsonViewLoggerHttpClient,
            'a396ccc8-51e1-4047-93aa-ca3f3847f425'
        );

        $tester = new CommandTester($application->get('run'));
        $tester->execute(array_merge([
            'file' => $file,
            '--endpoint' => 'http://0:'.self::$port,
            '--blackfire-env' => $envName,
        ], $cliOptions));

        $output = $tester->getDisplay();
        $output = implode("\n", array_map('rtrim', explode("\n", $output)));
        $expectedOutput = str_replace('{{ PORT }}', self::$port, $expectedOutput);
        $expectedOutput = str_replace('{{ SCENARIO_FILE }}', $file, $expectedOutput);

        $this->assertStringMatchesFormat($expectedOutput, $output);

        $this->assertEquals($expectedExitCode, $tester->getStatusCode());
        $latestJsonView = $this->jsonViewLoggerHttpClient->getLastJsonView();

        $this->assertNotNull($latestJsonView);
        $this->assertEquals(BuildStatus::DONE->value, $latestJsonView['status']);

        // For --json or --full-report, the output is composed of STDOUT + STDERR.
        // That's because the CommandTester use a StreamOutput instead of a ConsoleOutput.

        if ($expectedReportOutput) {
            if (!$this->resetProbeMockState($testOptions['folder_name'])) {
                $this->fail('Failed to reset mock state');
            }

            $tester->execute([
                'file' => $file,
                '--endpoint' => 'http://0:'.self::$port,
                '--json' => true,
                '--blackfire-env' => $envName,
            ]);

            $output = $tester->getDisplay();
            $output = implode("\n", array_map('rtrim', explode("\n", $output)));
            $this->assertStringMatchesFormat($expectedReportOutput, $output);
        }
    }

    public function providePlayerWithEnvTests()
    {
        $dirs = Finder::create()
            ->in(__DIR__.'/../'.self::FIXTURES_DIR)
            ->directories();

        foreach ($dirs as $dir) {
            foreach (['index.php', 'output-next.txt', 'scenario.bkf'] as $file) {
                $file = \sprintf('%s/%s', $dir->getPathname(), $file);
                if (!file_exists($file)) {
                    throw new \Exception(\sprintf('The fixture file "%s" does not exist.', $file));
                }
            }

            $reportFile = \sprintf('%s/output-next-full-report.txt', $dir->getPathname());
            $cliOptions = \sprintf('%s/cli-options.php', $dir->getPathname());
            $exitCodeFile = \sprintf('%s/exit-code.txt', $dir->getPathname());
            $stubbedSdkAdapterFile = \sprintf('%s/stubbedSdkAdapter.php', $dir->getPathname());

            $envName = 'Blackfire Test';

            yield $dir->getBasename() => [
                \sprintf('%s/scenario.bkf', $dir->getPathname()),
                file_get_contents(\sprintf('%s/output-next.txt', $dir->getPathname())),
                file_exists($stubbedSdkAdapterFile) ? require $stubbedSdkAdapterFile : new StubbedSdkAdapter($envName),
                $envName,
                [
                    'expected_exit_code' => file_exists($exitCodeFile) ? (int) (file_get_contents($exitCodeFile)) : 0,
                    'report_file' => file_exists($reportFile) ? file_get_contents($reportFile) : null,
                    'cli_options' => file_exists($cliOptions) ? require $cliOptions : [],
                    'folder_name' => $dir->getBasename(),
                ],
            ];
        }
    }

    private function resetProbeMockState(string $endpoint): bool
    {
        $probeMockClient = HttpClient::createForBaseUri('http://0:'.self::$port);
        $response = $probeMockClient->request('GET', '/'.$endpoint.'/index.php', [
            'headers' => [
                'X-Probe-Mock-Reset' => 1,
            ],
        ]);

        return 202 === $response->getStatusCode();
    }
}
