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
use Blackfire\Player\Step\LoopStep;
use Blackfire\Player\Step\StepContext;

/**
 * @internal
 */
class LoopStepProcessor implements StepProcessorInterface
{
    public function __construct(
        private readonly ExpressionEvaluator $expressionEvaluator,
    ) {
    }

    public function supports(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): bool
    {
        return $step instanceof LoopStep;
    }

    /**
     * @param LoopStep $step
     */
    public function process(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): iterable
    {
        if (!$this->supports($step, $stepContext, $scenarioContext)) {
            throw new \LogicException(\sprintf('Cannot handle steps of type "%s".', get_debug_type($step)));
        }

        $iterator = $this->expressionEvaluator->evaluateExpression($step->getValues(), $stepContext, $scenarioContext);
        if (!\is_array($iterator) && !$iterator instanceof \Traversable) {
            throw new LogicException(\sprintf('Result of expression "%s" is not iterable in step "%s".', $step->getValues(), $step::class));
        }

        $child = $step->getLoopStep();
        if (!$child) {
            return;
        }

        foreach ($iterator as $key => $value) {
            $stepContext->variable($step->getKeyName(), $key);
            $stepContext->variable($step->getValueName(), $value);

            yield clone $child;
        }
    }
}
