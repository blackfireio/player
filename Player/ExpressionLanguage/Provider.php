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
use Blackfire\Player\Extension\TmpDirExtension;
use Blackfire\Player\Http\Response;
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
        FakerGenerator|null $faker = null,
        private readonly bool $sandbox = false,
    ) {
        $this->faker = $faker ?? FakerFactory::create();
        $this->faker->addProvider(new ImagesGeneratorProvider($this->faker));
    }

    public function getFunctions(): array
    {
        $compiler = (fn (): string => '');

        return [
            new ExpressionFunction('url', $compiler, fn (array $arguments, string $url): string => $url),

            new ExpressionFunction('link', $compiler, function (array $arguments, string $selector) {
                if (null === $arguments['_crawler']) {
                    throw new LogicException(\sprintf('Unable to get link "%s" as the page is not crawlable.', $selector));
                }
                $this->expectScalarForFunction('link', $selector);

                return $arguments['_crawler']->selectLink($selector);
            }),

            new ExpressionFunction('button', $compiler, function (array $arguments, string $selector) {
                if (null === $arguments['_crawler']) {
                    throw new LogicException(\sprintf('Unable to submit on selector "%s" as the page is not crawlable.', $selector));
                }
                $this->expectScalarForFunction('button', $selector);

                return $arguments['_crawler']->selectButton($selector);
            }),

            new ExpressionFunction('file', $compiler, function (array $arguments, string $filename, string|null $name = null): UploadFile {
                if ($this->sandbox) {
                    if (UploadFile::isAbsolutePath($filename)) {
                        $extra = $arguments['_extra'];
                        if (!$extra->has(TmpDirExtension::EXTRA_VALUE_KEY)) {
                            throw new LogicException('The "file" provider is not supported when the "TmpDirExtension" is disabled in the sandbox mode.');
                        }
                        if (!str_starts_with($filename, (string) $extra->get(TmpDirExtension::EXTRA_VALUE_KEY))) {
                            throw new SecurityException('The "file" provider does not support absolute file paths in the sandbox mode (use the "fake()" function instead).');
                        }
                    } else {
                        throw new SecurityException('The "file" provider does not support relative file paths in the sandbox mode (use the "fake()" function instead).');
                    }
                }

                if (!UploadFile::isAbsolutePath($filename)) {
                    if (!isset($arguments['_working_dir'])) {
                        throw new LogicException(\sprintf('Unable to handle relative file "%s" as the working directory is unknown.', $filename));
                    }

                    $filename = $arguments['_working_dir'].$filename;
                }

                return new UploadFile($filename, $name ?? basename($filename));
            }),

            new ExpressionFunction('current_url', $compiler, function (array $arguments): string {
                if (null === $arguments['_crawler']) {
                    throw new LogicException('Unable to get the current URL as the page is not crawlable.');
                }

                return (string) $arguments['_crawler']->getUri();
            }),

            new ExpressionFunction('status_code', $compiler, function (array $arguments) {
                if ($arguments['_response'] instanceof Response) {
                    return $arguments['_response']->statusCode;
                }

                return $arguments['_response']->getStatusCode();
            }),

            new ExpressionFunction('headers', $compiler, function (array $arguments): array {
                $headers = [];
                if ($arguments['_response'] instanceof Response) {
                    foreach ($arguments['_response']->headers as $key => $value) {
                        $headers[$key] = $value[0];
                    }
                } else {
                    foreach ($arguments['_response']->getHeaders() as $key => $value) {
                        $headers[$key] = $value[0];
                    }
                }

                return $headers;
            }),

            new ExpressionFunction('body', $compiler, function (array $arguments): string {
                if ($arguments['_response'] instanceof Response) {
                    return $arguments['_response']->body;
                }

                return (string) $arguments['_response']->getBody();
            }),

            new ExpressionFunction('header', $compiler, function (array $arguments, string $name) {
                $this->expectScalarForFunction('header', $name);

                $name = str_replace('_', '-', strtolower($name));

                if ($arguments['_response'] instanceof Response) {
                    if (!isset($arguments['_response']->headers[$name])) {
                        return;
                    }

                    return $arguments['_response']->headers[$name][0];
                }

                if (!$arguments['_response']->hasHeader($name)) {
                    return;
                }

                return $arguments['_response']->getHeader($name)[0];
            }),

            new ExpressionFunction('trim', $compiler, fn (array $arguments, string $scalar): string => trim($scalar)),

            new ExpressionFunction('unique', $compiler, fn (array $arguments, array $arr): array => array_unique($arr)),

            new ExpressionFunction('join', $compiler, function (array $arguments, mixed $value, string $glue): string {
                if ($value instanceof \Traversable) {
                    $value = iterator_to_array($value, false);
                }

                return implode($glue, (array) $value);
            }),

            new ExpressionFunction('merge', $compiler, function (array $arguments, mixed $arr1, mixed $arr2): array {
                if ($arr1 instanceof \Traversable) {
                    $arr1 = iterator_to_array($arr1);
                } elseif (!\is_array($arr1)) {
                    throw new InvalidArgumentException(\sprintf('The merge function only works with arrays or "Traversable", got "%s" as first argument.', \gettype($arr1)));
                }

                if ($arr2 instanceof \Traversable) {
                    $arr2 = iterator_to_array($arr2);
                } elseif (!\is_array($arr2)) {
                    throw new InvalidArgumentException(\sprintf('The merge function only works with arrays or "Traversable", got "%s" as second argument.', \gettype($arr2)));
                }

                return array_merge($arr1, $arr2);
            }),

            new ExpressionFunction('fake', $compiler, function (array $arguments, string|null $provider = null/* , $othersArgs ... */) {
                $arguments = \func_get_args();

                if (null === $provider) {
                    throw new InvalidArgumentException('Missing first argument (provider) for the fake function.');
                }

                if ($this->sandbox && 'file' === $provider) {
                    throw new SecurityException('The "file" faker provider is not supported in sandbox mode.');
                }

                $args = array_splice($arguments, 2);

                if ('image' === $provider || 'simple_image' === $provider) {
                    // always store the file in a pre-determined directory
                    $extra = $arguments[0]['_extra'];
                    if (!$extra->has(TmpDirExtension::EXTRA_VALUE_KEY)) {
                        throw new LogicException('The "image" faker provider is not supported when the "TmpDirExtension" is disabled.');
                    }
                    $args[0] = $extra->get(TmpDirExtension::EXTRA_VALUE_KEY);
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

            new ExpressionFunction('regex', $compiler, function (array $arguments, string $regex, string|null $str = null): string|null {
                if (null === $str) {
                    if ($arguments['_response'] instanceof Response) {
                        $str = $arguments['_response']->body;
                    } else {
                        $str = (string) $arguments['_response']->getBody();
                    }
                }

                $ret = @preg_match($regex, $str, $matches);

                if (false === $ret) {
                    throw new InvalidArgumentException(\sprintf('Regex "%s" is not valid: %s.', $regex, error_get_last()['message']));
                }

                return $matches[1] ?? null;
            }),

            new ExpressionFunction('css', $compiler, function (array $arguments, string $selector) {
                if (null === $arguments['_crawler']) {
                    throw new LogicException(\sprintf('Unable to get the "%s" CSS selector as the page is not crawlable.', $selector));
                }
                $this->expectScalarForFunction('css', $selector);

                return $arguments['_crawler']->filter($selector);
            }),

            new ExpressionFunction('xpath', $compiler, function (array $arguments, string $selector) {
                if (null === $arguments['_crawler']) {
                    throw new LogicException(\sprintf('Unable to get "%s" XPATH selector as the page is not crawlable.', $selector));
                }
                $this->expectScalarForFunction('xpath', $selector);

                return $arguments['_crawler']->filterXPath($selector);
            }),

            new ExpressionFunction('json', $compiler, function (array $arguments, string $selector) {
                try {
                    if ($arguments['_response'] instanceof Response) {
                        $data = Json::decode($arguments['_response']->body);
                    } else {
                        $data = Json::decode((string) $arguments['_response']->getBody());
                    }
                } catch (\Throwable) {
                    throw new LogicException(\sprintf(' Unable to get the "%s" JSON path as the Response body does not seem to be JSON.', $selector));
                }

                return JmesPath::search($selector, $data);
            }),

            new ExpressionFunction('transform', $compiler, fn (array $arguments, string $selector, mixed $data) => JmesPath::search($selector, $data)),
        ];
    }

    private function expectScalarForFunction(string $function, mixed $value): void
    {
        if (!\is_scalar($value)) {
            throw new LogicException(\sprintf('Unable evaluate %s, expecting a string as argument to "%s()"', $function, $function));
        }
    }
}
