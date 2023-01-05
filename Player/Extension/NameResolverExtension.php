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

use Blackfire\Player\Context;
use Blackfire\Player\Exception\ExpressionSyntaxErrorException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\VariableResolver;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
final class NameResolverExtension extends AbstractExtension
{
    private $language;
    private $variableResolver;

    public function __construct(ExpressionLanguage $language, VariableResolver $variableResolver = null)
    {
        $this->language = $language;
        $this->variableResolver = $variableResolver ?: new VariableResolver($this->language);
    }

    public function enterScenarioSet(ScenarioSet $scenarios, $concurrency)
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

        $scenarios->name(sprintf('"%s"', $name));
    }

    public function enterScenario(Scenario $scenario, Context $context)
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

        $scenario->name(sprintf('"%s"', $name));
    }

    public function enterStep(AbstractStep $step, RequestInterface $request, Context $context): RequestInterface
    {
        if (!$step->getName()) {
            return $request;
        }

        try {
            $name = $this->language->evaluate($step->getName(), $context->getVariableValues(true));
        } catch (SyntaxError $e) {
            throw new ExpressionSyntaxErrorException(sprintf('Expression syntax error in "%s": %s', $step->getName(), $e->getMessage()));
        }

        $step->name(sprintf('"%s"', $name));

        return $request;
    }
}
