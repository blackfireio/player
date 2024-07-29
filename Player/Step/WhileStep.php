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
class WhileStep extends BlockStep
{
    private AbstractStep|null $whileStep = null;

    public function __construct(
        private readonly string $condition,
        string|null $file = null,
        int|null $line = null,
    ) {
        parent::__construct($file, $line);
    }

    public function setWhileStep(AbstractStep $whileStep): void
    {
        $this->whileStep = $whileStep;
    }

    public function __toString()
    {
        $str = \sprintf("└ %s: %s\n", static::class, $this->condition);
        $str .= $this->blockToString($this->whileStep);

        return $str;
    }

    public function getCondition(): string
    {
        return $this->condition;
    }

    public function getWhileStep(): AbstractStep|null
    {
        return $this->whileStep;
    }
}
