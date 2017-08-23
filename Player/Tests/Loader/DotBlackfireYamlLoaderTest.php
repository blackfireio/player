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

use Blackfire\Player\Loader\DotBlackfireYamlLoader;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\VisitStep;

class DotBlackfireYamlLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider resourcesProvider
     */
    public function testSupports($resource, $expected)
    {
        $loader = new DotBlackfireYamlLoader();

        $this->assertEquals($expected, $loader->supports($resource));
    }

    public function resourcesProvider()
    {
        yield ['does_not_exist', false];
        yield [__DIR__.'/../fixtures/bkf/all.bkf', false];
        yield [__DIR__.'/../fixtures/blackfire.yml/.blackfire.yml', true];
    }

    public function testLoadValidFile()
    {
        $loader = new DotBlackfireYamlLoader();
        $scenarioSet = $loader->load(__DIR__.'/../fixtures/blackfire.yml/.blackfire.yml');

        $this->assertInstanceOf(ScenarioSet::class, $scenarioSet);
        $this->assertCount(5, $scenarioSet);

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[0];
        $this->assertEquals('Pricing page', $scenario->getKey());
        $this->assertInstanceOf(VisitStep::class, $scenario->getBlockStep());
        $this->assertEquals([], $scenario->getVariables());
        $this->assertEquals("'POST'", $scenario->getBlockStep()->getMethod());
        $this->assertEquals(0, $scenario->getBlockStep()->getSamples());
        $this->assertEquals('true', $scenario->getBlockStep()->getWarmup());

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[1];
        $this->assertEquals('Integrations page', $scenario->getKey());
        $this->assertInstanceOf(VisitStep::class, $scenario->getBlockStep());
        $this->assertEquals([], $scenario->getVariables());
        $this->assertEquals(null, $scenario->getBlockStep()->getMethod());
        $this->assertEquals(0, $scenario->getBlockStep()->getSamples());
        $this->assertEquals('true', $scenario->getBlockStep()->getWarmup());

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[2];
        $this->assertEquals('Blackfire.yml Validator', $scenario->getKey());
        $this->assertInstanceOf(VisitStep::class, $scenario->getBlockStep());
        $this->assertEquals([], $scenario->getVariables());
        $this->assertEquals('\'POST\'', $scenario->getBlockStep()->getMethod());
        $this->assertEquals(10, $scenario->getBlockStep()->getSamples());
        $this->assertEquals('true', $scenario->getBlockStep()->getWarmup());
        $this->assertEquals([
            '\'accept: application/json\'',
        ], $scenario->getBlockStep()->getHeaders());

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[3];
        $this->assertEquals('Homepage', $scenario->getKey());
        $this->assertInstanceOf(VisitStep::class, $scenario->getBlockStep());
        $this->assertEquals([], $scenario->getVariables());
        $this->assertEquals(null, $scenario->getBlockStep()->getMethod());
        $this->assertEquals(0, $scenario->getBlockStep()->getSamples());
        $this->assertEquals(null, $scenario->getBlockStep()->getWarmup());

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[4];
        $this->assertEquals('Documentation', $scenario->getKey());
        $this->assertInstanceOf(VisitStep::class, $scenario->getBlockStep());
        $this->assertEquals([], $scenario->getVariables());
        $this->assertEquals(null, $scenario->getBlockStep()->getMethod());
        $this->assertEquals(0, $scenario->getBlockStep()->getSamples());
        $this->assertEquals('false', $scenario->getBlockStep()->getWarmup());
    }

    /**
     * @expectedException \Blackfire\Player\Exception\LoaderException
     */
    public function testLoadInvalidFile()
    {
        $loader = new DotBlackfireYamlLoader();
        $loader->load(__DIR__.'/../fixtures/blackfire.yml/empty/.blackfire.yml');
    }
}
