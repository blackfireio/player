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
use Blackfire\Player\Step\RequestStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\VisitStep;
use Blackfire\Player\StepProcessor\ChainProcessor;
use Blackfire\Player\StepProcessor\ExpressionEvaluator;
use Blackfire\Player\StepProcessor\StepProcessorInterface;
use Blackfire\Player\StepProcessor\UriResolver;
use Blackfire\Player\StepProcessor\VisitStepProcessor;
use PHPUnit\Framework\TestCase;

class VisitStepProcessorTest extends TestCase
{
    public function testProcess()
    {
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $processor = $this->createProcessor();

        $stepContext = new StepContext();

        $nextSteps = [...$processor->process(new VisitStep('"http://localhost"'), $stepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        $nextStep = $nextSteps[0];
        $this->assertInstanceOf(RequestStep::class, $nextStep);
    }

    public function testProcessWithRawBody()
    {
        $processor = $this->createProcessor();
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());

        $step = new VisitStep('"http://localhost"');
        $step->body('"test"');
        $stepContext = new StepContext();
        $stepContext->update($step, []);

        $nextSteps = [...$processor->process($step, $stepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        $nextStep = $nextSteps[0];
        $this->assertInstanceOf(RequestStep::class, $nextStep);
        $this->assertSame('', $nextStep->getRequest()->headers['content-type'][0]);
    }

    public function testProcessWithFormBody()
    {
        $processor = $this->createProcessor();
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());

        $step = new VisitStep('"http://localhost"');
        $step->param('foo', '"bar"');
        $stepContext = new StepContext();
        $stepContext->update($step, []);

        $nextSteps = [...$processor->process($step, $stepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        $nextStep = $nextSteps[0];
        $this->assertInstanceOf(RequestStep::class, $nextStep);
        $this->assertSame(
            'application/x-www-form-urlencoded',
            $nextStep->getRequest()->headers['content-type'][0]
        );
        $this->assertSame(['foo' => 'bar'], $nextStep->getRequest()->body);
    }

    public function testProcessWithJsonBody()
    {
        $processor = $this->createProcessor();
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());

        $step = new VisitStep('"http://localhost"');
        $step->json('true');
        $step->param('foo', '"bar"');
        $stepContext = new StepContext();
        $stepContext->update($step, []);

        $nextSteps = [...$processor->process($step, $stepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        $nextStep = $nextSteps[0];
        $this->assertInstanceOf(RequestStep::class, $nextStep);
        $this->assertSame('application/json', $nextStep->getRequest()->headers['content-type'][0]);
        $this->assertSame(['foo' => 'bar'], $nextStep->getRequest()->body);
    }

    public function testProcessPreserveHeaders()
    {
        $processor = $this->createProcessor();
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());

        $step = new VisitStep('"http://localhost"');
        $step->header('"Authorization: Bearer foo:bar"');
        $stepContext = new StepContext();
        $stepContext->update($step, []);

        $nextSteps = [...$processor->process($step, $stepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        $nextStep = $nextSteps[0];
        $this->assertInstanceOf(RequestStep::class, $nextStep);
        $this->assertSame('Bearer foo:bar', $nextStep->getRequest()->headers['authorization'][0]);
    }

    private function createProcessor(): StepProcessorInterface
    {
        $language = new ExpressionLanguage(null, [new Provider()]);
        $expressionEvaluator = new ExpressionEvaluator($language);
        $uriResolver = new UriResolver();

        return new ChainProcessor([
            new VisitStepProcessor($expressionEvaluator, $uriResolver),
        ]);
    }
}
