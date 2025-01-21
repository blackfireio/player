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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ValidateCommandTest extends TestCase
{
    public static function providePlayerTests()
    {
        $dirs = Finder::create()
            ->in(__DIR__.'/../fixtures-validate')
            ->directories();

        foreach ($dirs as $dir) {
            foreach (['output.txt', 'output-json.txt', 'scenario.bkf'] as $file) {
                $file = \sprintf('%s/%s', $dir->getPathname(), $file);
                if (!file_exists($file)) {
                    throw new \Exception(\sprintf('The fixture file "%s" does not exist.', $file));
                }
            }

            yield $dir->getBasename() => [
                \sprintf('%s/scenario.bkf', $dir->getPathname()),
                \sprintf('%s/output.txt', $dir->getPathname()),
                \sprintf('%s/output-err.txt', $dir->getPathname()),
                \sprintf('%s/output-json.txt', $dir->getPathname()),
                \sprintf('%s/output-json-err.txt', $dir->getPathname()),
            ];
        }
    }

    /** @dataProvider providePlayerTests */
    public function testValidate($file, $outputPath, $errorOutputPath, $jsonOutputPath, $jsonErrorOutputPath)
    {
        $finder = new PhpExecutableFinder();
        $process = new Process([$finder->find(), 'blackfire-player.php', 'validate', '--no-ansi', $file], __DIR__.'/../../../bin');
        $process->run();

        $this->assertStringMatchesFormat(file_get_contents($outputPath), $process->getOutput());
        $this->assertStringMatchesFormat(file_get_contents($errorOutputPath), $process->getErrorOutput());

        $finder = new PhpExecutableFinder();
        $process = new Process([$finder->find(), 'blackfire-player.php', 'validate', '--no-ansi', $file, '--json'], __DIR__.'/../../../bin');
        $process->run();

        $this->assertStringMatchesFormat(file_get_contents($jsonOutputPath), $process->getOutput());
        $this->assertStringMatchesFormat(file_get_contents($jsonErrorOutputPath), $process->getErrorOutput());
    }

    public function testErrorInRealWorld()
    {
        $finder = new PhpExecutableFinder();
        $process = new Process([$finder->find(), 'blackfire-player.php', 'validate', '--no-ansi', '../Player/Tests/fixtures-validate/scenario.json', '--json'], __DIR__.'/../../../bin');
        $process->run();

        $expectedOutput = '{
    "message": "The scenarios are not valid.",
    "success": false,
    "errors": [
        "Cannot load file \"../Player/Tests/fixtures-validate/scenario.json\" because it does not have the right extension. Expected \"bkf\", got \"json\"."
    ],
    "missing_variables": [],
    "code": 64
}
';

        $this->assertSame($expectedOutput, $process->getOutput());
    }

    /** @dataProvider providePlayerTests */
    public function testValidateStdIn($file, $outputPath, $errorOutputPath, $jsonOutputPath, $jsonErrorOutputPath)
    {
        $finder = new PhpExecutableFinder();
        $process = new Process([$finder->find(), 'blackfire-player.php', 'validate', '--no-ansi', '--json'], __DIR__.'/../../../bin');
        $process->setInput(file_get_contents($file));
        $process->run();

        $this->assertTrue($process->isSuccessful());
        $this->assertStringMatchesFormat(file_get_contents($jsonOutputPath), $process->getOutput());
        $this->assertStringMatchesFormat(file_get_contents($jsonErrorOutputPath), $process->getErrorOutput());

        $process = new Process([$finder->find(), 'blackfire-player.php', 'validate', '--no-ansi'], __DIR__.'/../../../bin');
        $process->setInput(file_get_contents($file));
        $process->run();

        $this->assertStringMatchesFormat(file_get_contents($outputPath), $process->getOutput());
        $this->assertStringMatchesFormat(file_get_contents($errorOutputPath), $process->getErrorOutput());
    }

    public function testErrorStdIn()
    {
        $finder = new PhpExecutableFinder();
        $process = new Process([$finder->find(), 'blackfire-player.php', 'validate', '--no-ansi', '--json'], __DIR__.'/../../../bin');
        $process->setInput('papilou!');
        $process->run();

        $expectedOutput = '{
    "message": "The scenarios are not valid.",
    "success": false,
    "errors": [
        "Unable to parse \"papilou!\" at line 1."
    ],
    "missing_variables": [],
    "code": 64
}
';

        $this->assertSame($expectedOutput, $process->getOutput());
    }
}
