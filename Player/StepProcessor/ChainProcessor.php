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

use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\StepContext;

/**
 * @internal
 */
class ChainProcessor implements StepProcessorInterface
{
    public function __construct(
        /** @var StepProcessorInterface[] $processors */
        private readonly iterable $processors,
    ) {
    }

    public function supports(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): bool
    {
        foreach ($this->processors as $processor) {
            if ($processor->supports($step, $stepContext, $scenarioContext)) {
                return true;
            }
        }

        return false;
    }

    public function process(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): iterable
    {
        $one = false;
        foreach ($this->processors as $processor) {
            if ($processor->supports($step, $stepContext, $scenarioContext)) {
                yield from $processor->process($step, $stepContext, $scenarioContext);

                $one = true;
            }
        }

        if (!$one) {
            throw new LogicException(sprintf('Unsupported step "%s".', get_debug_type($step)));
        }
    }
}
