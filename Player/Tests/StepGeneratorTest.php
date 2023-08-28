<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests;

use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ExpressionLanguage\Provider;
use Blackfire\Player\ExpressionLanguage\Provider as LanguageProvider;
use Blackfire\Player\Http\CookieJar;
use Blackfire\Player\Parser;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\StepProcessor\BlockStepProcessor;
use Blackfire\Player\StepProcessor\ChainProcessor;
use Blackfire\Player\StepProcessor\ClickStepProcessor;
use Blackfire\Player\StepProcessor\ConditionStepProcessor;
use Blackfire\Player\StepProcessor\ExpressionEvaluator;
use Blackfire\Player\StepProcessor\FollowStepProcessor;
use Blackfire\Player\StepProcessor\LoopStepProcessor;
use Blackfire\Player\StepProcessor\ReloadStepProcessor;
use Blackfire\Player\StepProcessor\RequestStepProcessor;
use Blackfire\Player\StepProcessor\StepContextFactory;
use Blackfire\Player\StepProcessor\SubmitStepProcessor;
use Blackfire\Player\StepProcessor\UriResolver;
use Blackfire\Player\StepProcessor\VariablesEvaluator;
use Blackfire\Player\StepProcessor\VisitStepProcessor;
use Blackfire\Player\StepProcessor\WhileStepProcessor;
use Blackfire\Player\Tests\Constraint\ArraySubset;
use Blackfire\Player\VariableResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class StepGeneratorTest extends TestCase
{
    /** @dataProvider provideScenarios */
    public function testGetIteratorIteratesOverAScenenarioWithLoops(string $rawScenarioSet, array $expectations)
    {
        $parser = new Parser(new ExpressionLanguage(null, [new LanguageProvider()]));
        $scenarioSet = $parser->parse($rawScenarioSet);

        $scenarioContext = new ScenarioContext('"foo"', $scenarioSet);
        $language = new ExpressionLanguage(null, [new Provider()]);
        $variableResolver = new VariableResolver($language);
        $variablesEvaluator = new VariablesEvaluator($language);
        $stepContextFactory = new StepContextFactory($variableResolver);

        $httpClient = new MockHttpClient(static fn () => new MockResponse('<a class="btn" href="/test">link</a>', ['response_headers' => ['Content-Type' => 'text/html']]));
        $expressionEvaluator = new ExpressionEvaluator($language);
        $uriResolver = new UriResolver();
        $generator = new ChainProcessor([
            new VisitStepProcessor($expressionEvaluator, $uriResolver),
            new ClickStepProcessor($expressionEvaluator, $uriResolver),
            new SubmitStepProcessor($expressionEvaluator, $uriResolver),
            new FollowStepProcessor($uriResolver),
            new ReloadStepProcessor(),
            new RequestStepProcessor($httpClient, new CookieJar()),

            new LoopStepProcessor($expressionEvaluator),
            new WhileStepProcessor($expressionEvaluator),
            new ConditionStepProcessor($expressionEvaluator),
            new BlockStepProcessor(),
        ]);

        $scenario = $scenarioSet->getScenarios()[0];

        $count = 0;
        $handleStep = function (AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext) use (&$handleStep, $stepContextFactory, $expectations, &$count, $generator, $variablesEvaluator) {
            if (!isset($expectations[$count])) {
                $this->fail('too many iterations');
            }

            [$expectedStepName, $expectedInputValues, $expectedOutputValues] = $expectations[$count];
            ++$count;

            $this->assertEquals($expectedStepName, $step->getName(), sprintf('Names does not match (iteration %d)', $count));

            self::assertArraySubset($expectedInputValues, $scenarioContext->getVariableValues($stepContext, true), message: sprintf('Failed asserting the input value bag (iteration %d)', $count));

            foreach ($generator->process($step, $stepContext, $scenarioContext) as $childStep) {
                $handleStep(
                    $childStep,
                    $stepContextFactory->createStepContext($childStep, $stepContext, $scenarioContext),
                    $scenarioContext,
                );
            }

            // mimic the variables resolution performed in PlayerNext main loop
            $variablesEvaluator->evaluate($step, $stepContext, $scenarioContext);

            self::assertArraySubset($expectedOutputValues, $scenarioContext->getVariableValues($stepContext, true), message: sprintf('Failed asserting the output value bag (iteration %d)', $count));
        };

        $handleStep(
            $scenario,
            $stepContextFactory->createStepContext($scenario, new StepContext(), $scenarioContext),
            $scenarioContext,
        );

        $this->assertEquals(\count($expectations), $count);
    }

    public function provideScenarios()
    {
        yield [<<<'BKF'
scenario
    name 'My scenario'
    endpoint "http://localhost"
    set var "A"

    with i in ['X', 'Y']
        name 'loop i'
        set var "B"

        visit url('/app?i=' ~ i)
            name 'visit i'
            set var "C"

    with j in ['X', 'Y']
        name 'loop j'
        set var "D"

        visit url('/app?j=' ~ j)
            name 'visit j'
            set var "E"
BKF,
            [
                ["'My scenario'", [], []],
                ["'loop i'", [], []],
                ["'visit i'", ['i' => 'X', 'var' => 'B'], ['i' => 'X', 'var' => 'C']], // Loop's var take precedence of scenario's var
                [null, [], []],
                ["'visit i'", ['i' => 'Y', 'var' => 'C'], ['i' => 'Y', 'var' => 'C']], // Visit's var override loops and scenario var
                [null, [], []],

                ["'loop j'", [], []],
                ["'visit j'", ['j' => 'X', 'var' => 'C'], ['j' => 'X', 'var' => 'E']], // Previous Visit is kept in memory and reused. Loop's var is ignored
                [null, [], []],
                ["'visit j'", ['j' => 'Y', 'var' => 'E'], ['j' => 'Y', 'var' => 'E']],
                [null, [], []],
            ],
        ];

        yield [<<<'BKF'
group visit_and_click
    name 'group'

    visit url('/page-with-clickable-button')
        expect status_code() == 200
        name 'visit a page with a clickable button'
    click css('.btn')
        expect status_code() == 200
        name 'click a clickable button'
    when "prod" == env
        name 'when env'
        visit url('/is-the-prod-down')
            expect status_code() == 200
            name 'check if the prod is down'

scenario
    name 'My scenario'
    endpoint "http://localhost"
    set i 1
    set env 'prod'
    while i < 4
        name 'while i'
        visit url('/app?i=' ~ i)
            set i i + 1
            set is_even i % 2 == 0
            # set env 'dev'
            expect status_code() == 200
            name 'visit app #' ~ i
            warmup false
            samples 3

    with slug in ['about', 'community', 'support']
        name 'with slug'
        visit url('/slug?slug=' ~ slug)
            expect status_code() == 200
            name 'visit slug #' ~ slug

    visit url('/not-in-a-loop')
        expect status_code() == 200
        name 'visit not a loop'
        warmup 4
        samples 10

    click css('.btn')
        expect status_code() == 200
        name 'click a button'

    include visit_and_click
BKF,
            [
                ["'My scenario'", [], []],
                ["'while i'", [], []],
                ["'visit app #' ~ i",     ['i' => 1],                                           ['i' => 2, 'is_even' => true]],
                [null, [], []],
                ["'visit app #' ~ i",     ['i' => 2, 'is_even' => true],                        ['i' => 3, 'is_even' => false]],
                [null, [], []],
                ["'visit app #' ~ i",     ['i' => 3, 'is_even' => false],                       ['i' => 4, 'is_even' => true]],
                [null, [], []],

                ["'with slug'", [], []],
                ["'visit slug #' ~ slug", ['i' => 4, 'is_even' => true, 'slug' => 'about'],     []],
                [null, [], []],
                ["'visit slug #' ~ slug", ['i' => 4, 'is_even' => true, 'slug' => 'community'], []],
                [null, [], []],
                ["'visit slug #' ~ slug", ['i' => 4, 'is_even' => true, 'slug' => 'support'],   []],
                [null, [], []],

                ["'visit not a loop'", [], []],
                [null, [], []],
                ["'click a button'", [], []],
                [null, [], []],

                ["'group'", [], []],
                ["'visit a page with a clickable button'", [], []],
                [null, [], []],
                ["'click a clickable button'", [], []],
                [null, [], []],
                ["'when env'", [], []],
                ["'check if the prod is down'", [], []],
                [null, [], []],
            ],
        ];
    }

    private static function assertArraySubset(iterable $subset, iterable $array, bool $checkForObjectIdentity = false, string $message = ''): void
    {
        $constraint = new ArraySubset($subset, $checkForObjectIdentity);

        static::assertThat($array, $constraint, $message);
    }
}
