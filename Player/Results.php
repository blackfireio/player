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

    public function isErrored($includeExpectation = true)
    {
        foreach ($this->results as $result) {
            if ($result->isErrored($includeExpectation)) {
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
