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
class ConditionStep extends BlockStep
{
    private $condition;
    private $ifStep;
    private $elseStep;

    public function __construct($condition, $file = null, $line = null)
    {
        $this->condition = $condition;

        parent::__construct($file, $line);
    }

    public function setIfStep(AbstractStep $ifStep)
    {
        $this->ifStep = $ifStep;
    }

    public function setElseStep(AbstractStep $elseStep)
    {
        $this->elseStep = $elseStep;
    }

    public function __toString()
    {
        $pipe = null !== $this->next;
        $str = sprintf("└ %s: %s\n", static::class, $this->condition);
        $str .= sprintf("%s └ When true:\n", $pipe ? '|' : '');
        $str .= $this->blockToString($this->ifStep);

        if ($this->elseStep) {
            $str .= sprintf("%s └ Else:\n", $pipe ? '|' : '');
            $str .= $this->blockToString($this->elseStep);
        }

        return $str;
    }

    public function getCondition()
    {
        return $this->condition;
    }

    public function getIfStep()
    {
        return $this->ifStep;
    }

    public function getElseStep()
    {
        return $this->elseStep;
    }
}
