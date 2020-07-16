<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\ExpressionLanguage;

use Blackfire\Player\ExpressionLanguage\VariableFormatter;
use PHPUnit\Framework\TestCase;

class VariableFormatterTest extends TestCase
{
    /**
     * @dataProvider getFormatResultData
     */
    public function testFormatResult($value, $expectedString)
    {
        $formatter = new VariableFormatter();

        $actual = $formatter->formatResult($value);

        $this->assertEquals($expectedString, $actual);
    }

    public function getFormatResultData()
    {
        yield [null, 'null'];
        yield [true, 'true'];
        yield [false, 'false'];
        yield [12, '12'];
        yield [0, '0'];
        yield ['foo', '"foo"'];
        yield [[1, 2, 3], '[1, 2, 3]'];
        yield [[[1, 2], [3, 4]], '[[1, 2], [3, 4]]'];
        yield [new \stdClass(), '"(object) \"stdClass\""'];
    }
}
