<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Guzzle;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class PlayerMiddleware
{
    private $handler;

    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    public static function create()
    {
        return function (callable $handler) {
            return new self($handler);
        };
    }

    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        $handler = $this->handler;

        if (!isset($options['player_context'])) {
            return $handler($request, $options);
        }

        $generator = $options['player_context']->getGenerator();

        return $handler($request, $options)
            ->then(function (ResponseInterface $response) use ($generator, $request, $options) {
                if (!$request = $generator->send([$request, $response])) {
                    return $response;
                }

                return $this($request, $options);
            })
            ->otherwise(function (\Exception $exception) use ($generator) {
                $generator->throw($exception);
            });
    }
}
