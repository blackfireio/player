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

use Blackfire\Player\Exception\VariableErrorException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\Step;
use Blackfire\Player\Step\StepContext;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\ExpressionLanguage\SyntaxError as ExpressionSyntaxError;

/**
 * @internal
 */
class VariablesEvaluator
{
    public function __construct(
        private readonly ExpressionLanguage $language,
    ) {
    }

    public function evaluate(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
        if (!$step instanceof Step) {
            return;
        }

        foreach ($step->getVariables() as $name => $expression) {
            try {
                $data = $this->language->evaluate($expression, $scenarioContext->getVariableValues($stepContext, true));
                if ($data instanceof Crawler) {
                    $data = $data->extract(['_text']);
                    $data = 1 === \count($data) ? array_pop($data) : $data;
                }
                $scenarioContext->setVariableValue($name, $data);
            } catch (ExpressionSyntaxError $e) {
                throw new VariableErrorException(sprintf('Syntax Error in "%s": %s', $expression, $e->getMessage()));
            }
        }
    }
}
