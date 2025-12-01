<?php

declare(strict_types=1);

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
use Blackfire\Player\Tests\Adapter\StubbedSdkAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class PlayerCommandTest extends TestCase
{
    use MockServerTrait;

    private const string FIXTURES_DIR = 'fixtures-run';
    private static string $port;

    public static function setUpBeforeClass(): void
    {
        self::$port = getenv('BLACKFIRE_WS_PORT') ?: '8399';
        self::getRunningServer(self::FIXTURES_DIR, self::$port);
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
    }

    public static function providePlayerTests(): \Iterator
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

            yield $dir->getBasename().' (next)' => [
                \sprintf('%s/scenario.bkf', $dir->getPathname()),
                file_get_contents(\sprintf('%s/output-next.txt', $dir->getPathname())),
                [
                    'expected_exit_code' => file_exists($exitCodeFile) ? (int) (file_get_contents($exitCodeFile)) : 0,
                    'report_file' => file_exists($reportFile) ? file_get_contents($reportFile) : null,
                    'cli_options' => file_exists($cliOptions) ? require $cliOptions : [],
                ],
            ];
        }
    }

    #[DataProvider('providePlayerTests')]
    public function testPlayer($file, string|bool $expectedOutput, array $testOptions): void
    {
        $expectedExitCode = $testOptions['expected_exit_code'];
        $expectedReportOutput = $testOptions['report_file'];
        $cliOptions = $testOptions['cli_options'];

        $application = new Application(
            new StubbedSdkAdapter('Blackfire Test'),
            new MockHttpClient(),
            'a396ccc8-51e1-4047-93aa-ca3f3847f425',
        );

        $tester = new CommandTester($application->get('run'));
        $tester->execute(array_merge([
            'file' => $file,
            '--endpoint' => 'http://0:'.self::$port,
        ], $cliOptions));

        $output = $tester->getDisplay();
        $output = implode("\n", array_map(rtrim(...), explode("\n", $output)));
        $expectedOutput = str_replace('{{ PORT }}', self::$port, $expectedOutput);
        $expectedOutput = str_replace('{{ SCENARIO_FILE }}', $file, $expectedOutput);

        $this->assertStringMatchesFormat($expectedOutput, $output);

        // For --json or --full-report, the output is composed of STDOUT + STDERR.
        // That's because the CommandTester use a StreamOutput instead of a ConsoleOutput.

        $this->assertEquals($expectedExitCode, $tester->getStatusCode());

        if ($expectedReportOutput) {
            $tester->execute([
                'file' => $file,
                '--endpoint' => 'http://0:'.self::$port,
                '--json' => true,
            ]);

            $output = $tester->getDisplay();
            $output = implode("\n", array_map(rtrim(...), explode("\n", $output)));
            $this->assertStringMatchesFormat($expectedReportOutput, $output);
        }
    }

    public function testErrorStdIn(): void
    {
        $finder = new PhpExecutableFinder();
        $process = new Process([$finder->find(), 'blackfire-player.php', 'run', '--json'], __DIR__.'/../../../bin');
        $process->setInput('papilou!');
        $process->run();

        $expectedOutput = '{
    "message": "Unable to parse \"papilou!\" at line 1.",
    "success": false,
    "errors": [],
    "input": {
        "path": "php://stdin",
        "content": "papilou!"
    }
}
';

        $expectedErrorOutput = <<<EOD
  Unable to parse "papilou!" at line 1.
EOD;

        $this->assertSame($expectedOutput, $process->getOutput());
        $this->assertStringContainsString($expectedErrorOutput, $process->getErrorOutput());
    }

    public function testNoEndpoint(): void
    {
        $script = <<<EOS
scenario
    name "Test"
    visit "/"
        expect status_code() == 200
EOS;

        $finder = new PhpExecutableFinder();
        $process = new Process([$finder->find(), 'blackfire-player.php', 'run', '--json'], __DIR__.'/../../../bin');
        $process->setInput($script);
        $process->run();

        $expectedOutput = '{
    "name": null,
    "results": [
        {
            "scenario": "\"Test\"",
            "values": [],
            "error": {
                "message": "Unable to crawl a non-absolute URI (/). Did you forget to set an \"endpoint\"?",
                "code": 0
            }
        }
    ],
    "message": "Build encountered a fatal error",
    "code": 65,
    "success": true,
    "input": {
        "path": "php://stdin",
        "content": "scenario\n    name \"Test\"\n    visit \"/\"\n        expect status_code() == 200"
    },
    "blackfire_build": {
        "version": %f,
        "name": null,
        "variables": [],
        "endpoint": "",
        "blackfire_environment": null,
        "status": "done",
        "scenarios": [
            {
                "status": "done",
                "variables": [],
                "steps": [
                    {
                        "uri": "\"/\"",
                        "status": "done",
                        "expectations": [
                            "status_code() == 200"
                        ],
                        "is_blackfire_enabled": true,
                        "errors": [
                            "Unable to crawl a non-absolute URI (/). Did you forget to set an \"endpoint\"?"
                        ],
                        "uuid": "%x-%x-%x-%x-%x",
                        "started_at": %d,
                        "finished_at": %d,
                        "line": 3,
                        "type": "visit"
                    }
                ],
                "name": "Test",
                "uuid": "%x-%x-%x-%x-%x",
                "started_at": %d,
                "finished_at": %d,
                "line": 1
            }
        ]
    }
}
';

        $this->assertStringMatchesFormat($expectedOutput, $process->getOutput());
        $this->assertStringContainsString('Unable to crawl a non-absolute URI (/). Did you forget to set an "endpoint"?', $process->getErrorOutput());
    }

    public function testErrorInRealWorld(): void
    {
        $finder = new PhpExecutableFinder();
        $process = new Process([$finder->find(), 'blackfire-player.php', 'run', '../Player/Tests/fixtures-validate/scenario.json', '--no-ansi', '--json'], __DIR__.'/../../../bin');
        $process->run();

        $expectedOutput = '{
    "message": "Cannot load file \"../Player/Tests/fixtures-validate/scenario.json\" because it does not have the right extension. Expected \"bkf\", got \"json\".",
    "success": false,
    "errors": [],
    "input": {
        "path": "../Player/Tests/fixtures-validate/scenario.json",
        "content": "{\n  \"message\": \"I\'m not a validate scenario file!\"\n}\n"
    }
}
';

        $expectedErrorOutput = <<<EOD

  [ERROR]
  Cannot load file "../Player/Tests/fixtures-validate/scenario.json" because
  it does not have the right extension. Expected "bkf", got "json".

  Player documentation at https://blackfire.io/player

EOD;

        $oneLineExpected = implode(' ', array_filter(array_map(trim(...), explode("\n", $expectedErrorOutput))));
        $oneLineOutput = implode(' ', array_filter(array_map(trim(...), explode("\n", $process->getErrorOutput()))));

        $this->assertSame($expectedOutput, $process->getOutput());
        $this->assertStringContainsString($oneLineExpected, $oneLineOutput);
    }
}
