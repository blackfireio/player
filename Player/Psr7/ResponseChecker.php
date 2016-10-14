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

use Blackfire\Player\Exception\ExpectationErrorException;
use Blackfire\Player\Exception\ExpectationFailureException;
use Blackfire\Player\Context;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError as ExpressionSyntaxError;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
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

        $variables = ['_response' => $response, '_crawler' => $crawler];

        foreach ($expectations as $expression) {
            try {
                $result = $this->language->evaluate($expression, $variables + $context->getVariableValues(true));
            } catch (ExpressionSyntaxError $e) {
                throw new ExpectationErrorException(sprintf('Expectation syntax error in "%s": %s', $expression, $e->getMessage()));
            }

            if (null === $result || false === $result || 0 === $result) {
                throw new ExpectationFailureException(sprintf('Expectation "%s" failed', $expression));
            }
        }
    }
}
