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

namespace Blackfire\Player\Tests\StepProcessor;

use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ExpressionLanguage\Provider;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\BlockStep;
use Blackfire\Player\Step\ConditionStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\StepProcessor\ChainProcessor;
use Blackfire\Player\StepProcessor\ConditionStepProcessor;
use Blackfire\Player\StepProcessor\ExpressionEvaluator;
use Blackfire\Player\StepProcessor\StepProcessorInterface;
use Blackfire\Player\Tests\Caster\ResetStepUuidDumpTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class ConditionStepProcessorTest extends TestCase
{
    use ResetStepUuidDumpTrait;
    use VarDumperTestTrait;

    protected function setUp(): void
    {
        $this->resetStepUuidOnDump();
    }

    public function testProcessTrue(): void
    {
        $processor = $this->createProcessor();
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $firstStepContext = new StepContext();

        $blockStep = new BlockStep();
        $firstStepContext->variable('i', 1);
        $step = new ConditionStep('i == 1');
        $step->setIfStep($blockStep);

        $nextSteps = [...$processor->process($step, $firstStepContext, $scenarioContext)];

        $this->assertNotSame([$blockStep], $nextSteps);

        $expectedBlockStepDump = $this->getDump([$blockStep]);

        $this->assertDumpEquals($expectedBlockStepDump, $nextSteps);
    }

    public function testProcessFalse(): void
    {
        $processor = $this->createProcessor();
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $firstStepContext = new StepContext();

        $blockStep = new BlockStep();
        $firstStepContext->variable('i', 2);
        $step = new ConditionStep('i == 1');
        $step->setIfStep($blockStep);

        $nextSteps = [...$processor->process($step, $firstStepContext, $scenarioContext)];

        $this->assertSame([], $nextSteps);
    }

    private function createProcessor(): StepProcessorInterface
    {
        $language = new ExpressionLanguage(null, [new Provider()]);
        $expressionEvaluator = new ExpressionEvaluator($language);

        return new ChainProcessor([
            new ConditionStepProcessor($expressionEvaluator),
        ]);
    }
}
