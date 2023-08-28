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
 *
 * @internal
 */
class ConditionStep extends BlockStep
{
    private null|AbstractStep $ifStep = null;
    private null|AbstractStep $elseStep = null;

    public function __construct(
        private readonly string $condition,
        string $file = null,
        int $line = null,
    ) {
        parent::__construct($file, $line);
    }

    public function setIfStep(AbstractStep $ifStep): void
    {
        $this->ifStep = $ifStep;
    }

    public function setElseStep(AbstractStep $elseStep): void
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

    public function getCondition(): string
    {
        return $this->condition;
    }

    public function getIfStep(): null|AbstractStep
    {
        return $this->ifStep;
    }

    public function getElseStep(): null|AbstractStep
    {
        return $this->elseStep;
    }
}
