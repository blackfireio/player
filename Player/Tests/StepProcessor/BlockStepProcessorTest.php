<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\StepProcessor;

use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ExpressionLanguage\Provider;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\BlockStep;
use Blackfire\Player\Step\GroupStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\VisitStep;
use Blackfire\Player\StepProcessor\BlockStepProcessor;
use Blackfire\Player\StepProcessor\ChainProcessor;
use Blackfire\Player\StepProcessor\ExpressionEvaluator;
use Blackfire\Player\StepProcessor\StepProcessorInterface;
use PHPUnit\Framework\TestCase;

class BlockStepProcessorTest extends TestCase
{
    public function testProcessBlock(): void
    {
        $processor = $this->createProcessor();
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $firstStepContext = new StepContext();

        $visitStep1 = new VisitStep('/');
        $visitStep2 = new VisitStep('/');

        $step = new BlockStep();
        $step->setBlockStep($visitStep1);
        $visitStep1->next($visitStep2);

        $nextSteps = [...$processor->process($step, $firstStepContext, $scenarioContext)];

        $this->assertSame([$visitStep1, $visitStep2], $nextSteps);
    }

    public function testProcessGroup(): void
    {
        $processor = $this->createProcessor();
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $firstStepContext = new StepContext();

        $visitStep1 = new VisitStep('/');
        $visitStep2 = new VisitStep('/');

        $step = new GroupStep('key');
        $step->setBlockStep($visitStep1);
        $visitStep1->next($visitStep2);

        $nextSteps = [...$processor->process($step, $firstStepContext, $scenarioContext)];

        $this->assertSame([$visitStep1, $visitStep2], $nextSteps);
    }

    public function testProcessScenario(): void
    {
        $processor = $this->createProcessor();
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $firstStepContext = new StepContext();

        $visitStep1 = new VisitStep('/');
        $visitStep2 = new VisitStep('/');

        $step = new Scenario('key');
        $step->setBlockStep($visitStep1);
        $visitStep1->next($visitStep2);

        $nextSteps = [...$processor->process($step, $firstStepContext, $scenarioContext)];

        $this->assertSame([$visitStep1, $visitStep2], $nextSteps);
    }

    private function createProcessor(): StepProcessorInterface
    {
        $language = new ExpressionLanguage(null, [new Provider()]);
        new ExpressionEvaluator($language);

        return new ChainProcessor([
            new BlockStepProcessor(),
        ]);
    }
}
