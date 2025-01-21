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

use Blackfire\Player\Exception\CrawlException;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\ReloadStep;
use Blackfire\Player\Step\RequestStep;
use Blackfire\Player\Step\StepContext;

/**
 * @internal
 */
class ReloadStepProcessor implements StepProcessorInterface
{
    public function supports(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): bool
    {
        return $step instanceof ReloadStep;
    }

    /**
     * @param ReloadStep $step
     */
    public function process(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): iterable
    {
        if (!$this->supports($step, $stepContext, $scenarioContext)) {
            throw new \LogicException(\sprintf('Cannot handle steps of type "%s".', get_debug_type($step)));
        }

        if (!$scenarioContext->hasPreviousResponse()) {
            throw new CrawlException('Unable to reload without a previous request.');
        }

        yield new RequestStep(
            $scenarioContext->getLastResponse()->request,
            $step,
        );
    }
}
