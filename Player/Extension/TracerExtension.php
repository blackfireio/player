<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Extension;

use Blackfire\Player\Json;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\ScenarioResult;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\ScenarioSetResult;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\RequestStep;
use Blackfire\Player\Step\StepContext;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class TracerExtension implements ScenarioSetExtensionInterface, ScenarioExtensionInterface, StepExtensionInterface, ExceptionExtensionInterface
{
    private readonly string $dir;

    private string $currentDir = '/';
    private int $scenarioIndex = 0;
    private int $stepCount = 0;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly Filesystem $fs,
    ) {
        $this->dir = \sprintf('%s/blackfire-player-trace/%s/%s', sys_get_temp_dir(), date('y-m-d-H-m-s'), bin2hex(random_bytes(5)));
        $this->fs->remove($this->dir);
    }

    public function beforeScenarioSet(ScenarioSet $scenarios, int $concurrency): void
    {
        $this->stepCount = 0;
    }

    public function beforeScenario(Scenario $scenario, ScenarioContext $scenarioContext): void
    {
        if (null === $key = $scenario->getKey()) {
            $key = ++$this->scenarioIndex;
        }
        $this->currentDir = $this->dir.'/'.$key;
        $target = \sprintf('%s/scenario.txt', $this->currentDir);
        $this->fs->mkdir(\dirname($target));

        $this->fs->dumpFile($target, (string) $scenario);
    }

    public function beforeStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
        ++$this->stepCount;

        $target = $this->getDirectory();

        $this->fs->dumpFile($target.'/step.txt', (string) $step);
        if ($step instanceof RequestStep) {
            $this->fs->dumpFile($target.'/request.txt', $step->getRequest()->toString());
        }
    }

    public function afterStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
        $target = $this->getDirectory();

        $variables = $scenarioContext->getVariableValues($stepContext, true);
        unset($variables['_crawler'], $variables['_response']);

        $this->fs->dumpFile($target.'/variables.json', Json::encode($variables, \JSON_PRETTY_PRINT));
        if ($step instanceof RequestStep) {
            $this->fs->dumpFile($target.'/response.txt', $scenarioContext->getLastResponse()->toString());
        }
    }

    public function failStep(AbstractStep $step, \Throwable $exception): void
    {
        $target = $this->getDirectory();

        $this->fs->dumpFile($target.'/exception.txt', $exception->getMessage()."\n".$exception->getTraceAsString());
    }

    public function afterScenario(Scenario $scenario, ScenarioContext $scenarioContext, ScenarioResult $scenarioResult): void
    {
    }

    public function afterScenarioSet(ScenarioSet $scenarios, int $concurrency, ScenarioSetResult $scenarioSetResult): void
    {
        $this->output->writeln(\sprintf('<comment>Traces under %s</>', $this->dir));
    }

    private function getDirectory(): string
    {
        $target = \sprintf('%s/%d', $this->currentDir, $this->stepCount);
        $this->fs->mkdir($target);

        return $target;
    }
}
