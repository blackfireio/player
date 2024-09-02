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
final class ExpectationExtension implements StepExtensionInterface
{
    public function __construct(
        private readonly ResponseChecker $responseChecker,
    ) {
    }

    public function beforeStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
    }

    public function afterStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
        if (!$step instanceof Step) {
            return;
        }

        $variables = $scenarioContext->getVariableValues($stepContext, true);
        $this->responseChecker->check($step->getExpectations(), $variables);
    }
}
