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
 *
 * @internal
 */
class ScenarioSet implements \IteratorAggregate
{
    private $scenarios;
    private $keys = [];
    private $extraBag;
    private $name;
    private $variables = [];

    public function __construct(array $scenarios = [])
    {
        $this->scenarios = $scenarios;
        $this->extraBag = new ValueBag();
    }

    public function __toString()
    {
        $str = '';
        $ind = 0;
        foreach ($this->scenarios as $i => $scenario) {
            $str .= sprintf(">>> Scenario %d%s <<<\n", ++$ind, !\is_int($i) ? sprintf(' (as %s)', $i) : '');
            $str .= (string) $scenario."\n";
        }

        return $str;
    }

    public function addScenarioSet(self $scenarioSet)
    {
        foreach ($scenarioSet as $reference => $scenario) {
            $this->add($scenario, $reference);
        }
    }

    public function add(Scenario $scenario, $reference = null)
    {
        if (null !== $scenario->getKey() && isset($this->keys[$scenario->getKey()])) {
            throw new LogicException(sprintf('Scenario key "%s" is already defined.', $scenario->getKey()));
        }

        if (null !== $reference && !\is_int($reference)) {
            if (isset($this->scenarios[$reference])) {
                throw new LogicException(sprintf('Reference "%s" is already defined.', $reference));
            }

            $this->scenarios[$reference] = $scenario;
        } else {
            $this->scenarios[] = $scenario;
        }

        $this->keys[$scenario->getKey()] = true;
    }

    public function name($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * @return \Traversable
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->scenarios);
    }

    public function getExtraBag()
    {
        return $this->extraBag;
    }

    public function setVariables(array $variables)
    {
        $this->variables = $variables;
    }

    public function getVariables()
    {
        return $this->variables;
    }
}
