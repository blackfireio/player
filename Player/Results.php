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
 */
class Results implements \IteratorAggregate
{
    private $results = [];

    public function addResult($key, Result $result)
    {
        $this->results[$key] = $result;
    }

    public function isFatalError()
    {
        /** @var Result $result */
        foreach ($this->results as $result) {
            if ($result->isFatalError()) {
                return true;
            }
        }

        return false;
    }

    public function isExpectationError()
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
    public function isErrored()
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

    public function getIterator()
    {
        return new \ArrayIterator($this->results);
    }
}
