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
class WhileStep extends BlockStep
{
    private $condition;
    private $whileStep;

    public function __construct($condition, $file = null, $line = null)
    {
        $this->condition = $condition;

        parent::__construct($file, $line);
    }

    public function setWhileStep(AbstractStep $whileStep)
    {
        $this->whileStep = $whileStep;
    }

    public function __toString()
    {
        $str = sprintf("└ %s: %s\n", \get_class($this), $this->condition);
        $str .= $this->blockToString($this->whileStep);

        return $str;
    }

    public function getCondition()
    {
        return $this->condition;
    }

    public function getWhileStep()
    {
        return $this->whileStep;
    }
}
