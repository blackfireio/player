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

use Blackfire\Player\Http\CookieJar;
use Blackfire\Player\Http\Request;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\RequestStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\VisitStep;
use Blackfire\Player\StepProcessor\ChainProcessor;
use Blackfire\Player\StepProcessor\RequestStepProcessor;
use Blackfire\Player\StepProcessor\StepProcessorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RequestStepProcessorTest extends TestCase
{
    public function testProcess(): void
    {
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $processor = $this->createProcessor([
            new MockResponse('', ['http_code' => 201]),
        ]);

        $stepContext = new StepContext();

        $nextSteps = [...$processor->process(new RequestStep(new Request('GET', 'http://localhost'), new VisitStep('/')), $stepContext, $scenarioContext)];
        $this->assertSame([], $nextSteps);

        $this->assertSame(201, $scenarioContext->getLastResponse()->statusCode);
    }

    private function createProcessor(iterable $mockHttpResponses): StepProcessorInterface
    {
        $httpClient = new MockHttpClient($mockHttpResponses);

        return new ChainProcessor([
            new RequestStepProcessor($httpClient, new CookieJar()),
        ]);
    }
}
