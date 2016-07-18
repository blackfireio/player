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
    private $index = 1;
    private $uri;
    private $method;
    private $options;
    private $removedHeaders = [];
    private $headers = [];
    private $linkSelector;
    private $formSelector;
    private $formValues = [];
    private $expectations = [];
    private $extractions = [];
    private $title = '';
    private $follow = false;
    private $delay;
    private $json = false;
    private $next;

    public function __construct($root = true)
    {
        $this->root = (bool) $root;

        if ($this->root) {
            $this->options = new StepOptions();
        }
    }

    public function __clone()
    {
        if ($this->root) {
            $this->options = clone $this->options;
        }

        if ($this->next) {
            $this->next = clone $this->next;
        }
    }

    public function add(Scenario $scenario)
    {
        if (!$step = $scenario->getRoot()) {
            throw new LogicException('Unable to add an empty scenario.');
        }

        if ($this->root) {
            throw new LogicException('Unable to add a scenario at the root step.');
        }

        $this->next = clone $scenario->getRoot();
        $this->next->root = false;
        $this->next->options = $this->options;

        return $this->next->getLast();
    }

    public function visit($uri, $method = 'GET', $values = [])
    {
        if ($this->root && !$this->uri) {
            $step = $this;
        } else {
            $step = new self(false);
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

        $step = new self(false);
        $step->linkSelector = $selector;

        $this->next = $step;

        return $step;
    }

    public function submit($selector, array $values = [])
    {
        if ($this->root && !$this->uri) {
            throw new LogicException(sprintf('%s() cannot be called as a first step.', __METHOD__));
        }

        $step = new self(false);
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

        $step = new self(false);
        $step->follow = true;

        $this->next = $step;

        return $step;
    }

    public function reload()
    {
        if ($this->root && !$this->uri) {
            throw new LogicException(sprintf('%s() cannot be called as a first step.', __METHOD__));
        }

        $step = clone $this;
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
            $this->header('Authorization', $this->options->generateAuthorizationHeader($username, $password));
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

    public function extract($name, $expression)
    {
        $this->extractions[$name] = $expression;

        return $this;
    }

    public function getNext()
    {
        if (!$this->next) {
            return;
        }

        $this->next->options = $this->options;
        $this->next->index = $this->index + 1;

        return $this->next;
    }

    public function getIndex()
    {
        return $this->index;
    }

    public function getDelay()
    {
        return null !== $this->delay ? $this->delay : $this->options->getDelay();
    }

    public function getEndpoint()
    {
        return $this->options->getEndpoint();
    }

    public function getHeaders()
    {
        $headers = $this->options->getHeaders();
        foreach ($this->headers as $key => $values) {
            $headers[$key] = isset($headers[$key]) ? array_merge($headers[$key], $values) : $values;
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

        if ($this->follow) {
            return 'follow redirect';
        }

        return $this->uri;
    }

    /**
     * @internal
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @internal
     */
    public function setOptions(StepOptions $options)
    {
        $this->options = $options;
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
}
