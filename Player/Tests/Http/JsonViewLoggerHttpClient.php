<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Http;

use Blackfire\Player\Json;
use Symfony\Component\HttpClient\AsyncDecoratorTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class JsonViewLoggerHttpClient implements HttpClientInterface
{
    use AsyncDecoratorTrait;

    private array|null $lastJsonView;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $this->lastJsonView = Json::decode($options['body']);

        return $this->httpClient->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->httpClient->stream($responses, $timeout);
    }

    public function resetLastJsonView(): void
    {
        $this->lastJsonView = null;
    }

    public function getLastJsonView(): array|null
    {
        return $this->lastJsonView;
    }
}
