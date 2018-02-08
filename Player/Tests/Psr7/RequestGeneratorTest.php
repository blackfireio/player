<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Psr7;

use Blackfire\Player\Context;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\Psr7\RequestGenerator;
use Blackfire\Player\Psr7\StepConverterInterface;
use Blackfire\Player\Scenario;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\BlockStep;
use Blackfire\Player\Step\ClickStep;
use Blackfire\Player\Step\ConditionStep;
use Blackfire\Player\Step\EmptyStep;
use Blackfire\Player\Step\LoopStep;
use Blackfire\Player\Step\ReloadStep;
use Blackfire\Player\Step\VisitStep;
use Blackfire\Player\Step\WhileStep;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RequestGeneratorTest extends TestCase
{
    private $language;
    private $stepConverter;
    private $context;

    public function setUp()
    {
        $this->context = new Context('Test');
        $this->language = new ExpressionLanguage();
        $this->stepConverter = $this->createMock(StepConverterInterface::class);
        $this->stepConverter->method('createRequest')->willReturn($this->createMock(RequestInterface::class));
    }

    /**
     * @dataProvider stepsProvider
     */
    public function testRequestGeneration(AbstractStep $step, array $expected)
    {
        $requestGen = new RequestGenerator($this->language, $this->stepConverter, $step, $this->context);
        $generator = $requestGen->getIterator();

        $this->context->getValueBag()->set('index', 0);
        $res = [];
        do {
            $step = $generator->key();
            $request = $generator->current();

            // empty iterator
            if (null === $request) {
                break;
            }

            $this->context->getValueBag()->set('index', $this->context->getValueBag()->get('index') + 1);

            $res[] = \get_class($step);
        } while ($generator->send([$request, $this->createMock(ResponseInterface::class)]));

        $this->assertEquals($expected, $res);
    }

    public function stepsProvider()
    {
        yield 'VisitStep' => [
            new VisitStep('/1'),
            [
                VisitStep::class,
            ],
        ];

        $scenario = new Scenario('Test BlockStep');
        $step1 = new VisitStep('/1');
        $step2 = new VisitStep('/2');
        $step1->next($step2);
        $scenario->setBlockStep($step1);

        yield 'BlockStep' => [
            $scenario,
            [
                VisitStep::class,
                VisitStep::class,
            ],
        ];

        $scenario = new Scenario('Test LoopStep');
        $visit = new VisitStep('/');
        $loop = new LoopStep('[1, 2, 3, 4]', 'key', 'value');
        $loop->setLoopStep($visit);
        $scenario->setBlockStep($loop);

        yield 'LoopStep' => [
            $scenario,
            [
                VisitStep::class,
                VisitStep::class,
                VisitStep::class,
                VisitStep::class,
            ],
        ];

        $scenario = new Scenario('Test WhileStep');
        $visit = new VisitStep('/');
        $while = new WhileStep('index < 2');
        $while->setWhileStep($visit);
        $scenario->setBlockStep($while);

        yield 'WhileStep' => [
            $scenario,
            [
                VisitStep::class,
                VisitStep::class,
            ],
        ];

        $scenario = new Scenario('Test ConditionStep');
        $condition = new ConditionStep('1 == 2');
        $condition->setIfStep(new ClickStep(''));
        $condition->setElseStep(new ReloadStep());
        $scenario->setBlockStep($condition);

        yield 'ConditionStep' => [
            $scenario,
            [
                ReloadStep::class,
            ],
        ];

        $scenario = new Scenario('Test EmptyStep');
        $scenario->setBlockStep(new EmptyStep());

        yield 'EmptyStep' => [
            $scenario,
            [],
        ];
    }

    /**
     * @dataProvider sendToGeneratorProvider
     */
    public function testTheGeneratorShouldReceiveARequestAndAResponse($shouldThrowException, $sendToGenerator)
    {
        if ($shouldThrowException) {
            $this->expectException(\LogicException::class);
        }

        $requestGen = new RequestGenerator($this->language, $this->stepConverter, new VisitStep(''), $this->context);
        $generator = $requestGen->getIterator();
        $generator->key();

        $generator->send($sendToGenerator);
    }

    public function sendToGeneratorProvider()
    {
        // Valid
        yield [false, [$this->createMock(RequestInterface::class), $this->createMock(ResponseInterface::class)]];

        // Invalid
        yield [true, null];
        yield [true, ['zz', $this->createMock(ResponseInterface::class)]];
        yield [true, [$this->createMock(RequestInterface::class), 'invalid']];
        yield [true, [$this->createMock(ResponseInterface::class), $this->createMock(RequestInterface::class)]];
    }

    public function testBlockStepVariablesEvaluation()
    {
        $step = new BlockStep();
        $step->set('name', '"John"');
        $step->set('hello', '"Hello " ~ name ~ "!"');
        $step->setBlockStep(new VisitStep(''));

        $requestGen = new RequestGenerator($this->language, $this->stepConverter, $step, $this->context);
        $generator = $requestGen->getIterator();
        $step = $generator->key();

        $this->assertInstanceOf(VisitStep::class, $step);

        $this->assertEquals([
            'name' => 'John',
            'hello' => 'Hello John!',
        ], $this->context->getStepContext()->getVariables());
    }

    /**
     * @expectedException \Symfony\Component\ExpressionLanguage\SyntaxError
     */
    public function testBlockStepInvalidVariableThrowAnException()
    {
        $step = new BlockStep();
        $step->set('hello', '"Hello " ~ name ~ "!"');
        $step->setBlockStep(new VisitStep(''));

        $requestGen = new RequestGenerator($this->language, $this->stepConverter, $step, $this->context);
        $generator = $requestGen->getIterator();
        $generator->key();
    }
}
