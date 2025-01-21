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
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\BlockStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\WhileStep;
use Blackfire\Player\StepProcessor\ChainProcessor;
use Blackfire\Player\StepProcessor\ExpressionEvaluator;
use Blackfire\Player\StepProcessor\StepProcessorInterface;
use Blackfire\Player\StepProcessor\WhileStepProcessor;
use Blackfire\Player\Tests\Caster\ResetStepUuidDumpTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class WhileStepProcessorTest extends TestCase
{
    use ResetStepUuidDumpTrait;
    use VarDumperTestTrait;

    protected function setUp(): void
    {
        $this->resetStepUuidOnDump();
    }

    public function testProcess(): void
    {
        $processor = $this->createProcessor();
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());

        $i = 1;
        $blockStep = new BlockStep();

        $stepContext = new StepContext();
        $stepContext->variable('i', $i);
        $step = new WhileStep('i < 3');
        $step->setWhileStep($blockStep);

        $entered = 0;
        foreach ($processor->process($step, $stepContext, $scenarioContext) as $child) {
            ++$entered;
            $scenarioContext->setVariableValue('i', ++$i);
            $expectedBlockStepDump = $this->getDump($blockStep);

            $this->assertDumpEquals($expectedBlockStepDump, $child);
        }

        $this->assertSame(2, $entered);
    }

    private function createProcessor(): StepProcessorInterface
    {
        $language = new ExpressionLanguage(null, [new Provider()]);
        $expressionEvaluator = new ExpressionEvaluator($language);

        return new ChainProcessor([
            new WhileStepProcessor($expressionEvaluator),
        ]);
    }
}
