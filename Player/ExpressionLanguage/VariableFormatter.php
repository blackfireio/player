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
        if (true === $value) {
            return 'true';
        }
        if (false === $value) {
            return 'false';
        }
        if (null === $value) {
            return 'null';
        }
        if (is_numeric($value)) {
            return $value;
        }
        if (\is_array($value)) {
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
        }
        if (\is_object($value)) {
            $value = $this->convertObjectToString($value);

            return Json::encode($value);
        }

        return Json::encode($value);
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
