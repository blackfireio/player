<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Util\Exporter;
use SebastianBergmann\Comparator\ComparisonFailure;

/**
 * @internal
 */
final class ArraySubset extends Constraint
{
    public function __construct(private iterable $subset, private readonly bool $strict = false)
    {
    }

    private function _evaluate($other, string $description = '', bool $returnResult = false): bool|null
    {
        // type cast $other & $this->subset as an array to allow
        // support in standard array functions.
        $other = $this->toArray($other);
        $this->subset = $this->toArray($this->subset);
        $patched = array_replace_recursive($other, $this->subset);
        if ($this->strict) {
            $result = $other === $patched;
        } else {
            $result = $other == $patched;
        }
        if ($returnResult) {
            return $result;
        }
        if ($result) {
            return null;
        }

        $f = new ComparisonFailure(
            $patched,
            $other,
            var_export($patched, true),
            var_export($other, true)
        );
        $this->fail($other, $description, $f);

        return false;
    }

    public function toString(): string
    {
        return 'has the subset '.Exporter::export($this->subset);
    }

    protected function failureDescription($other): string
    {
        return 'an array '.$this->toString();
    }

    private function toArray(iterable $other): array
    {
        if (\is_array($other)) {
            return $other;
        }
        if ($other instanceof \ArrayObject) {
            return $other->getArrayCopy();
        }

        return iterator_to_array($other);
    }

    public function evaluate($other, string $description = '', bool $returnResult = false): bool|null
    {
        return $this->_evaluate($other, $description, $returnResult);
    }
}
