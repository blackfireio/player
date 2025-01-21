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
    private AbstractStep|null $loopStep = null;

    public function __construct(
        private readonly string $values,
        private readonly string $keyName,
        private readonly string $valueName,
        string|null $file = null,
        int|null $line = null,
    ) {
        parent::__construct($file, $line);
    }

    public function setLoopStep(AbstractStep $loopStep): void
    {
        $this->loopStep = $loopStep;
    }

    public function __toString(): string
    {
        $str = \sprintf("└ %s: %s, %s in %s\n", static::class, $this->keyName, $this->valueName, $this->values);

        return $str.$this->blockToString($this->loopStep);
    }

    public function getValues(): string
    {
        return $this->values;
    }

    public function getLoopStep(): AbstractStep|null
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
