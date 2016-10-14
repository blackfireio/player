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
class AbstractStep
{
    protected $next;

    private $name = '';
    private $file;
    private $line;

    public function __construct($file = null, $line = null)
    {
        $this->file = $file;
        $this->line = $line;
    }

    public function __clone()
    {
        if ($this->next) {
            $this->next = clone $this->next;
        }
    }

    public function __toString()
    {
        return sprintf("└ %s\n", get_class($this));
    }

    public function name($name)
    {
        $this->name = $name;

        return $this;
    }

    public function next(AbstractStep $step)
    {
        $this->next = $step;

        return $step->getLast();
    }

    public function getNext()
    {
        return $this->next;
    }

    public function getName()
    {
        return $this->name ?: null;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function getLine()
    {
        return $this->line;
    }

    /**
     * @internal
     */
    public function getLast()
    {
        if (!$this->next) {
            return $this;
        }

        return $this->next->getLast();
    }
}
