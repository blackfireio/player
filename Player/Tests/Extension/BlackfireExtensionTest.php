<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Player\Tests;

use Blackfire\Client;
use Blackfire\ClientConfiguration;
use Blackfire\Player\Context;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\Extension\BlackfireExtension;
use Blackfire\Player\Step\ConfigurableStep;
use Blackfire\Player\Step\ReloadStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Profile\Request as ProfileRequest;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Console\Output\NullOutput;

class BlackfireExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider stepsProvider
     */
    public function testProfileOrWarmup(ConfigurableStep $step, Request $request, $shoudProfile, $shouldWarmup)
    {
        $extension = new BlackfireExtension(new ExpressionLanguage(), 'My env', new NullOutput(), $this->createBlackfireClient());
        $request = $extension->enterStep($step, $request, $this->createContext($step));

        if ($shoudProfile) {
            $this->assertEquals('1234', $request->getHeaderLine('X-Blackfire-Query'));
            $this->assertEquals('1111-2222-3333-4444', $request->getHeaderLine('X-Blackfire-Profile-Uuid'));
        } else {
            $this->assertFalse($request->hasHeader('X-Blackfire-Query'));
            $this->assertFalse($request->hasHeader('X-Blackfire-Profile-Uuid'));
        }

        if ($shouldWarmup) {
            $this->assertFalse($request->hasHeader('X-Blackfire-Query'));
            $this->assertFalse($request->hasHeader('X-Blackfire-Profile-Uuid'));

            $this->assertContains('Warmup', $step->getName());

            $second = $step->getNext();
            $this->assertInstanceOf(ReloadStep::class, $second);
            $this->assertEquals('false', $second->getBlackfire());

            $third = $second->getNext();
            $this->assertInstanceOf(ReloadStep::class, $third);
            $this->assertEquals('false', $third->getBlackfire());

            $real = $third->getNext();
            $this->assertInstanceOf(ReloadStep::class, $real);
            $this->assertEquals('true', $real->getBlackfire());
            $this->assertNull($real->getNext());
        } else {
            $this->assertNotContains('Warmup', $step->getName());

            $this->assertNull($step->getNext());
        }
    }

    public function stepsProvider()
    {
        // Blackfire disabled by default

        yield [
            (new ConfigurableStep())->name('"Step name"'),
            new Request('GET', '/'),
            false,
            false,
        ];

        // Blackfire enabled, Warmup disabled by default

        $step = (new ConfigurableStep())
            ->name('"Step name"')
            ->blackfire('true')
        ;

        yield [
            $step,
            new Request('GET', '/'),
            true,
            false,
        ];

        // Blackfire enabled, Warmup disabled by default + samples

        $step = (new ConfigurableStep())
            ->name('"Step name"')
            ->blackfire('true')
            ->samples(10)
        ;

        yield [
            $step,
            new Request('POST', '/'),
            true,
            false,
        ];

        // Blackfire enabled, Warmup enabled (GET)

        $step = (new ConfigurableStep())
            ->name('"Step name"')
            ->blackfire('true')
            ->warmup('true')
        ;

        yield [
            $step,
            new Request('GET', '/'),
            false,
            true,
        ];

        // Blackfire enabled, Warmup enabled (POST)

        $step = (new ConfigurableStep())
            ->name('"Step name"')
            ->blackfire('true')
            ->warmup('true')
        ;

        yield [
            $step,
            new Request('POST', '/'),
            true,
            false,
        ];

        // Blackfire enabled, Warmup enabled + samples

        $step = (new ConfigurableStep())
            ->name('"Step name"')
            ->blackfire('true')
            ->warmup('true')
            ->samples(10)
        ;

        yield [
            $step,
            new Request('POST', '/'),
            false,
            true,
        ];

        // Blackfire enabled, Warmup disabled explicitly

        $step = (new ConfigurableStep())
            ->name('"Step name"')
            ->blackfire('true')
            ->warmup('false')
        ;

        yield [
            $step,
            new Request('GET', '/'),
            true,
            false,
        ];

        // Blackfire enabled, Warmup with number

        $step = (new ConfigurableStep())
            ->name('"Step name"')
            ->blackfire('true')
            ->warmup('3')
        ;

        yield [
            $step,
            new Request('GET', '/'),
            false,
            true,
        ];
    }

    public function testTheProfileShouldNotBeRetrievedBeforeTheProfilingIsComplete()
    {
        $step = new ConfigurableStep();

        $request = new Request('GET', '/');
        $request = $request->withHeader('X-Blackfire-Profile-Uuid', '11111');

        $response = new Response();
        $response = $response->withHeader('X-Blackfire-Response', 'continue=true');

        $blackfireClient = $this->createBlackfireClient();
        $blackfireClient->expects($this->never())->method('getProfile');

        $context = $this->createContext($step);

        $extension = new BlackfireExtension(new ExpressionLanguage(), 'My env', new NullOutput(), $blackfireClient);
        $extension->enterStep($step, $request, $context);
        $extension->leaveStep($step, $request, $response, $context);
        $extension->getNextStep($step, $request, $response, $context);
    }

    public function testTheProbeCanAskANewSample()
    {
        $step = new ConfigurableStep();

        $request = new Request('GET', '/');
        $request = $request->withHeader('X-Blackfire-Profile-Uuid', '11111');

        $response = new Response();
        $response = $response->withHeader('X-Blackfire-Response', 'continue=true');

        $extension = new BlackfireExtension(new ExpressionLanguage(), 'My env', new NullOutput(), $this->createBlackfireClient());
        $nextStep = $extension->getNextStep($step, $request, $response, $this->createContext($step));

        $this->assertInstanceOf(ReloadStep::class, $nextStep);
    }

    public function testTheProbeCanAskToStopSampling()
    {
        $step = new ConfigurableStep();

        $request = new Request('GET', '/');
        $request = $request->withHeader('X-Blackfire-Profile-Uuid', '11111');

        $response = new Response();
        $response = $response->withHeader('X-Blackfire-Response', '');

        $extension = new BlackfireExtension(new ExpressionLanguage(), 'My env', new NullOutput(), $this->createBlackfireClient());
        $nextStep = $extension->getNextStep($step, $request, $response, $this->createContext($step));

        $this->assertNull($nextStep);
    }

    protected function createBlackfireClient()
    {
        $blackfireConfig = new ClientConfiguration();

        $profileRequest = $this->getMockBuilder(ProfileRequest::class)->disableOriginalConstructor()->getMock();
        $profileRequest->method('getToken')->willReturn('1234');
        $profileRequest->method('getUuid')->willReturn('1111-2222-3333-4444');

        $blackfire = $this->getMockBuilder(Client::class)->getMock();
        $blackfire->method('getConfiguration')->willReturn($blackfireConfig);
        $blackfire->method('createRequest')->willReturn($profileRequest);

        return $blackfire;
    }

    protected function createContext($step)
    {
        $stepContext = new StepContext();
        $stepContext->update($step, []);

        $contextStack = new \SplStack();
        $contextStack->push($stepContext);

        $context = new Context('Context name');
        $context->setContextStack($contextStack);

        return $context;
    }
}
