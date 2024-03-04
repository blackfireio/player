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

use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\BlockStep;
use Blackfire\Player\Step\ConfigurableStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\VariableResolver;

/**
 * @internal
 */
class StepContextFactory
{
    public function __construct(
        private readonly VariableResolver $variableResolver,
    ) {
    }

    public function createStepContext(AbstractStep $step, StepContext $parentStepContext): ?StepContext
    {
        if (!$step instanceof ConfigurableStep) {
            return null;
        }

        $stepContext = clone $parentStepContext;

        // evaluate variables first
        $variables = [];
        if ($step instanceof BlockStep) {
            $variables = $this->variableResolver->resolve($step->getVariables(), $stepContext->getVariables());
        }

        $stepContext->update($step, $variables);

        return $stepContext;
    }
}
