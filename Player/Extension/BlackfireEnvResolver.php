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
use Blackfire\Player\SentrySupport;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\StepContext;

/**
 * @internal
 */
readonly class BlackfireEnvResolver
{
    private const DEPRECATION_ENV_RESOLVING = 'Resolving an environment at the scenario level using the "blackfire" property is deprecated. Please use `--blackfire-env` instead.';

    public function __construct(
        private ?string $defaultEnv,
        private ExpressionLanguage $language,
    ) {
    }

    /**
     * Resolves the environment from the current StepContext.
     *
     * When the return value is false, it means that we shouldn't profile the current step.
     */
    public function resolve(StepContext $stepContext, ScenarioContext $scenarioContext, AbstractStep $step): string|false
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
        if (true === $env) {
            if (null === $this->defaultEnv) {
                throw new \LogicException('--blackfire-env option must be set when using "blackfire: true" in a scenario.');
            }

            $env = $this->defaultEnv;
        }

        return $env ?? false;
    }
}
