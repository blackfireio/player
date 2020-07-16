<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\ExpressionLanguage;

use Symfony\Component\DomCrawler\Crawler;

class VariableFormatter
{
    public function formatResult($value)
    {
        switch (true) {
            case true === $value:
                return 'true';

            case false === $value:
                return 'false';

            case null === $value:
                return 'null';

            case is_numeric($value):
                return $value;

            case \is_array($value):
                if ($this->isHash($value)) {
                    $str = '{';

                    foreach ($value as $key => $v) {
                        if (\is_int($key)) {
                            $str .= sprintf('%s: %s, ', $key, $this->formatResult($v));
                        } else {
                            $str .= sprintf('"%s": %s, ', $this->dumpEscaped($key), $this->formatResult($v));
                        }
                    }

                    return rtrim($str, ', ').'}';
                }

                $str = '[';

                foreach ($value as $key => $v) {
                    $str .= sprintf('%s, ', $this->formatResult($v));
                }

                return rtrim($str, ', ').']';

            case \is_object($value):
                /** @var string $value */
                $value = $this->convertObjectToString($value);

                return sprintf('"%s"', $this->dumpEscaped($value));

            default:
                return sprintf('"%s"', $this->dumpEscaped($value));
        }
    }

    protected function convertObjectToString($value)
    {
        if (method_exists($value, '__toString')) {
            return $value->__toString();
        }

        if ($value instanceof Crawler) {
            return $value->html();
        }

        return sprintf('(object) "%s"', \get_class($value));
    }

    protected function isHash(array $value)
    {
        $expectedKey = 0;

        foreach ($value as $key => $val) {
            if ($key !== $expectedKey++) {
                return true;
            }
        }

        return false;
    }

    protected function dumpEscaped($value)
    {
        return str_replace(['\\', '"'], ['\\\\', '\"'], $value);
    }
}
