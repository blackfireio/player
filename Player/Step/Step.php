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
    /** @var string[] */
    private array $expectations = [];
    /** @var string[] */
    private array $variables = [];
    /** @var string[] */
    private array $assertions = [];
    /** @var string[] */
    private array $dumpValuesName = [];

    public function expect(string $expression): self
    {
        $this->expectations[] = $expression;

        return $this;
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->variables);
    }

    public function set(string $name, string $variable): self
    {
        $this->variables[$name] = $variable;

        return $this;
    }

    public function assert(string $assertion): self
    {
        $this->assertions[] = $assertion;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getExpectations(): array
    {
        return $this->expectations;
    }

    public function resetExpectations(): self
    {
        $this->expectations = [];

        return $this;
    }

    /**
     * @return string[]
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    /**
     * @return string[]
     */
    public function getAssertions(): array
    {
        return $this->assertions;
    }

    public function resetAssertions(): self
    {
        $this->assertions = [];

        return $this;
    }

    /**
     * @param string[] $varName
     */
    public function setDumpValuesName(array $varName): void
    {
        $this->dumpValuesName = $varName;
    }

    /**
     * @return string[]
     */
    public function getDumpValuesName(): array
    {
        return $this->dumpValuesName;
    }
}
