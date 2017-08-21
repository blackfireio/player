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
 */
class Step extends ConfigurableStep
{
    private $expectations = [];
    private $variables = [];
    private $assertions = [];
    private $dumpValuesName = [];

    public function expect($expression)
    {
        $this->expectations[] = $expression;

        return $this;
    }

    public function set($name, $expression)
    {
        $this->variables[$name] = $expression;

        return $this;
    }

    public function assert($assertion)
    {
        $this->assertions[] = $assertion;

        return $this;
    }

    public function getExpectations()
    {
        return $this->expectations;
    }

    public function resetExpectations()
    {
        $this->expectations = [];

        return $this;
    }

    public function getVariables()
    {
        return $this->variables;
    }

    public function getAssertions()
    {
        return $this->assertions;
    }

    public function resetAssertions()
    {
        $this->assertions = [];

        return $this;
    }

    public function setDumpValuesName(array $varName = [])
    {
        $this->dumpValuesName = $varName;
    }

    public function getDumpValuesName()
    {
        return $this->dumpValuesName;
    }
}
