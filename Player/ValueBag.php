<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player;

use Blackfire\Player\Exception\ValueException;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class ValueBag
{
    private $values = [];

    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function has($name)
    {
        return array_key_exists($name, $this->values);
    }

    public function get($name)
    {
        if (!array_key_exists($name, $this->values)) {
            throw new ValueException(sprintf('Variable "%s" is not defined.', $name));
        }

        return $this->values[$name];
    }

    public function set($name, $value)
    {
        $this->values[$name] = $value;
    }

    public function remove($name)
    {
        unset($this->values[$name]);
    }

    public function all($trim = false)
    {
        if (!$trim) {
            return $this->values;
        }

        $values = [];
        foreach ($this->values as $key => $value) {
            $values[$key] = \is_string($value) ? trim($value) : $value;
        }

        return $values;
    }
}
