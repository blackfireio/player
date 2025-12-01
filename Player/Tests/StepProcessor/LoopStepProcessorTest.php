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
use Blackfire\Player\Step\LoopStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\StepProcessor\ChainProcessor;
use Blackfire\Player\StepProcessor\ExpressionEvaluator;
use Blackfire\Player\StepProcessor\LoopStepProcessor;
use Blackfire\Player\StepProcessor\StepProcessorInterface;
use Blackfire\Player\Tests\Caster\ResetStepUuidDumpTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class LoopStepProcessorTest extends TestCase
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
        $stepContext = new StepContext();
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());

        $i = 0;
        $blockStep = new BlockStep();
        $step = new LoopStep('[1, 2]', 'key', 'value');
        $step->setLoopStep($blockStep);

        foreach ($processor->process($step, $stepContext, $scenarioContext) as $child) {
            $this->assertNotSame($blockStep, $child);

            $expectedBlockStepDump = $this->getDump($blockStep);
            $this->assertDumpEquals($expectedBlockStepDump, $child);

            if (0 === $i++) {
                $this->assertSame(0, $stepContext->getVariables()['key']);
                $this->assertSame(1, $stepContext->getVariables()['value']);
            } else {
                $this->assertSame(1, $stepContext->getVariables()['key']);
                $this->assertSame(2, $stepContext->getVariables()['value']);
            }
        }
    }

    private function createProcessor(): StepProcessorInterface
    {
        $language = new ExpressionLanguage(null, [new Provider()]);
        $expressionEvaluator = new ExpressionEvaluator($language);

        return new ChainProcessor([
            new LoopStepProcessor($expressionEvaluator),
        ]);
    }
}
