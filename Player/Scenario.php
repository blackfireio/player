<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player;

use Blackfire\Player\Exception\LogicException;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class Scenario
{
    private $auth;
    private $root;
    private $key;
    private $values;

    public function __construct($title = null, array $values = array())
    {
        $this->root = new Step();
        $this->title = null === $title ? 'Untitled Scenario' : $title;
        $this->values = $values;
    }

    /**
     * @return Step
     */
    public function visit($uri, $method = 'GET')
    {
        return $this->root->visit($uri, $method);
    }

    /**
     * @return Step
     */
    public function add(Scenario $scenario)
    {
        if (!$step = $scenario->getRoot()) {
            throw new LogicException('Unable to add an empty scenario.');
        }

        $this->root = clone $step;

        return $this->root->getLast();
    }

    public function header($key, $value)
    {
        $this->root->setDefaultHeader($key, $value);

        return $this;
    }

    public function auth($username, $password)
    {
        $this->root->setDefaultAuth($username, $password);

        return $this;
    }

    public function delay($delay)
    {
        $this->root->setDefaultDelay($delay);

        return $this;
    }

    public function value($key, $value)
    {
        $this->values[$key] = $value;

        return $this;
    }

    public function key($key)
    {
        $this->key = $key;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getValues()
    {
        return $this->values;
    }

    public function getRoot()
    {
        return $this->root;
    }
}
