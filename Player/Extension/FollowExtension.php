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

use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\FollowStep;
use Blackfire\Player\Step\StepContext;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class FollowExtension implements NextStepExtensionInterface
{
    public function __construct(
        private readonly ExpressionLanguage $language,
    ) {
    }

    public function getPreviousSteps(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): iterable
    {
        return [];
    }

    public function getNextSteps(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): iterable
    {
        if (!$this->language->evaluate($stepContext->isFollowingRedirects(), $scenarioContext->getVariableValues($stepContext, true))) {
            return;
        }

        $response = $scenarioContext->getLastResponse();
        if (!str_starts_with((string) $response->statusCode, '3') || !isset($response->headers['location'])) {
            return;
        }

        $follow = new FollowStep(null, null, $step);

        $follow->blackfire($step->getBlackfire());
        $follow->followRedirects('true');
        $follow->name(var_export(\sprintf('Auto-following redirect to %s', $response->headers['location'][0]), true));

        yield $follow;
    }
}
