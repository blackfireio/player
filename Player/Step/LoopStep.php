<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Step;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class LoopStep extends BlockStep
{
    private $iterator;
    private $loopStep;
    private $keyName;
    private $valueName;

    public function __construct($iterator, $keyName, $valueName, $file = null, $line = null)
    {
        $this->iterator = $iterator;
        $this->keyName = $keyName;
        $this->valueName = $valueName;

        parent::__construct($file, $line);
    }

    public function setLoopStep(AbstractStep $loopStep)
    {
        $this->loopStep = $loopStep;
    }

    public function __toString()
    {
        $str = sprintf("└ %s: %s, %s in %s\n", \get_class($this), $this->keyName, $this->valueName, $this->iterator);
        $str .= $this->blockToString($this->loopStep);

        return $str;
    }

    public function getIterator()
    {
        return $this->iterator;
    }

    public function getLoopStep()
    {
        return $this->loopStep;
    }

    public function getKeyName()
    {
        return $this->keyName;
    }

    public function getValueName()
    {
        return $this->valueName;
    }
}
