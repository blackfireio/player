<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Serializer;

use Blackfire\Player\Parser;
use Blackfire\Player\Serializer\ScenarioSetSerializer;
use PHPUnit\Framework\TestCase;

class ScenarioSetSerializerTest extends TestCase
{
    /** @dataProvider provideScenarioAndSerializations */
    public function testSerialization(string $scenarioFile, string $expectedFile)
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse(file_get_contents($scenarioFile));

        $serializer = new ScenarioSetSerializer();
        $serialized = $serializer->serialize($scenarioSet);

        if (getenv('UPDATE_FIXTURES')) {
            file_put_contents($expectedFile, $serialized);
        }

        $this->assertStringMatchesFormat(file_get_contents($expectedFile), $serialized);
    }

    public function provideScenarioAndSerializations()
    {
        yield [__DIR__.'/fixtures/test1.bkf', __DIR__.'/fixtures/test1.json'];
        yield [__DIR__.'/fixtures/test2.bkf', __DIR__.'/fixtures/test2.json'];
        yield [__DIR__.'/fixtures/test3.bkf', __DIR__.'/fixtures/test3.json'];
    }
}
