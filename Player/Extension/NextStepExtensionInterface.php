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
use Blackfire\Player\Step\Step;
use Blackfire\Player\Step\StepContext;

/**
 * @internal
 */
interface NextStepExtensionInterface
{
    /**
     * Might yield Steps which will be processed right before the current Step.
     */
    public function getPreviousSteps(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): iterable;

    /**
     * Might yield Steps which will be processed right after the current Step.
     */
    public function getNextSteps(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): iterable;
}
