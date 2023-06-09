<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Step;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class VisitStep extends Step
{
    private ?string $method = null;
    private array $parameters = [];
    private mixed $body = null;

    public function __construct(
        private readonly string $uri,
        string $file = null,
        int $line = null,
    ) {
        parent::__construct($file, $line);
    }

    public function __toString()
    {
        return sprintf("└ %s: %s %s\n", static::class, $this->method ?: 'GET', $this->uri);
    }

    public function method(string $method): void
    {
        $this->method = $method;
    }

    public function param($key, $value): void
    {
        $this->parameters[$key] = $value;
    }

    public function body(mixed $body): void
    {
        $this->body = $body;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }
}
