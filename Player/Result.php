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

use Blackfire\Player\Exception\ExpectationFailureException;
use Blackfire\Player\Exception\InvalidArgumentException;
use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\Exception\NonFatalException;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class Result implements \ArrayAccess, \Iterator
{
    private $context;
    private $values;
    private $error;

    public function __construct(Context $context, \Exception $error = null)
    {
        $this->context = $context;
        $this->values = $context->getValueBag()->all();
        $this->error = $error;
    }

    public function getScenarioName()
    {
        return $this->context->getName();
    }

    public function isFatalError()
    {
        return null !== $this->error
            && !$this->error instanceof ExpectationFailureException
            && !$this->error instanceof NonFatalException
        ;
    }

    public function isExpectationError()
    {
        return $this->error instanceof ExpectationFailureException;
    }

    public function isErrored()
    {
        return null !== $this->error;
    }

    /**
     * @return \Exception
     */
    public function getError()
    {
        return $this->error;
    }

    public function getValues()
    {
        return $this->context->getValueBag();
    }

    public function getExtra($key = null)
    {
        return null === $key ? $this->context->getExtraBag() : $this->context->getExtraBag()->get($key);
    }

    public function offsetSet($offset, $value)
    {
        throw new LogicException('The Result is immutable.');
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->values);
    }

    public function offsetUnset($offset)
    {
        throw new LogicException('The Result is immutable.');
    }

    public function offsetGet($offset)
    {
        if (!array_key_exists($offset, $this->values)) {
            throw new InvalidArgumentException(sprintf('The "%s" variable does not exist.', $offset));
        }

        return $this->values[$offset];
    }

    public function rewind()
    {
        return reset($this->values);
    }

    public function current()
    {
        return current($this->values);
    }

    public function key()
    {
        return key($this->values);
    }

    public function next()
    {
        return next($this->values);
    }

    public function valid()
    {
        return null !== key($this->values);
    }
}
