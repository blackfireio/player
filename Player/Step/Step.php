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
        $this->isBlackfireConfigured = true;

        return $this;
    }

    public function getExpectations()
    {
        return $this->expectations;
    }

    public function getVariables()
    {
        return $this->variables;
    }

    public function getAssertions()
    {
        return $this->assertions;
    }
}
