<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Psr7;

use Blackfire\Player\Context;
use Blackfire\Player\Exception\ExpectationErrorException;
use Blackfire\Player\Exception\ExpectationFailureException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\ExpressionLanguage\SyntaxError as ExpressionSyntaxError;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class ResponseChecker
{
    private $language;

    public function __construct(ExpressionLanguage $language)
    {
        $this->language = $language;
    }

    public function check(array $expectations, Context $context, RequestInterface $request, ResponseInterface $response)
    {
        $crawler = CrawlerFactory::create($response, $request->getUri());

        $variables = array_replace($context->getVariableValues(true), ['_response' => $response, '_crawler' => $crawler]);
        foreach ($expectations as $expression) {
            try {
                $parsedExpression = $this->language->parse($expression, array_keys($variables));
                $result = $this->language->evaluate($parsedExpression, $variables);
            } catch (ExpressionSyntaxError $e) {
                throw new ExpectationErrorException(sprintf('Expectation syntax error in "%s": %s', $expression, $e->getMessage()));
            }

            if (null === $result || false === $result || 0 === $result) {
                $results = $this->language->extractResults($parsedExpression, $variables);

                throw new ExpectationFailureException(sprintf('Expectation "%s" failed.', $expression), $results);
            }
        }
    }
}
