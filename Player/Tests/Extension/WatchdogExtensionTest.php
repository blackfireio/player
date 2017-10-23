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

use Blackfire\Player\Context;
use Blackfire\Player\Extension\WatchdogExtension;
use Blackfire\Player\Step\ConfigurableStep;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WatchdogExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testStepLimitResetWhenStepIsExpectedOne()
    {
        $extension = new WatchdogExtension(1, 10);

        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $context = new Context('Test');

        $step = new ConfigurableStep();
        $step->next(new ConfigurableStep());

        $extension->enterStep($step, $request, $context);
        $extension->leaveStep($step, $request, $response, $context);

        $this->assertEquals(1, $context->getExtraBag()->get('_watchdog_step_counter'));
        $this->assertEquals(1, $context->getExtraBag()->get('_watchdog_total_counter'));

        $next = $step->getNext();
        $extension->enterStep($next, $request, $context);
        $extension->leaveStep($next, $request, $response, $context);

        $this->assertEquals(1, $context->getExtraBag()->get('_watchdog_step_counter'));
        $this->assertEquals(2, $context->getExtraBag()->get('_watchdog_total_counter'));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Number of requests per step exceeded ("1")
     */
    public function testStepLimitExceededThrowsException()
    {
        $extension = new WatchdogExtension(1, 10);

        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $context = new Context('Test');

        $step = new ConfigurableStep();
        $step->next(new ConfigurableStep());

        $extension->enterStep($step, $request, $context);
        $extension->leaveStep($step, $request, $response, $context);

        $this->assertEquals(1, $context->getExtraBag()->get('_watchdog_step_counter'));
        $this->assertEquals(1, $context->getExtraBag()->get('_watchdog_total_counter'));

        $next = new ConfigurableStep();
        $extension->enterStep($next, $request, $context);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Number of requests per scenario exceeded ("1")
     */
    public function testTotalLimitExceededThrowsException()
    {
        $extension = new WatchdogExtension(1, 1);

        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $context = new Context('Test');

        $step = new ConfigurableStep();
        $step->next(new ConfigurableStep());

        $extension->enterStep($step, $request, $context);
        $extension->leaveStep($step, $request, $response, $context);

        $this->assertEquals(1, $context->getExtraBag()->get('_watchdog_step_counter'));
        $this->assertEquals(1, $context->getExtraBag()->get('_watchdog_total_counter'));

        $next = $step->getNext();
        $extension->enterStep($next, $request, $context);
    }
}
