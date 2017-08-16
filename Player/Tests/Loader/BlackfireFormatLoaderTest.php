<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Player\Tests\Loader;

use Blackfire\Player\Loader\BlackfireFormatLoader;
use Blackfire\Player\ScenarioSet;

class BlackfireFormatLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider resourcesProvider
     */
    public function testSupports($resource, $expected)
    {
        $loader = new BlackfireFormatLoader();

        $this->assertEquals($expected, $loader->supports($resource));
    }

    public function resourcesProvider()
    {
        yield ['does_not_exist', false];
        yield [__DIR__.'/../fixtures/blackfire.yml/.blackfire.yml', false];
        yield [__DIR__.'/../fixtures/bkf/all.bkf', true];
    }

    public function testLoadValidFile()
    {
        $loader = new BlackfireFormatLoader();
        $scenarioSet = $loader->load(__DIR__.'/../fixtures/bkf/simple/scenario.bkf');

        $this->assertInstanceOf(ScenarioSet::class, $scenarioSet);
        $this->assertCount(1, $scenarioSet);
    }

    /**
     * @expectedException \Blackfire\Player\Exception\LoaderException
     */
    public function testLoadInvalidFile()
    {
        $loader = new BlackfireFormatLoader();
        $loader->load(__DIR__.'/../fixtures/bkf/invalid.bkf');
    }
}
