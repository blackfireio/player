<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Extension;

use Blackfire\Player\Context;
use Blackfire\Player\Step\AbstractStep;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @author Luc Vieillescazes <luc.vieillescazes@blackfire.io>
 *
 * @internal
 */
final class WatchdogExtension extends AbstractExtension
{
    private $stepLimit;
    private $totalLimit;

    public function __construct($stepLimit = 50, $totalLimit = 1000)
    {
        $this->stepLimit = $stepLimit;
        $this->totalLimit = $totalLimit;
    }

    public function enterStep(AbstractStep $step, RequestInterface $request, Context $context): RequestInterface
    {
        $extra = $context->getExtraBag();
        $nextStep = $extra->has('_watchdog_next_step') ? $extra->get('_watchdog_next_step') : null;
        $stepCounter = $extra->has('_watchdog_step_counter') ? $extra->get('_watchdog_step_counter') : 0;
        $totalCounter = $extra->has('_watchdog_total_counter') ? $extra->get('_watchdog_total_counter') : 0;

        if ($nextStep === $step) {
            $stepCounter = 1;
        } else {
            ++$stepCounter;
        }

        if ($stepCounter > $this->stepLimit) {
            throw new \RuntimeException(sprintf('Number of requests per step exceeded ("%d")', $this->stepLimit));
        }

        if (++$totalCounter > $this->totalLimit) {
            throw new \RuntimeException(sprintf('Number of requests per scenario exceeded ("%d")', $this->stepLimit));
        }

        $extra->set('_watchdog_step_counter', $stepCounter);
        $extra->set('_watchdog_total_counter', $totalCounter);

        return $request;
    }

    public function leaveStep(AbstractStep $step, RequestInterface $request, ResponseInterface $response, Context $context): ResponseInterface
    {
        $extra = $context->getExtraBag();
        $extra->set('_watchdog_next_step', $step->getNext());

        return $response;
    }
}
