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
class ClickStep extends Step
{
    private $selector;

    public function __construct($selector, $file = null, $line = null)
    {
        $this->selector = $selector;

        parent::__construct($file, $line);
    }

    public function __toString()
    {
        return sprintf("└ %s: %s\n", static::class, $this->selector);
    }

    public function getSelector()
    {
        return $this->selector;
    }
}
