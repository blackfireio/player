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
use Blackfire\Player\Step\StepContext;

/**
 * @internal
 */
interface StepExtensionInterface
{
    public function beforeStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void;

    public function afterStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void;
}
