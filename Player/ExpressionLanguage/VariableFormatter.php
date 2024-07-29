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

use Blackfire\Player\Json;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @internal
 */
class VariableFormatter
{
    public function formatResult(mixed $value): string
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
                if (!array_is_list($value)) {
                    $str = '{';

                    foreach ($value as $key => $v) {
                        $str .= \sprintf('%s: %s, ', Json::encode((string) $key), $this->formatResult($v));
                    }

                    return rtrim($str, ', ').'}';
                }

                $str = '[';

                foreach ($value as $v) {
                    $str .= \sprintf('%s, ', $this->formatResult($v));
                }

                return rtrim($str, ', ').']';

            case \is_object($value):
                $value = $this->convertObjectToString($value);

                return Json::encode($value);

            default:
                return Json::encode($value);
        }
    }

    protected function convertObjectToString(object $value): string
    {
        if (method_exists($value, '__toString')) {
            return $value->__toString();
        }

        if ($value instanceof Crawler) {
            return $value->html();
        }

        return \sprintf('(object) "%s"', $value::class);
    }
}
