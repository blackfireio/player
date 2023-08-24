<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Extension;

use Blackfire\Player\Context;
use Blackfire\Player\Exception\ExpectationFailureException;
use Blackfire\Player\Extension\JUnitExtension;
use Blackfire\Player\Result;
use Blackfire\Player\Results;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\Step;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class JUnitExtensionTest extends TestCase
{
    public function testCreatingReport()
    {
        $extension = new JUnitExtension();
        $set = new ScenarioSet();

        $scenario = new Scenario('scenario 1');
        $scenario->name('Example scenario');
        $stepSucceed = new Step();
        $stepSucceed->name('Successful step');
        $stepSucceed->assert('main.wall_time < 50ms');
        $stepSucceed->expect('status_code() == 200');

        $stepFailedAssertion = new Step();
        $stepFailedAssertion->name('Assertion failed');
        $stepFailedAssertion->assert('main.wall_time < 5ms');
        $stepFailedAssertion->addError('Example assertion error');

        $stepFailedExpectation = new Step();
        $stepFailedExpectation->name('Expectation failed');
        $stepFailedExpectation->expect('status_code() == 400');
        $expectationException = new ExpectationFailureException('Expectation failed');

        $stepFailedException = new Step();
        $stepFailedException->name('Other exception');
        $exception = new \Exception('Some exception');

        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $context = $this->createMock(Context::class);
        $result = $this->createMock(Result::class);
        $results = $this->createMock(Results::class);

        $extension->enterScenarioSet($set, 1);
        $extension->enterScenario($scenario, $context);
        $extension->enterStep($stepSucceed, $request, $context);
        $extension->leaveStep($stepSucceed, $request, $response, $context);
        $extension->enterStep($stepFailedAssertion, $request, $context);
        $extension->leaveStep($stepFailedAssertion, $request, $response, $context);
        $extension->enterStep($stepFailedExpectation, $request, $context);
        $extension->abortStep($stepFailedExpectation, $request, $expectationException, $context);
        $extension->enterStep($stepFailedException, $request, $context);
        $extension->abortStep($stepFailedException, $request, $exception, $context);
        $extension->leaveScenario($scenario, $result, $context);
        $extension->leaveScenarioSet($set, $results);

        self::assertSame(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites name="" errors="1" failures="2" tests="4">
  <testsuite name="Example scenario" errors="1" failures="2" tests="4">
    <testcase name="Successful step" assertions="2"/>
    <testcase name="Assertion failed" assertions="1">
      <failure message="Example assertion error" type="performance assertion"/>
    </testcase>
    <testcase name="Expectation failed" assertions="1">
      <failure message="Expectation failed" type="expectation"/>
    </testcase>
    <testcase name="Other exception" assertions="0">
      <error message="Some exception" type="Exception"/>
    </testcase>
  </testsuite>
</testsuites>

XML
            , $extension->getXml());
    }
}
