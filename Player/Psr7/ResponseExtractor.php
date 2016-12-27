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
use Blackfire\Player\Exception\VariableErrorException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\ExpressionLanguage\SyntaxError as ExpressionSyntaxError;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class ResponseExtractor
{
    private $language;

    public function __construct(ExpressionLanguage $language)
    {
        $this->language = $language;
    }

    public function extract($extractions, Context $context, RequestInterface $request, ResponseInterface $response)
    {
        $crawler = CrawlerFactory::create($response, $request->getUri());

        $variables = ['_response' => $response, '_crawler' => $crawler];

        foreach ($extractions as $name => $expression) {
            try {
                $data = $this->language->evaluate($expression, $variables + $context->getVariableValues(true));
                if ($data instanceof Crawler) {
                    $data = $data->extract('_text');
                    $data = count($data) == 1 ? array_pop($data) : $data;
                }

                $context->getValueBag()->set($name, $data);
            } catch (ExpressionSyntaxError $e) {
                throw new VariableErrorException(sprintf('Syntax Error in "%s": %s', $expression, $e->getMessage()));
            }
        }
    }
}
