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
 *
 * @internal
 */
class Result implements \ArrayAccess, \Iterator
{
    private array $values;

    public function __construct(
        private readonly Context $context,
        private readonly ?\Throwable $error = null,
    ) {
        $this->values = $context->getValueBag()->all();
    }

    public function getScenarioName()
    {
        return $this->context->getName();
    }

    public function isFatalError(): bool
    {
        return null !== $this->error
            && !$this->error instanceof ExpectationFailureException
            && !$this->error instanceof NonFatalException
        ;
    }

    public function isExpectationError(): bool
    {
        return $this->error instanceof ExpectationFailureException;
    }

    public function isErrored(): bool
    {
        return null !== $this->error;
    }

    public function getError(): ?\Throwable
    {
        return $this->error;
    }

    public function getValues(): ValueBag
    {
        return $this->context->getValueBag();
    }

    public function getExtra($key = null)
    {
        return null === $key ? $this->context->getExtraBag() : $this->context->getExtraBag()->get($key);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        throw new LogicException('The Result is immutable.');
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return \array_key_exists($offset, $this->values);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        throw new LogicException('The Result is immutable.');
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if (!\array_key_exists($offset, $this->values)) {
            throw new InvalidArgumentException(sprintf('The "%s" variable does not exist.', $offset));
        }

        return $this->values[$offset];
    }

    #[\ReturnTypeWillChange]
    public function rewind()
    {
        return reset($this->values);
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return current($this->values);
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return key($this->values);
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        return next($this->values);
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return null !== key($this->values);
    }
}
