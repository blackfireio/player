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
use Blackfire\Player\Exception\InvalidArgumentException;
use Faker\Generator as FakerGenerator;
use Faker\Factory as FakerFactory;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use JmesPath\Env as JmesPath;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class Provider implements ExpressionFunctionProviderInterface
{
    private $faker;

    public function __construct(FakerGenerator $faker = null)
    {
        $this->faker = null !== $faker ? $faker : FakerFactory::create();
    }

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

            new ExpressionFunction('scalar', $compiler, function ($arguments, $scalar) {
                return $scalar;
            }),

            new ExpressionFunction('join', $compiler, function ($arguments, $value, $glue) {
                if ($value instanceof \Traversable) {
                    $value = iterator_to_array($value, false);
                }

                return implode($glue, (array) $value);
            }),

            new ExpressionFunction('merge', $compiler, function ($arguments, $arr1, $arr2) {
                if ($arr1 instanceof \Traversable) {
                    $arr1 = iterator_to_array($arr1);
                } elseif (!is_array($arr1)) {
                    throw new InvalidArgumentException(sprintf('The merge filter only works with arrays or "Traversable", got "%s" as first argument.', gettype($arr1)));
                }

                if ($arr2 instanceof \Traversable) {
                    $arr2 = iterator_to_array($arr2);
                } elseif (!is_array($arr2)) {
                    throw new InvalidArgumentException(sprintf('The merge filter only works with arrays or "Traversable", got "%s" as second argument.', gettype($arr2)));
                }

                return array_merge($arr1, $arr2);
            }),

            new ExpressionFunction('fake', $compiler, function ($arguments, $provider) {
                $arguments = func_get_args();

                return $this->faker->format($provider, array_splice($arguments, 2));
            }),

            new ExpressionFunction('regex', $compiler, function ($arguments, $regex, $str = null) {
                if (null === $str) {
                    $str = (string) $arguments['_response']->getBody();
                }

                $ret = @preg_match($regex, $str, $matches);

                if (false === $ret) {
                    throw new InvalidArgumentException(sprintf('Regex "%s" is not valid: %s.', $regex, error_get_last()['message']));
                }

                return isset($matches[1]) ? $matches[1] : null;
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
                if (null === $data = json_decode((string) $arguments['_response']->getBody(), true)) {
                    throw new LogicException(sprintf(' Unable to get the "%s" JSON path as the Response body does not seem to be JSON.', $selector));
                }

                return JmesPath::search($selector, $data);
            }),
        ];
    }
}
