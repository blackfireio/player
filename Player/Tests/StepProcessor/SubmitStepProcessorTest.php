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
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\SubmitStep;
use Blackfire\Player\Step\VisitStep;
use Blackfire\Player\StepProcessor\ChainProcessor;
use Blackfire\Player\StepProcessor\ExpressionEvaluator;
use Blackfire\Player\StepProcessor\RequestStepProcessor;
use Blackfire\Player\StepProcessor\StepProcessorInterface;
use Blackfire\Player\StepProcessor\SubmitStepProcessor;
use Blackfire\Player\StepProcessor\UriResolver;
use Blackfire\Player\StepProcessor\VisitStepProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SubmitStepProcessorTest extends TestCase
{
    public function testProcess(): void
    {
        $processor = $this->createProcessor([
            new MockResponse('<form action="/form" method="POST"><input type="text" name="field" value="val"></form>', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'text/html']]),
            function (string $method, string $url, array $options = []): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('content-type: application/x-www-form-urlencoded', $options['normalized_headers']['content-type'][0]);
                $this->assertSame('http://localhost/form', $url);
                $this->assertSame('field=val', $options['body']);

                return new MockResponse('', ['http_code' => 201]);
            },
        ]);
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $firstStepContext = new StepContext();

        $nextSteps = [...$processor->process(new VisitStep('"http://localhost"'), $firstStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $firstStepContext, $scenarioContext)];

        $submitStepContext = new StepContext();

        $nextSteps = [...$processor->process(new SubmitStep('css("form")'), $submitStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $submitStepContext, $scenarioContext)];

        $this->assertSame(201, $scenarioContext->getLastResponse()->statusCode);
    }

    public function testProcessWithBody(): void
    {
        $processor = $this->createProcessor([
            new MockResponse('<form action="/form" method="POST"><input type="text" name="field" value="val"></form>', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'text/html']]),
            function (string $method, string $url, array $options = []): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('http://localhost/form', $url);
                $this->assertSame('content-type: ', $options['normalized_headers']['content-type'][0]); // step configuration takes precedence over real form
                $this->assertSame('test', $options['body']); // step configuration takes precedence over real form

                return new MockResponse('', ['http_code' => 201]);
            },
        ]);
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $firstStepContext = new StepContext();

        $nextSteps = [...$processor->process(new VisitStep('"http://localhost"'), $firstStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $firstStepContext, $scenarioContext)];

        $step = new SubmitStep('css("form")');
        $step->body('"test"');
        $submitStepContext = new StepContext();
        $submitStepContext->update($step, []);

        $nextSteps = [...$processor->process($step, $submitStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $submitStepContext, $scenarioContext)];

        $this->assertSame(201, $scenarioContext->getLastResponse()->statusCode);
    }

    public function testProcessWithParameters(): void
    {
        $processor = $this->createProcessor([
            new MockResponse('<form action="/form" method="POST"><input type="text" name="field" value="val"></form>', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'text/html']]),
            function (string $method, string $url, array $options = []): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('http://localhost/form', $url);
                $this->assertSame('content-type: application/x-www-form-urlencoded', $options['normalized_headers']['content-type'][0]);
                $this->assertSame('field=step+val', $options['body']); // value is replaced by step's param

                return new MockResponse('', ['http_code' => 201]);
            },
        ]);
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $visitStepContext = new StepContext();
        $nextSteps = [...$processor->process(new VisitStep('"http://localhost"'), $visitStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $visitStepContext, $scenarioContext)];

        $step = new SubmitStep('css("form")');
        $step->param('field', '"step val"');
        $stepContext = new StepContext();
        $stepContext->update($step, []);

        $nextSteps = [...$processor->process($step, $stepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $stepContext, $scenarioContext)];

        $this->assertSame(201, $scenarioContext->getLastResponse()->statusCode);
    }

    public function testProcessWithJson(): void
    {
        $processor = $this->createProcessor([
            new MockResponse('<form action="/form" method="POST"><input type="text" name="field" value="val"></form>', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'text/html']]),
            function (string $method, string $url, array $options = []): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('http://localhost/form', $url);
                $this->assertSame('content-type: application/json', $options['normalized_headers']['content-type'][0]);
                $this->assertSame('{"field":"step val"}', $options['body']); // value is replaced by step's param and JSON encoded

                return new MockResponse('', ['http_code' => 201]);
            },
        ]);
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $visitStepContext = new StepContext();
        $nextSteps = [...$processor->process(new VisitStep('"http://localhost"'), $visitStepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $visitStepContext, $scenarioContext)];

        $step = new SubmitStep('css("form")');
        $step->param('field', '"step val"');
        $step->json('true');
        $stepContext = new StepContext();
        $stepContext->update($step, []);

        $nextSteps = [...$processor->process($step, $stepContext, $scenarioContext)];
        $this->assertCount(1, $nextSteps);
        [...$processor->process($nextSteps[0], $stepContext, $scenarioContext)];

        $this->assertSame(201, $scenarioContext->getLastResponse()->statusCode);
    }

    public function testProcessWithFile(): void
    {
        $processor = $this->createProcessor([
            new MockResponse('<form action="/form" method="POST"><input type="text" name="field" value="val"><input type="file" name="image"></form>', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'text/html']]),
            function (string $method, string $url, array $options = []): MockResponse {
                $this->assertSame('POST', $method);
                $this->assertSame('http://localhost/form', $url);
                $this->assertStringStartsWith('content-type: multipart/form-data; boundary=', $options['normalized_headers']['content-type'][0]);
                $content = '';
                while ($l = $options['body']()) {
                    $content .= $l;
                }

                $lines = explode("\r\n", trim($content));
                unset($lines[0], $lines[6], $lines[12]); // boundary tokens
                $lines = array_values($lines);
                $lines[9] = trim($lines[9]); // swallow the `\n` of the content of the file

                $this->assertSame(explode("\n", trim(
                    'Content-Type: text/plain; charset=utf-8
Content-Transfer-Encoding: 8bit
Content-Disposition: form-data; name="field"

step val
Content-Type: text/plain
Content-Transfer-Encoding: 8bit
Content-Disposition: form-data; name="image"; filename="my bio"

This is my bio')), $lines);

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

        $this->assertSame(201, $scenarioContext->getLastResponse()->statusCode);

        // assert the content is rewindable
        $body = $scenarioContext->getLastResponse()->request->body;
        $this->assertStringContainsString('This is my bio', implode('', iterator_to_array($body())));
    }

    private function createProcessor(iterable $mockHttpResponses): StepProcessorInterface
    {
        $httpClient = new MockHttpClient($mockHttpResponses);
        $language = new ExpressionLanguage(null, [new Provider()]);
        $expressionEvaluator = new ExpressionEvaluator($language);
        $uriResolver = new UriResolver();

        return new ChainProcessor([
            new VisitStepProcessor($expressionEvaluator, $uriResolver),
            new SubmitStepProcessor($expressionEvaluator, $uriResolver),
            new RequestStepProcessor($httpClient, new CookieJar()),
        ]);
    }
}
