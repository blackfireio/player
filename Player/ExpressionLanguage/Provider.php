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

use Blackfire\Player\Exception\InvalidArgumentException;
use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\Exception\RuntimeException;
use Blackfire\Player\Exception\SecurityException;
use Blackfire\Player\Json;
use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use JmesPath\Env as JmesPath;
use Maltyxx\ImagesGenerator\ImagesGeneratorProvider;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class Provider implements ExpressionFunctionProviderInterface
{
    private readonly FakerGenerator $faker;

    public function __construct(
        FakerGenerator $faker = null,
        private readonly bool $sandbox = false,
    ) {
        $this->faker = null !== $faker ? $faker : FakerFactory::create();
        $this->faker->addProvider(new ImagesGeneratorProvider($this->faker));
    }

    #[\ReturnTypeWillChange]
    public function getFunctions(): array
    {
        $compiler = function () { return ''; };

        return [
            new ExpressionFunction('url', $compiler, function ($arguments, $url) {
                return $url;
            }),

            new ExpressionFunction('link', $compiler, function ($arguments, $selector) {
                if (null === $arguments['_crawler']) {
                    throw new LogicException(sprintf('Unable to get link "%s" as the page is not crawlable.', $selector));
                }
                $this->expectScalarForFunction('link', $selector);

                return $arguments['_crawler']->selectLink($selector);
            }),

            new ExpressionFunction('button', $compiler, function ($arguments, $selector) {
                if (null === $arguments['_crawler']) {
                    throw new LogicException(sprintf('Unable to submit on selector "%s" as the page is not crawlable.', $selector));
                }
                $this->expectScalarForFunction('button', $selector);

                return $arguments['_crawler']->selectButton($selector);
            }),

            new ExpressionFunction('file', $compiler, function ($arguments, $filename, $name = null) {
                if ($this->sandbox) {
                    if (UploadFile::isAbsolutePath($filename)) {
                        $extra = $arguments['_extra'];
                        if (!$extra->has('tmp_dir')) {
                            throw new LogicException('The "file" provider is not supported when the "TmpDirExtension" is disabled in the sandbox mode.');
                        }
                        if (!str_starts_with($filename, $extra->get('tmp_dir'))) {
                            throw new SecurityException('The "file" provider does not support absolute file paths in the sandbox mode (use the "fake()" function instead).');
                        }
                    } else {
                        throw new SecurityException('The "file" provider does not support relative file paths in the sandbox mode (use the "fake()" function instead).');
                    }
                }

                if (!UploadFile::isAbsolutePath($filename)) {
                    if (!isset($arguments['_working_dir'])) {
                        throw new LogicException(sprintf('Unable to handle relative file "%s" as the working directory is unknown.', $filename));
                    }

                    $filename = $arguments['_working_dir'].$filename;
                }

                return new UploadFile($filename, $name ?: basename($filename));
            }),

            new ExpressionFunction('current_url', $compiler, function ($arguments) {
                if (null === $arguments['_crawler']) {
                    throw new LogicException('Unable to get the current URL as the page is not crawlable.');
                }

                return (string) $arguments['_crawler']->getUri();
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
                $this->expectScalarForFunction('header', $name);

                return $arguments['_response']->getHeader($name)[0];
            }),

            new ExpressionFunction('trim', $compiler, function ($arguments, $scalar) {
                return trim($scalar);
            }),

            new ExpressionFunction('unique', $compiler, function ($arguments, $arr) {
                return array_unique($arr);
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
                } elseif (!\is_array($arr1)) {
                    throw new InvalidArgumentException(sprintf('The merge filter only works with arrays or "Traversable", got "%s" as first argument.', \gettype($arr1)));
                }

                if ($arr2 instanceof \Traversable) {
                    $arr2 = iterator_to_array($arr2);
                } elseif (!\is_array($arr2)) {
                    throw new InvalidArgumentException(sprintf('The merge filter only works with arrays or "Traversable", got "%s" as second argument.', \gettype($arr2)));
                }

                return array_merge($arr1, $arr2);
            }),

            new ExpressionFunction('fake', $compiler, function ($arguments, $provider = null/* , $othersArgs ... */) {
                $arguments = \func_get_args();

                if (!$provider) {
                    throw new InvalidArgumentException('Missing first argument (provider) for the fake function.');
                }

                if ($this->sandbox && 'file' === $provider) {
                    throw new SecurityException('The "file" faker provider is not supported in sandbox mode.');
                }

                $args = array_splice($arguments, 2);

                if ('image' === $provider || 'simple_image' === $provider) {
                    // always store the file in a pre-determined directory
                    $extra = $arguments[0]['_extra'];
                    if (!$extra->has('tmp_dir')) {
                        throw new LogicException('The "image" faker provider is not supported when the "TmpDirExtension" is disabled.');
                    }
                    $args[0] = $extra->get('tmp_dir');
                }

                if ('simple_image' === $provider) {
                    $provider = 'imageGenerator';
                }

                $ret = $this->faker->format($provider, $args);

                if ('image' === $provider && false === $ret) {
                    // the server was not reachable and the image has not been generated
                    throw new RuntimeException('The "image" faker provider failed as the server generating the images is not available.');
                }

                return $ret;
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
                    throw new LogicException(sprintf('Unable to get the "%s" CSS selector as the page is not crawlable.', $selector));
                }
                $this->expectScalarForFunction('css', $selector);

                return $arguments['_crawler']->filter($selector);
            }),

            new ExpressionFunction('xpath', $compiler, function ($arguments, $selector) {
                if (null === $arguments['_crawler']) {
                    throw new LogicException(sprintf('Unable to get "%s" XPATH selector as the page is not crawlable.', $selector));
                }
                $this->expectScalarForFunction('xpath', $selector);

                return $arguments['_crawler']->filterXPath($selector);
            }),

            new ExpressionFunction('json', $compiler, function ($arguments, $selector) {
                try {
                    $data = Json::decode((string) $arguments['_response']->getBody());
                } catch (\Throwable $e) {
                    throw new LogicException(sprintf(' Unable to get the "%s" JSON path as the Response body does not seem to be JSON.', $selector));
                }

                return JmesPath::search($selector, $data);
            }),

            new ExpressionFunction('transform', $compiler, function ($arguments, $selector, $data) {
                return JmesPath::search($selector, $data);
            }),
        ];
    }

    private function expectScalarForFunction($function, $value)
    {
        if (!\is_scalar($value)) {
            throw new LogicException(sprintf('Unable evaluate %s, expecting a string as argument to "%s()"', $function, $function));
        }
    }
}
