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
use Blackfire\Player\Step\FollowStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\SubmitStep;
use Blackfire\Player\Step\VisitStep;
use Blackfire\Player\StepProcessor\ChainProcessor;
use Blackfire\Player\StepProcessor\ExpressionEvaluator;
use Blackfire\Player\StepProcessor\FollowStepProcessor;
use Blackfire\Player\StepProcessor\RequestStepProcessor;
use Blackfire\Player\StepProcessor\StepProcessorInterface;
use Blackfire\Player\StepProcessor\SubmitStepProcessor;
use Blackfire\Player\StepProcessor\UriResolver;
use Blackfire\Player\StepProcessor\VisitStepProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class FollowStepProcessorTest extends TestCase
{
    public function testProcess(): void
    {
        $processor = $this->createProcessor([
            new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => '/follow']]),
            function (string $method, string $url, array $options = []): MockResponse {
                $this->assertSame('GET', $method);
                $this->assertSame('http://localhost/follow', $url);

                return new MockResponse('', ['http_code' => 201]);
            },
        ]);
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $visitStepContext = new StepContext();

        $nextSteps = [...$processor->process(new VisitStep('"http://localhost"'), $visitStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $visitStepContext, $scenarioContext)];

        $followStepContext = new StepContext();

        $nextSteps = [...$processor->process(new FollowStep(), $followStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $followStepContext, $scenarioContext)];

        $this->assertSame(201, $scenarioContext->getLastResponse()->statusCode);
    }

    public function testProcessWithBody(): void
    {
        $processor = $this->createProcessor([
            new MockResponse('<form action="/form" method="POST"><input type="text" name="field" value="val"></form>', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'text/html']]),
            new MockResponse('', ['http_code' => 307, 'response_headers' => ['Location' => '/follow']]),
            function (string $method, string $url, array $options = []): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('content-type: application/x-www-form-urlencoded', $options['normalized_headers']['content-type'][0]);
                $this->assertSame('http://localhost/follow', $url);
                $this->assertSame('field=val', $options['body']);

                return new MockResponse('', ['http_code' => 201]);
            },
        ]);
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $visitStepContext = new StepContext();

        $nextSteps = [...$processor->process(new VisitStep('"http://localhost"'), $visitStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $visitStepContext, $scenarioContext)];

        $submitStepContext = new StepContext();

        $nextSteps = [...$processor->process(new SubmitStep('css("form")'), $submitStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $submitStepContext, $scenarioContext)];

        $followStepContext = new StepContext();

        $nextSteps = [...$processor->process(new FollowStep(), $followStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $followStepContext, $scenarioContext)];

        $this->assertSame(201, $scenarioContext->getLastResponse()->statusCode);
    }

    public function testProcessWithFile(): void
    {
        $processor = $this->createProcessor([
            new MockResponse('<form action="/form" method="POST"><input type="text" name="field" value="val"><input type="file" name="image"></form>', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'text/html']]),
            new MockResponse('', ['http_code' => 307, 'response_headers' => ['Location' => '/follow']]),
            function (string $method, string $url, array $options = []): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('http://localhost/follow', $url);
                $this->assertStringStartsWith('content-type: multipart/form-data; boundary=', $options['normalized_headers']['content-type'][0]);
                $content = '';
                while ($l = $options['body']()) {
                    $content .= $l;
                }

                $this->assertStringContainsString('step val', $content);
                $this->assertStringContainsString('This is my bio', $content);

                return new MockResponse('', ['http_code' => 201]);
            },
        ]);
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $visitStepContext = new StepContext();

        $nextSteps = [...$processor->process(new VisitStep('"http://localhost"'), $visitStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $visitStepContext, $scenarioContext)];

        $step = new SubmitStep('css("form")', __FILE__);
        $step->param('field', '"step val"');
        $step->param('image', 'file("../fixtures-run/simple/bio.txt", "my bio")');
        $stepContext = new StepContext();
        $stepContext->update($step, []);

        $nextSteps = [...$processor->process($step, $stepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $stepContext, $scenarioContext)];

        $thirdStepContext = new StepContext();
        $nextSteps = [...$processor->process(new FollowStep(), $thirdStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $thirdStepContext, $scenarioContext)];

        $this->assertSame(201, $scenarioContext->getLastResponse()->statusCode);
    }

    public function testProcessResetBlackfireQuery(): void
    {
        $processor = $this->createProcessor([
            function (string $method, string $url, array $options = []): MockResponse {
                $this->assertSame('x-blackfire-query: foo', $options['normalized_headers']['x-blackfire-query'][0]);
                $this->assertSame('x-blackfire-profile-uuid: bar', $options['normalized_headers']['x-blackfire-profile-uuid'][0]);

                return new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => '/follow']]);
            },
            function (string $method, string $url, array $options = []): MockResponse {
                $this->assertArrayNotHasKey('x-blackfire-query', $options['normalized_headers']);
                $this->assertArrayNotHasKey('x-blackfire-profile-uuid', $options['normalized_headers']);

                return new MockResponse('', ['http_code' => 201]);
            },
        ]);
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $step = new VisitStep('"http://localhost"');
        $step
            ->header('"x-blackfire-query: foo"')
            ->header('"x-blackfire-profile-uuid: bar"')
        ;
        $stepContext = new StepContext();
        $stepContext->update($step, []);

        $nextSteps = [...$processor->process($step, $stepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $stepContext, $scenarioContext)];

        $this->assertSame('foo', $scenarioContext->getLastResponse()->request->headers['x-blackfire-query'][0] ?? null);
        $this->assertSame('bar', $scenarioContext->getLastResponse()->request->headers['x-blackfire-profile-uuid'][0] ?? null);

        $followStepContext = new StepContext();
        $nextSteps = [...$processor->process(new FollowStep(), $followStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $followStepContext, $scenarioContext)];

        $this->assertNull($scenarioContext->getLastResponse()->request->headers['x-blackfire-query'][0] ?? null);
    }

    public function testProcessFilterCredentials(): void
    {
        $processor = $this->createProcessor([
            new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => '/follow']]),
            function (string $method, string $url, array $options = []): MockResponse {
                $this->assertSame('referer: http://localhost', $options['normalized_headers']['referer'][0]);

                return new MockResponse('', ['http_code' => 201]);
            },
        ]);
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $visitStepContext = new StepContext();

        $nextSteps = [...$processor->process(new VisitStep('"http://user:pass@localhost"'), $visitStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $visitStepContext, $scenarioContext)];

        $followStepContext = new StepContext();

        $nextSteps = [...$processor->process(new FollowStep(), $followStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $followStepContext, $scenarioContext)];

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
            new FollowStepProcessor($uriResolver),
            new SubmitStepProcessor($expressionEvaluator, $uriResolver),
            new RequestStepProcessor($httpClient, new CookieJar()),
        ]);
    }
}
