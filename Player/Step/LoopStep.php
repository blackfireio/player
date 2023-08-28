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
class LoopStep extends BlockStep
{
    private null|AbstractStep $loopStep = null;

    public function __construct(
        private readonly string $values,
        private readonly string $keyName,
        private readonly string $valueName,
        string $file = null,
        int $line = null,
    ) {
        parent::__construct($file, $line);
    }

    public function setLoopStep(AbstractStep $loopStep): void
    {
        $this->loopStep = $loopStep;
    }

    public function __toString()
    {
        $str = sprintf("└ %s: %s, %s in %s\n", static::class, $this->keyName, $this->valueName, $this->values);
        $str .= $this->blockToString($this->loopStep);

        return $str;
    }

    public function getValues(): string
    {
        return $this->values;
    }

    public function getLoopStep(): null|AbstractStep
    {
        return $this->loopStep;
    }

    public function getKeyName(): string
    {
        return $this->keyName;
    }

    public function getValueName(): string
    {
        return $this->valueName;
    }
}
