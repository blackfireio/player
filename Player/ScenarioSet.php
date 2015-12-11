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
class ScenarioSet implements \IteratorAggregate
{
    private $scenarios;

    public function __construct(array $scenarios = [])
    {
        $this->scenarios = $scenarios;
    }

    public function add(Scenario $scenario, $reference = null)
    {
        if (null !== $reference) {
            $this->scenarios[$reference] = $scenario;
        } else {
            $this->scenarios[] = $scenario;
        }
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->scenarios);
    }
}
