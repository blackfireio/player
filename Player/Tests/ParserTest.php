<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Player\Tests;

use Blackfire\Player\Parser;
use Blackfire\Player\Scenario;
use Blackfire\Player\Step\ReloadStep;
use Blackfire\Player\Step\VisitStep;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    public function testParsingSeparatedScenario()
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse(<<<EOF
scenario Test 1
    set env "prod"
    endpoint 'http://toto.com'

    # A comment
    visit url('/blog/')
        expect "prod" == env

scenario Test2
    reload
EOF
);
        $this->assertCount(2, $scenarioSet);

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[0];
        $this->assertEquals('Test 1', $scenario->getKey());
        $this->assertInstanceOf(VisitStep::class, $scenario->getBlockStep());
        $this->assertEquals([
            'env' => '"prod"',
            'endpoint' => '\'http://toto.com\'',
        ], $scenario->getVariables());

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[1];
        $this->assertEquals('Test2', $scenario->getKey());
        $this->assertInstanceOf(ReloadStep::class, $scenario->getBlockStep());
        $this->assertEquals([], $scenario->getVariables());
    }

    public function testParsingGlobalConfiguration()
    {
        $parser = new Parser();
        $scenarioSet = $parser->parse(<<<EOF
set env "prod"
endpoint 'http://toto.com'

scenario Test 1
    # A comment
    visit url('/blog/')
        header "Accept-Language: en-US"
        samples 10
        expect "prod" == env

scenario Test2
    reload
EOF
        );
        $this->assertCount(2, $scenarioSet);

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[0];
        $this->assertEquals('Test 1', $scenario->getKey());
        $this->assertInstanceOf(VisitStep::class, $scenario->getBlockStep());
        $this->assertEquals([
            'env' => '"prod"',
            'endpoint' => '\'http://toto.com\'',
        ], $scenario->getVariables());
        $this->assertEquals([
            '"Accept-Language: en-US"',
        ], $scenario->getBlockStep()->getHeaders());
        $this->assertEquals(10, $scenario->getBlockStep()->getSamples());

        /** @var Scenario $scenario */
        $scenario = $scenarioSet->getIterator()[1];
        $this->assertEquals('Test2', $scenario->getKey());
        $this->assertInstanceOf(ReloadStep::class, $scenario->getBlockStep());
        $this->assertEquals([
            'env' => '"prod"',
            'endpoint' => '\'http://toto.com\'',
        ], $scenario->getVariables());
    }
}
