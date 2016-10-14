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
class VisitStep extends Step
{
    private $uri;
    private $method;
    private $parameters = [];
    private $body;

    public function __construct($uri, $file = null, $line = null)
    {
        $this->uri = $uri;

        parent::__construct($file, $line);
    }

    public function __toString()
    {
        return sprintf("└ %s: %s %s\n", get_class($this), $this->method ? $this->method : 'GET', $this->uri);
    }

    public function method($method)
    {
        $this->method = $method;
    }

    public function param($key, $value)
    {
        $this->parameters[$key] = $value;
    }

    public function body($body)
    {
        $this->body = $body;
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getMethod()
    {
        return $this->method;
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
