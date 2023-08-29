<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\StepProcessor;

use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\StepContext;

/**
 * @internal
 */
interface StepProcessorInterface
{
    public function supports(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): bool;

    /**
     * @return AbstractStep[]
     */
    public function process(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): iterable;
}
