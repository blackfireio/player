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
use Blackfire\Player\Http\CookieJar;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\ClickStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\VisitStep;
use Blackfire\Player\StepProcessor\ChainProcessor;
use Blackfire\Player\StepProcessor\ClickStepProcessor;
use Blackfire\Player\StepProcessor\ExpressionEvaluator;
use Blackfire\Player\StepProcessor\RequestStepProcessor;
use Blackfire\Player\StepProcessor\StepProcessorInterface;
use Blackfire\Player\StepProcessor\UriResolver;
use Blackfire\Player\StepProcessor\VisitStepProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ClickStepProcessorTest extends TestCase
{
    public function testProcess(): void
    {
        $processor = $this->createProcessor([
            new MockResponse(
                '<a href="/link">click me</a>',
                ['http_code' => 200, 'response_headers' => ['Content-Type' => 'text/html']]
            ),
            function (string $method, string $url, array $options = []): MockResponse {
                $this->assertSame('http://localhost/link', $url);

                return new MockResponse('', ['http_code' => 201]);
            },
        ]);
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $firstStepContext = new StepContext();

        $nextSteps = [...$processor->process(new VisitStep('"http://localhost"'), $firstStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $firstStepContext, $scenarioContext)];

        $clickStepContext = new StepContext();

        $nextSteps = [...$processor->process(new ClickStep('link("click me")'), $clickStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $clickStepContext, $scenarioContext)];

        $this->assertSame(201, $scenarioContext->getLastResponse()->statusCode);
    }

    private function createProcessor(iterable $mockHttpResponses): StepProcessorInterface
    {
        $httpClient = new MockHttpClient($mockHttpResponses);
        $language = new ExpressionLanguage(null, [new Provider()]);
        $expressionEvaluator = new ExpressionEvaluator($language);
        $uriResolver = new UriResolver();

        return new ChainProcessor([
            new VisitStepProcessor($expressionEvaluator, $uriResolver),
            new ClickStepProcessor($expressionEvaluator, $uriResolver),
            new RequestStepProcessor($httpClient, new CookieJar()),
        ]);
    }
}
