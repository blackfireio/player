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
 *
 * @internal
 */
class ValueBag
{
    public function __construct(
        private array $values = [],
    ) {
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->values);
    }

    public function get(string $name): mixed
    {
        if (!\array_key_exists($name, $this->values)) {
            throw new ValueException(\sprintf('Variable "%s" is not defined.', $name));
        }

        return $this->values[$name];
    }

    public function set(string $name, mixed $value): void
    {
        $this->values[$name] = $value;
    }

    public function remove(string $name): void
    {
        unset($this->values[$name]);
    }

    public function all(bool $trim = false): array
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
