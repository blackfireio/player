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
class SubmitStep extends Step
{
    /** @var string[] */
    private array $parameters = [];
    private string|null $body = null;

    public function __construct(
        private readonly string $selector,
        string|null $file = null,
        int|null $line = null,
    ) {
        parent::__construct($file, $line);
    }

    public function __toString()
    {
        return \sprintf("└ %s: %s\n", static::class, $this->selector);
    }

    public function param(string $key, string $value): void
    {
        $this->parameters[$key] = $value;
    }

    public function body(string $body): void
    {
        $this->body = $body;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    /**
     * @return string[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getBody(): string|null
    {
        return $this->body;
    }
}
