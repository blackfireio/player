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
use Blackfire\Player\Step\StepContext;

/**
 * @internal
 */
readonly class BlackfireEnvResolver
{
    public function __construct(
        private string|null $defaultEnv,
        private ExpressionLanguage $language,
    ) {
    }

    /**
     * Resolves the environment from the current StepContext.
     *
     * When the return value is false, it means that we shouldnt profile the current step.
     */
    public function resolve(StepContext $stepContext, ScenarioContext $scenarioContext): string|false
    {
        $resolvedBlackfireEnv = null;
        if (null !== $blackfireEnvironment = $scenarioContext->getScenarioSet()->getBlackfireEnvironment()) {
            $resolvedBlackfireEnv = $this->language->evaluate($blackfireEnvironment, $scenarioContext->getVariableValues($stepContext, true));
        }

        // let's now check if the current step resolves false. In that case, it means that we won't profile the step URL
        $env = $stepContext->getBlackfireEnv();
        if (null !== $env) {
            $env = $this->language->evaluate($env, $scenarioContext->getVariableValues($stepContext, true));
        }

        if (false === $env) {
            return false;
        }

        if (true === $env) {
            if (null === $this->defaultEnv) {
                throw new \LogicException('--blackfire-env option must be set when using "blackfire: true" in a scenario.');
            }

            $env = $this->defaultEnv;
        }

        return $env ?? $resolvedBlackfireEnv ?? false;
    }
}
