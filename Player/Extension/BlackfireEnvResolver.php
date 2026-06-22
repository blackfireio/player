<?php

declare(strict_types=1);

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
use Blackfire\Player\SentrySupport;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\StepContext;

/**
 * @internal
 */
readonly class BlackfireEnvResolver
{
    private const string DEPRECATION_ENV_RESOLVING = 'Resolving an environment at the scenario level using the "blackfire" property is deprecated. Please use `--blackfire-env` instead.';

    public function __construct(
        private string|null $defaultEnv,
        private ExpressionLanguage $language,
    ) {
    }

    /**
     * Resolves the environment from the current StepContext.
     *
     * The return value tells the caller how to profile the current step:
     *  - false:  don't profile the step;
     *  - null:   profile the step without an explicit environment (the agent /
     *            personal collab token decides where the profile lands);
     *  - string: profile the step in that environment.
     */
    public function resolve(StepContext $stepContext, ScenarioContext $scenarioContext, AbstractStep $step): string|false|null
    {
        // let's check if the current step resolves false. In that case, it means that we won't profile the step URL
        $env = $stepContext->getBlackfireEnv();
        if (null !== $env) {
            $env = $this->language->evaluate($env, $scenarioContext->getVariableValues($stepContext, true));
            // if it resolves a string, that's an environment name: it's deprecated. We emit a deprecation warning and return it.
            if (\is_string($env)) {
                SentrySupport::captureMessage('blackfire property used to resolve the blackfire environment');
                $step->addDeprecation(self::DEPRECATION_ENV_RESOLVING);

                return $env;
            }
        } else {
            $env = $scenarioContext->getScenarioSet()->getBlackfireEnvironment();
        }

        // it resolved false: we won't profile the step
        if (false === $env) {
            return false;
        }

        // it resolved true: we'll profile the step using the default environment.
        // When no default environment is set, we still profile the step but
        // without an explicit environment (the agent / personal collab token
        // decides where the profile lands).
        if (true === $env) {
            return $this->defaultEnv;
        }

        return $env ?? false;
    }
}
