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

use Blackfire\Player\Exception\ExpressionSyntaxErrorException;
use Blackfire\Player\Exception\InvalidArgumentException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\ConfigurableStep;
use Blackfire\Player\Step\StepContext;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final readonly class WaitExtension implements StepExtensionInterface
{
    public function __construct(
        private ExpressionLanguage $language,
    ) {
    }

    public function beforeStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
    }

    public function afterStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
        if (!$step instanceof ConfigurableStep) {
            return;
        }

        if (null === $wait = $stepContext->getWait()) {
            return;
        }

        try {
            $delay = $this->language->evaluate($wait, $scenarioContext->getVariableValues($stepContext, true));
        } catch (ExpressionSyntaxErrorException $e) {
            throw new InvalidArgumentException(\sprintf('Wait syntax error in "%s": %s', $wait, $e->getMessage()));
        }

        $end = microtime(true) + ($delay / 1_000);
        while ($end > microtime(true)) {
            \Fiber::suspend();
        }
    }
}
