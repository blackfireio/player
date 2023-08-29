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
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\Json;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\ScenarioResult;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\ScenarioSetResult;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\VariableResolver;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
final class NameResolverExtension implements ScenarioSetExtensionInterface, ScenarioExtensionInterface, StepExtensionInterface
{
    public function __construct(
        private readonly ExpressionLanguage $language,
        private readonly VariableResolver $variableResolver,
    ) {
    }

    public function beforeScenarioSet(ScenarioSet $scenarios, int $concurrency): void
    {
        if (!$scenarios->getName()) {
            return;
        }

        try {
            $variables = $this->variableResolver->resolve($scenarios->getVariables());
            $name = $this->language->evaluate($scenarios->getName(), $variables);
        } catch (SyntaxError $e) {
            throw new ExpressionSyntaxErrorException(sprintf('Expression syntax error in "%s": %s', $scenarios->getName(), $e->getMessage()));
        }

        $scenarios->name(Json::encode((string) $name));
    }

    public function afterScenarioSet(ScenarioSet $scenarios, int $concurrency, ScenarioSetResult $scenarioSetResult): void
    {
    }

    public function beforeScenario(Scenario $scenario, ScenarioContext $scenarioContext): void
    {
        if (!$scenario->getName()) {
            return;
        }

        try {
            $variables = $this->variableResolver->resolve($scenario->getVariables());
            $name = $this->language->evaluate($scenario->getName(), $variables);
        } catch (SyntaxError $e) {
            throw new ExpressionSyntaxErrorException(sprintf('Expression syntax error in "%s": %s', $scenario->getName(), $e->getMessage()));
        }

        $scenario->name(Json::encode((string) $name));
    }

    public function afterScenario(Scenario $scenario, ScenarioContext $scenarioContext, ScenarioResult $scenarioResult): void
    {
    }

    public function beforeStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
        if (!$step->getName()) {
            return;
        }

        try {
            $name = $this->language->evaluate($step->getName(), $scenarioContext->getVariableValues($stepContext, true));
        } catch (SyntaxError $e) {
            throw new ExpressionSyntaxErrorException(sprintf('Expression syntax error in "%s": %s', $step->getName(), $e->getMessage()));
        }

        $step->name(Json::encode((string) $name));
    }

    public function afterStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
    }
}
