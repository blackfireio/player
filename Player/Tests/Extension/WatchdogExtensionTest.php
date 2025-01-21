<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Extension;

use Blackfire\Player\Extension\WatchdogExtension;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\ConfigurableStep;
use Blackfire\Player\Step\FollowStep;
use Blackfire\Player\Step\Step;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\VisitStep;
use Blackfire\Player\Step\WhileStep;
use PHPUnit\Framework\TestCase;

class WatchdogExtensionTest extends TestCase
{
    public function testStepLimitResetWhenStepIsExpectedOne(): void
    {
        $extension = new WatchdogExtension(1, 10);

        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());

        $step = new ConfigurableStep();
        $stepUuid = $step->getUuid();

        $step->next(new ConfigurableStep());
        $stepContext = new StepContext();

        $extension->beforeStep($step, $stepContext, $scenarioContext);
        $extension->afterStep($step, $stepContext, $scenarioContext);

        $this->assertEquals(1, $scenarioContext->getExtraValue('_watchdog_step_counter:'.$stepUuid));
        $this->assertEquals(1, $scenarioContext->getExtraValue('_watchdog_total_counter'));

        $next = $step->getNext();
        $extension->beforeStep($next, $stepContext, $scenarioContext);
        $extension->afterStep($next, $stepContext, $scenarioContext);

        $this->assertEquals(1, $scenarioContext->getExtraValue('_watchdog_step_counter:'.$stepUuid));
        $this->assertEquals(2, $scenarioContext->getExtraValue('_watchdog_total_counter'));
    }

    public function testStepLimitExceededThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Number of requests per step exceeded ("1")');

        $extension = new WatchdogExtension(1, 10);

        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());

        $step = new ConfigurableStep();
        $stepUuid = $step->getUuid();
        $step->next(new ConfigurableStep());
        $stepContext = new StepContext();

        $extension->beforeStep($step, $stepContext, $scenarioContext);
        $extension->afterStep($step, $stepContext, $scenarioContext);

        $extension->beforeStep($step, $stepContext, $scenarioContext);
        $extension->afterStep($step, $stepContext, $scenarioContext);

        $this->assertEquals(2, $scenarioContext->getExtraValue('_watchdog_step_counter:'.$stepUuid));
        $this->assertEquals(1, $scenarioContext->getExtraValue('_watchdog_total_counter'));

        $next = new ConfigurableStep();
        $extension->beforeStep($next, $stepContext, $scenarioContext);
    }

    public function testTotalLimitExceededThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Number of requests per scenario exceeded ("1")');

        $extension = new WatchdogExtension(1, 1);

        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());

        $step = new ConfigurableStep();
        $stepUuid = $step->getUuid();
        $step->next(new ConfigurableStep());
        $stepContext = new StepContext();

        $extension->beforeStep($step, $stepContext, $scenarioContext);
        $extension->afterStep($step, $stepContext, $scenarioContext);

        $this->assertEquals(1, $scenarioContext->getExtraValue('_watchdog_step_counter:'.$stepUuid));
        $this->assertEquals(1, $scenarioContext->getExtraValue('_watchdog_total_counter'));

        $next = $step->getNext();
        $extension->beforeStep($next, $stepContext, $scenarioContext);
    }

    public function testStepWithInitiatorIncrementsInitiatorStepCounter(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Number of requests per step exceeded ("6")');

        $extension = new WatchdogExtension(6, 10);

        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());

        $step = new VisitStep('/login');
        $stepUuid = $step->getUuid();
        $step->next(new ConfigurableStep());

        $stepContext = new StepContext();

        $extension->beforeStep($step, $stepContext, $scenarioContext);
        $extension->afterStep($step, $stepContext, $scenarioContext);

        for ($i = 0; $i < 5; ++$i) {
            $this->doInitiateStep($step, $extension, $scenarioContext);
        }

        $this->assertEquals(6, $scenarioContext->getExtraValue('_watchdog_step_counter:'.$stepUuid));
        $this->assertEquals(6, $scenarioContext->getExtraValue('_watchdog_total_counter'));

        // once again to throw the exception
        $this->doInitiateStep($step, $extension, $scenarioContext);
    }

    public function testIgnoresBlockStep(): void
    {
        $extension = new WatchdogExtension(10, 40);

        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());

        $step = new WhileStep('"i < 4');
        $blockStepUuid = $step->getUuid();
        $visitSteps = [];

        for ($i = 0; $i < 4; ++$i) {
            $step = new VisitStep('/login');
            $visitSteps[] = $step->getUuid();
            $stepContext = new StepContext();
            $extension->beforeStep($step, $stepContext, $scenarioContext);
            $extension->afterStep($step, $stepContext, $scenarioContext);

            for ($j = 0; $j < 4; ++$j) {
                $this->doInitiateStep($step, $extension, $scenarioContext);
            }
        }

        $this->assertNull($scenarioContext->getExtraValue('_watchdog_step_counter:'.$blockStepUuid));
        foreach ($visitSteps as $visitStepUuid) {
            $this->assertEquals(5, $scenarioContext->getExtraValue('_watchdog_step_counter:'.$visitStepUuid));
        }

        $this->assertEquals(20, $scenarioContext->getExtraValue('_watchdog_total_counter'));
    }

    private function doInitiateStep(Step $step, WatchdogExtension $extension, ScenarioContext $scenarioContext): void
    {
        $generatedStep = new FollowStep(null, null, $step);
        $generatedStepContext = new StepContext();

        $extension->beforeStep($generatedStep, $generatedStepContext, $scenarioContext);
        $extension->afterStep($generatedStep, $generatedStepContext, $scenarioContext);
    }
}
