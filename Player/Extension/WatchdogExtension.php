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

use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\BlockStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\StepInitiatorInterface;

/**
 * @author Luc Vieillescazes <luc.vieillescazes@blackfire.io>
 *
 * @internal
 */
final readonly class WatchdogExtension implements StepExtensionInterface
{
    public function __construct(
        private int $stepLimit = 50,
        private int $totalLimit = 1000,
    ) {
    }

    public function beforeStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
        if ($step instanceof BlockStep) {
            return;
        }

        // set new ExtraValue counter for step UUID at 0
        // if step has initiator uuid:
        //  - increment the initiator uuid counter
        // increment the step uuid counter otherwise

        $stepUuid = $step->getUuid();
        if ($step instanceof StepInitiatorInterface && null !== $step->getInitiator()) {
            $stepUuid = $step->getInitiator()->getUuid();
        }

        $stepCounterKey = '_watchdog_step_counter:'.$stepUuid;

        $totalCounter = $scenarioContext->getExtraValue('_watchdog_total_counter', 0);

        $stepCounter = $scenarioContext->getExtraValue($stepCounterKey, 0);
        ++$stepCounter;

        if ($stepCounter > $this->stepLimit) {
            throw new \RuntimeException(\sprintf('Number of requests per step exceeded ("%d")', $this->stepLimit));
        }

        if (++$totalCounter > $this->totalLimit) {
            throw new \RuntimeException(\sprintf('Number of requests per scenario exceeded ("%d")', $this->stepLimit));
        }

        $scenarioContext->setExtraValue($stepCounterKey, $stepCounter);
        $scenarioContext->setExtraValue('_watchdog_total_counter', $totalCounter);
    }

    public function afterStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
    }
}
