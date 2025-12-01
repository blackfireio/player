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
class ConditionStep extends BlockStep implements \Stringable
{
    private AbstractStep|null $ifStep = null;
    private AbstractStep|null $elseStep = null;

    public function __construct(
        private readonly string $condition,
        string|null $file = null,
        int|null $line = null,
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

    public function __toString(): string
    {
        $pipe = null !== $this->next;
        $str = \sprintf("└ %s: %s\n", static::class, $this->condition);
        $str .= \sprintf("%s └ When true:\n", $pipe ? '|' : '');
        $str .= $this->blockToString($this->ifStep);

        if (null !== $this->elseStep) {
            $str .= \sprintf("%s └ Else:\n", $pipe ? '|' : '');
            $str .= $this->blockToString($this->elseStep);
        }

        return $str;
    }

    public function getCondition(): string
    {
        return $this->condition;
    }

    public function getIfStep(): AbstractStep|null
    {
        return $this->ifStep;
    }

    public function getElseStep(): AbstractStep|null
    {
        return $this->elseStep;
    }
}
