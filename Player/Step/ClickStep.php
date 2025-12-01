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
class ClickStep extends Step implements \Stringable
{
    public function __construct(
        private readonly string $selector,
        string|null $file = null,
        int|null $line = null,
    ) {
        parent::__construct($file, $line);
    }

    public function __toString(): string
    {
        return \sprintf("└ %s: %s\n", static::class, $this->selector);
    }

    public function getSelector(): string
    {
        return $this->selector;
    }
}
