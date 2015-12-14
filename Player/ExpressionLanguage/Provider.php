<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\ExpressionLanguage;

use Blackfire\Player\Exception\LogicException;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use JmesPath\Env as JmesPath;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class Provider implements ExpressionFunctionProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        $compiler = function () {
            throw new LogicException('Compilation not supported.');
        };

        return [
            new ExpressionFunction('url', $compiler, function ($arguments, $url) {
                return $url;
            }),

            new ExpressionFunction('link', $compiler, function ($arguments, $selector) {
                return $arguments['_crawler']->selectLink($selector);
            }),

            new ExpressionFunction('button', $compiler, function ($arguments, $selector) {
                return $arguments['_crawler']->selectButton($selector);
            }),

            new ExpressionFunction('status_code', $compiler, function ($arguments) {
                return $arguments['_response']->getStatusCode();
            }),

            new ExpressionFunction('headers', $compiler, function ($arguments) {
                $headers = [];
                foreach ($arguments['_response']->getHeaders() as $key => $value) {
                    $headers[$key] = $value[0];
                }

                return $headers;
            }),

            new ExpressionFunction('body', $compiler, function ($arguments) {
                return (string) $arguments['_response']->getBody();
            }),

            new ExpressionFunction('header', $compiler, function ($arguments, $name) {
                $name = str_replace('_', '-', strtolower($name));

                if (!$arguments['_response']->hasHeader($name)) {
                    return;
                }

                return $arguments['_response']->getHeader($name)[0];
            }),

            new ExpressionFunction('scalar', $compiler, function ($arguments, $string) {
                return $string;
            }),

            new ExpressionFunction('css', $compiler, function ($arguments, $selector) {
                if (null === $arguments['_crawler']) {
                    throw new LogicException(sprintf('Unable to get "%s" CSS selector as the page is not crawlable.', $selector));
                }

                return $arguments['_crawler']->filter($selector);
            }),

            new ExpressionFunction('xpath', $compiler, function ($arguments, $selector) {
                if (null === $arguments['_crawler']) {
                    throw new LogicException(sprintf('Unable to get "%s" XPATH selector as the page is not crawlable.', $selector));
                }

                return $arguments['_crawler']->filterXPath($selector);
            }),

            new ExpressionFunction('json', $compiler, function ($arguments, $selector) {
                if (null === $data = @json_decode($arguments['body'], true)) {
                    throw new LogicException(sprintf(' Unable to get the "%s" JSON path as the Response body does not seem to be JSON.', $selector));
                }

                return JmesPath::search($selector, $data);
            }),
        ];
    }
}
