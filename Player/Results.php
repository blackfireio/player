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

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class Results implements \IteratorAggregate
{
    private array $results = [];

    public function addResult($key, Result $result): void
    {
        $this->results[$key] = $result;
    }

    public function isFatalError(): bool
    {
        /** @var Result $result */
        foreach ($this->results as $result) {
            if ($result->isFatalError()) {
                return true;
            }
        }

        return false;
    }

    public function isExpectationError(): bool
    {
        /** @var Result $result */
        foreach ($this->results as $result) {
            if ($result->isExpectationError()) {
                return true;
            }
        }

        return false;
    }

    /** @var Result */
    public function isErrored(): bool
    {
        foreach ($this->results as $result) {
            if ($result->isErrored()) {
                return true;
            }
        }

        return false;
    }

    public function getValues()
    {
        $values = [];
        foreach ($this->results as $key => $result) {
            $values[$key] = $result->getValues()->all();
        }

        return $values;
    }

    #[\ReturnTypeWillChange]
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->results);
    }
}
