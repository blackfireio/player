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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ValidateCommandTest extends TestCase
{
    public function providePlayerTests()
    {
        $dirs = Finder::create()
            ->in(__DIR__.'/../fixtures-validate')
            ->directories();

        foreach ($dirs as $dir) {
            foreach (['output.txt', 'scenario.bkf'] as $file) {
                $file = sprintf('%s/%s', $dir->getPathname(), $file);
                if (!file_exists($file)) {
                    throw new \Exception(sprintf('The fixture file "%s" does not exist.', $file));
                }
            }

            $jsonFile = sprintf('%s/output-json.txt', $dir->getPathname());

            yield $dir->getBasename() => [
                sprintf('%s/scenario.bkf', $dir->getPathname()),
                file_get_contents(sprintf('%s/output.txt', $dir->getPathname())),
                file_exists($jsonFile) ? file_get_contents($jsonFile) : null,
            ];
        }
    }

    /** @dataProvider providePlayerTests */
    public function testValidate($file, $expectedOutput, $expectedJsonOutput)
    {
        $application = new Application();
        $tester = new CommandTester($application->get('validate'));
        $tester->execute([
            'file' => $file,
        ]);

        $output = $tester->getDisplay();
        $output = implode("\n", array_map('rtrim', explode("\n", $output)));

        $this->assertStringMatchesFormat($expectedOutput, $output);

        // For --json and --full-report, the output is composed of STDOUT + STDERR.
        // That's because the CommandTester use a StreamOutput instead of a ConsoleOutput.

        if ($expectedJsonOutput) {
            $tester->execute([
                'file' => $file,
                '--json' => true,
            ]);

            $this->assertStringMatchesFormat($expectedJsonOutput, $tester->getDisplay());
        }
    }

    public function testErrorInRealWorld()
    {
        $finder = new PhpExecutableFinder();
        $process = new Process([$finder->find(), 'blackfire-player.php', 'run', '../composer.json', '--json'], __DIR__.'/../../../bin');
        $process->run();

        $expectedOutput = '{
    "message": "Cannot load file \"../composer.json\" because it does not have the right extension. Expected \"bkf\", got \"json\".",
    "success": false,
    "errors": [],
    "input": {
        "path": "../composer.json",
        "content": "{\n    \"name\": \"blackfire/player\",\n    \"type\": \"project\",\n    \"description\": \"A powerful web crawler and web scraper with Blackfire support\",\n    \"keywords\": [\"scraper\", \"crawler\", \"blackfire\"],\n    \"homepage\": \"https://blackfire.io/player\",\n    \"license\": \"MIT\",\n    \"authors\": [\n        {\n            \"name\": \"Fabien Potencier\",\n            \"email\": \"fabien@blackfire.io\",\n            \"homepage\": \"https://blackfire.io/\",\n            \"role\": \"Lead Developer\"\n        }\n    ],\n    \"require\": {\n        \"php\": \">=5.5.9\",\n        \"blackfire/php-sdk\": \"^1.17\",\n        \"fzaninotto/faker\": \"^1.5\",\n        \"guzzlehttp/guzzle\": \"^6.1\",\n        \"mtdowling/jmespath.php\": \"^2.2\",\n        \"psr/log\": \"^1.0\",\n        \"symfony/console\": \"^3.2|^4.0\",\n        \"symfony/css-selector\": \"^3.2|^4.0\",\n        \"symfony/dom-crawler\": \"^3.2|^4.0\",\n        \"symfony/expression-language\": \"^3.2|^4.0\",\n        \"symfony/filesystem\": \"^3.2|^4.0\",\n        \"symfony/var-dumper\": \"^3.2|^4.0\",\n        \"symfony/yaml\": \"^3.2|^4.0\",\n        \"webmozart/glob\": \"^4.0\",\n        \"symfony/event-dispatcher\": \"^3.4|^4.0\"\n    },\n    \"require-dev\": {\n        \"symfony/finder\": \"^3.2|^4.0\",\n        \"symfony/process\": \"^3.2|^4.0\",\n        \"symfony/phpunit-bridge\": \"^3.2|^4.0\"\n    },\n    \"autoload\": {\n        \"psr-4\" : {\n            \"Blackfire\\\\\\\\Player\\\\\\\\\" : \"Player\"\n        },\n        \"exclude-from-classmap\": [\n            \"/Tests/\"\n        ]\n    },\n    \"config\": {\n        \"platform\": {\n            \"php\": \"5.5.9\"\n        }\n    },\n    \"extra\": {\n        \"branch-alias\": {\n            \"dev-master\": \"1.0-dev\"\n        }\n    }\n}\n"
    }
}
';

        $expectedErrorOutput = <<<EOD
  [ERROR]                                                                      
  Cannot load file "../composer.json" because it does not have the right exte  
  nsion. Expected "bkf", got "json".                                           
                                                                               
  Player documentation at https://blackfire.io/player                          
EOD;

        $this->assertSame($expectedOutput, $process->getOutput());
        $this->assertContains($expectedErrorOutput, $process->getErrorOutput());
    }
}
