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
class Step extends ConfigurableStep
{
    private array $expectations = [];
    private array $variables = [];
    private array $assertions = [];
    private array $dumpValuesName = [];

    public function expect($expression)
    {
        $this->expectations[] = $expression;

        return $this;
    }

    public function has($name): bool
    {
        return \array_key_exists($name, $this->variables);
    }

    public function set($name, $expression): self
    {
        $this->variables[$name] = $expression;

        return $this;
    }

    public function assert($assertion): self
    {
        $this->assertions[] = $assertion;

        return $this;
    }

    public function getExpectations(): array
    {
        return $this->expectations;
    }

    public function resetExpectations(): self
    {
        $this->expectations = [];

        return $this;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getAssertions(): array
    {
        return $this->assertions;
    }

    public function resetAssertions(): self
    {
        $this->assertions = [];

        return $this;
    }

    public function setDumpValuesName(array $varName = []): void
    {
        $this->dumpValuesName = $varName;
    }

    public function getDumpValuesName(): array
    {
        return $this->dumpValuesName;
    }
}
