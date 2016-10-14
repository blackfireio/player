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
class SubmitStep extends Step
{
    private $selector;
    private $parameters = [];
    private $body;

    public function __construct($selector, $file = null, $line = null)
    {
        $this->selector = $selector;

        parent::__construct($file, $line);
    }

    public function __toString()
    {
        return sprintf("└ %s: %s\n", get_class($this), $this->selector);
    }

    public function param($key, $value)
    {
        $this->parameters[$key] = $value;
    }

    public function body($body)
    {
        $this->body = $body;
    }

    public function getSelector()
    {
        return $this->selector;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function getBody()
    {
        return $this->body;
    }
}
