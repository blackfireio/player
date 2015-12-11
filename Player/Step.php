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
use Blackfire\Player\Extension\BlackfireStepTrait;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class Step
{
    use BlackfireStepTrait;

    private $root;
    private $index;
    private $uri;
    private $method;
    private $defaultHeaders = [];
    private $removedHeaders = [];
    private $defaultDelay = null;
    private $headers = [];
    private $linkSelector;
    private $formSelector;
    private $formValues = [];
    private $expectations = [];
    private $extractions = [];
    private $title = '';
    private $follow = false;
    private $delay = null;
    private $json = false;
    private $next;

    public function __construct($root = true, $index = 1)
    {
        $this->root = $root;
        $this->index = $index;
    }

    public function __clone()
    {
        if ($this->next) {
            $this->next = clone $this->next;
        }
    }

    public function add(Scenario $scenario)
    {
        if (!$step = $scenario->getRoot()) {
            throw new LogicException('Unable to add an empty scenario.');
        }

        $this->rootStep = clone $step;

        return $this->rootStep->getLast();
    }

    public function visit($uri, $method = 'GET', $values = [])
    {
        if ($this->root && !$this->uri) {
            $step = $this;
        } else {
            $step = new self(false, $this->index + 1);
            $this->next = $step;
        }

        $step->uri = $uri;
        $step->method = $method;
        $step->formValues = $values;

        return $step;
    }

    public function click($selector)
    {
        if ($this->root && !$this->uri) {
            throw new LogicException(sprintf('%s() cannot be called as a first step.', __METHOD__));
        }

        $step = new self(false, $this->index + 1);
        $step->linkSelector = $selector;

        $this->next = $step;

        return $step;
    }

    public function submit($selector, array $values = [])
    {
        if ($this->root && !$this->uri) {
            throw new LogicException(sprintf('%s() cannot be called as a first step.', __METHOD__));
        }

        $step = new self(false, $this->index + 1);
        $step->formSelector = $selector;
        $step->formValues = $values;

        $this->next = $step;

        return $step;
    }

    public function follow()
    {
        if ($this->root && !$this->uri) {
            throw new LogicException(sprintf('%s() cannot be called as a first step.', __METHOD__));
        }

        $step = new self(false, $this->index + 1);
        $step->follow = true;

        $this->next = $step;

        return $step;
    }

    public function title($title)
    {
        $this->title = $title;

        return $this;
    }

    public function json()
    {
        $this->json = true;

        return $this;
    }

    public function header($key, $value = '')
    {
        if (false === $value) {
            if (isset($this->headers[$key])) {
                throw new LogicException('You cannot set and remove a header at the same time in a step.');
            }

            $this->removedHeaders[$key] = true;
        } else {
            if (isset($this->removedHeaders[$key])) {
                throw new LogicException('You cannot set and remove a header at the same time in a step.');
            }

            $this->headers[$key][] = $value;
        }

        return $this;
    }

    public function auth($username, $password = '')
    {
        if (false === $password) {
            $this->header('Authorization', false);
        } else {
            $this->header('Authorization', $this->generateAuthorizationHeader($username, $password));
        }

        return $this;
    }

    public function delay($delay)
    {
        $this->delay = $delay;

        return $this;
    }

    public function expect($expression)
    {
        $this->expectations[] = $expression;

        return $this;
    }

    public function extract($name, $expression, $attributes = '_text')
    {
        $this->extractions[$name] = [$expression, $attributes];

        return $this;
    }

    public function getNext()
    {
        if (!$this->next) {
            return;
        }

        // propagate defaults
        $this->next->defaultHeaders = $this->defaultHeaders;
        $this->next->defaultDelay = $this->defaultDelay;

        // propagate default delay
        if (null !== $this->defaultDelay && null === $this->next->delay) {
            $this->next->delay = $this->defaultDelay;
        }

        return $this->next;
    }

    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @internal
     */
    public function setDefaultDelay($delay)
    {
        if (!$this->root) {
            throw new LogicException('Default delay can only be set on root steps.');
        }

        $this->defaultDelay = $delay;
    }

    /**
     * @internal
     */
    public function setDefaultHeader($key, $value)
    {
        if (!$this->root) {
            throw new LogicException('Default headers can only be set on root steps.');
        }

        $this->defaultHeaders[$key][] = $value;
    }

    /**
     * @internal
     */
    public function setDefaultAuth($username, $password)
    {
        $this->setDefaultHeader('Authorization', $this->generateAuthorizationHeader($username, $password));
    }

    public function getHeaders()
    {
        $headers = $this->defaultHeaders;
        foreach ($this->headers as $key => $values) {
            $headers[$key] = array_merge($headers[$key], $values);
        }

        foreach ($headers as $key => $values) {
            if (isset($this->removedHeaders[$key])) {
                unset($headers[$key]);
            }
        }

        return $headers;
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getLinkSelector()
    {
        return $this->linkSelector;
    }

    public function getFormSelector()
    {
        return $this->formSelector;
    }

    public function isFollow()
    {
        return $this->follow;
    }

    public function getFormValues()
    {
        return $this->formValues;
    }

    public function getExpectations()
    {
        return $this->expectations;
    }

    public function getExtractions()
    {
        return $this->extractions;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function isJson()
    {
        return $this->json;
    }

    public function getName()
    {
        if ($this->title) {
            return $this->title;
        }

        if ($this->linkSelector) {
            return $this->linkSelector;
        }

        if ($this->formSelector) {
            return $this->formSelector;
        }

        return $this->uri;
    }

    /**
     * @internal
     */
    public function getLast()
    {
        if (!$this->next) {
            return $this;
        }

        return $this->next->getLast();
    }

    private function generateAuthorizationHeader($username, $password)
    {
        return 'Basic '.base64_encode(sprintf('%s:%s', $username, $password));
    }
}
