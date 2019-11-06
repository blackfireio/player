<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Extension;

use Blackfire\Build\Build;
use Blackfire\Client;
use Blackfire\ClientConfiguration;
use Blackfire\Player\Context;
use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\Extension\BlackfireExtension;
use Blackfire\Player\Step\ConfigurableStep;
use Blackfire\Player\Step\ReloadStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\ValueBag;
use Blackfire\Profile;
use Blackfire\Profile\Request as ProfileRequest;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

class BlackfireExtensionTest extends TestCase
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

            $this->assertStringContainsString('[Warmup]', $step->getName());

            $second = $step->getNext();
            $this->assertInstanceOf(ReloadStep::class, $second);
            $this->assertEquals('false', $second->getBlackfire());
            $this->assertStringContainsString('[Warmup]', $second->getName());

            $third = $second->getNext();
            $this->assertInstanceOf(ReloadStep::class, $third);
            $this->assertEquals('false', $third->getBlackfire());
            $this->assertStringContainsString('[Warmup]', $third->getName());

            $ref = $third->getNext();
            $this->assertInstanceOf(ReloadStep::class, $ref);
            $this->assertEquals('false', $ref->getBlackfire());
            $this->assertStringContainsString('[Reference]', $ref->getName());

            $real = $ref->getNext();
            $this->assertInstanceOf(ReloadStep::class, $real);
            $this->assertEquals('true', $real->getBlackfire());
            $this->assertNull($real->getNext());
        } else {
            $this->assertStringNotContainsString('Warmup', $step->getName());

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

        // Blackfire enabled, Warmup enabled by default

        $step = (new ConfigurableStep())
            ->name('"Step name"')
            ->blackfire('true')
        ;

        yield [
            $step,
            new Request('GET', '/'),
            false,
            true,
        ];

        // Blackfire enabled, Warmup enabled by default + samples

        $step = (new ConfigurableStep())
            ->name('"Step name"')
            ->blackfire('true')
            ->samples(10)
        ;

        yield [
            $step,
            new Request('POST', '/'),
            false,
            true,
        ];

        // Blackfire enabled, Warmup enabled explicitly (GET)

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

        // Blackfire enabled, Warmup enabled explicitly (POST)

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

        // Blackfire enabled, Warmup enabled explicitly + samples

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

    public function testTheProgressCannotDiminish()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageRegExp('/progress is going backward/');

        $step = new ConfigurableStep();

        $request = new Request('GET', '/');
        $request = $request->withHeader('X-Blackfire-Profile-Uuid', '11111');

        $response = new Response();
        $response = $response->withHeader('X-Blackfire-Response', 'continue=true&progress=33');

        $response2 = new Response();
        $response2 = $response2->withHeader('X-Blackfire-Response', 'continue=true&progress=15');

        $context = $this->createContext($step);

        $extension = new BlackfireExtension(new ExpressionLanguage(), 'My env', new NullOutput(), $this->createBlackfireClient());
        $extension->leaveStep($step, $request, $response, $context);
        $extension->leaveStep($step, $request, $response2, $context);
    }

    public function testTheProgressCannotBeEqual()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageRegExp('/progress is not increasing/');

        $step = new ConfigurableStep();

        $request = new Request('GET', '/');
        $request = $request->withHeader('X-Blackfire-Profile-Uuid', '11111');

        $response = new Response();
        $response = $response->withHeader('X-Blackfire-Response', 'continue=true&progress=33');

        $context = $this->createContext($step);

        $extension = new BlackfireExtension(new ExpressionLanguage(), 'My env', new NullOutput(), $this->createBlackfireClient());
        $extension->leaveStep($step, $request, $response, $context);
        $extension->leaveStep($step, $request, $response, $context);
    }

    public function testTheProgressIsResetAtTheEndOfProfiling()
    {
        $step = new ConfigurableStep();

        $request = new Request('GET', '/');
        $request = $request->withHeader('X-Blackfire-Profile-Uuid', '11111');

        $response = new Response();

        $context = $this->createContext($step);

        $extension = new BlackfireExtension(new ExpressionLanguage(), 'My env', new NullOutput(), $this->createBlackfireClient());
        $extension->leaveStep($step, $request, $response->withHeader('X-Blackfire-Response', 'continue=true&progress=99'), $context);
        $this->assertEquals(99, $context->getExtraBag()->get('blackfire_progress'));

        $extension->leaveStep($step, $request, $response->withHeader('X-Blackfire-Response', 'continue=false'), $context);
        $this->assertEquals(-1, $context->getExtraBag()->get('blackfire_progress'));

        $extension->leaveStep($step, $request, $response->withHeader('X-Blackfire-Response', 'continue=true&progress=10'), $context);
        $this->assertEquals(10, $context->getExtraBag()->get('blackfire_progress'));
    }

    protected function createBlackfireClient()
    {
        $blackfireConfig = new ClientConfiguration();

        $profileRequest = $this->getMockBuilder(ProfileRequest::class)->disableOriginalConstructor()->getMock();
        $profileRequest->method('getToken')->willReturn('1234');
        $profileRequest->method('getUuid')->willReturn('1111-2222-3333-4444');

        $profile = $this->getMockBuilder(Profile::class)->disableOriginalConstructor()->getMock();
        $profile->method('isErrored')->willReturn(false);
        $profile->method('isSuccessful')->willReturn(true);

        $build = $this->getMockBuilder(Build::class)->disableOriginalConstructor()->getMock();

        $blackfire = $this->getMockBuilder(Client::class)->getMock();
        $blackfire->method('getConfiguration')->willReturn($blackfireConfig);
        $blackfire->method('createRequest')->willReturn($profileRequest);
        $blackfire->method('getProfile')->willReturn($profile);
        $blackfire->method('startBuild')->willReturn($build);

        return $blackfire;
    }

    protected function createContext($step)
    {
        $stepContext = new StepContext();
        $stepContext->update($step, []);

        $contextStack = new \SplStack();
        $contextStack->push($stepContext);

        $context = new Context('"Context name"', new ValueBag());
        $context->setContextStack($contextStack);

        return $context;
    }
}
