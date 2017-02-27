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
use Blackfire\Player\Step\AbstractStep;
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

    public function __construct(ExpressionLanguage $language)
    {
        $this->language = $language;
    }

    public function enterScenario(Scenario $scenario, Context $context)
    {
        if (!$scenario->getName()) {
            return;
        }

        try {
            $name = $this->language->evaluate($scenario->getName(), $scenario->getVariables());
        } catch (SyntaxError $e) {
            throw new ExpressionSyntaxErrorException(sprintf('Expression syntax error in "%s": %s', $expression, $e->getMessage()));
        }

        $scenario->name(sprintf('"%s"', $name));
    }

    public function enterStep(AbstractStep $step, RequestInterface $request, Context $context)
    {
        if (!$step->getName()) {
            return $request;
        }

        try {
            $name = $this->language->evaluate($step->getName(), $context->getVariableValues(true));
        } catch (SyntaxError $e) {
            throw new ExpressionSyntaxErrorException(sprintf('Expression syntax error in "%s": %s', $expression, $e->getMessage()));
        }

        $step->name(sprintf('"%s"', $name));

        return $request;
    }
}
