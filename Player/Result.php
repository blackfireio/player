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

use Blackfire\Player\Exception\InvalidArgumentException;
use Blackfire\Player\Exception\LogicException;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class Result implements \ArrayAccess, \Iterator
{
    private $valueBag;
    private $values;
    private $extra;
    private $error;

    public function __construct(ValueBag $values, ValueBag $extra, \Exception $error = null)
    {
        $this->values = $values->all();
        $this->valueBag = $values;
        $this->extra = $extra;
        $this->error = $error;
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
        return $this->valueBag;
    }

    public function getExtra($key = null)
    {
        return null === $key ? $this->extra : $this->extra->get($key);
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
