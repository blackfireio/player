<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Extension;

use Blackfire\Player\Context;
use Blackfire\Player\Exception\ExpectationFailureException;
use Blackfire\Player\Result;
use Blackfire\Player\Results;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\Step;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @author Marcin Czarnecki <scyzoryck@gmail.com>
 */
class JUnitExtension extends AbstractExtension
{
    /**
     * @var \DOMDocument
     */
    private $document;

    /**
     * @var \DOMElement
     */
    private $currentScenarioSet;

    private $scenarioSetErrorCount = 0;

    private $scenarioSetFailureCount = 0;

    private $scenarioSetTestsCount = 0;

    /**
     * @var \DOMElement
     */
    private $currentScenario;

    private $scenarioErrorCount = 0;

    private $scenarioFailureCount = 0;

    private $scenarioTestsCount = 0;

    /**
     * @var \DOMElement
     */
    private $currentStep;

    public function __construct()
    {
        $this->document = new \DOMDocument('1.0', 'UTF-8');
        $this->document->formatOutput = true;
    }

    public function enterScenarioSet(ScenarioSet $scenarios, $concurrency)
    {
        $this->currentScenarioSet = $this->document->createElement('testsuites');
        $this->currentScenarioSet->setAttribute('name', $scenarios->getName());
        $this->document->appendChild($this->currentScenarioSet);
    }

    public function enterScenario(Scenario $scenario, Context $context)
    {
        $this->currentScenario = $this->document->createElement('testsuite');
        $this->currentScenario->setAttribute('name', $scenario->getName());
        $this->currentScenarioSet->appendChild($this->currentScenario);
    }

    public function enterStep(AbstractStep $step, RequestInterface $request, Context $context)
    {
        $this->currentStep = $this->document->createElement('testcase');
        $this->currentStep->setAttribute('name', $step->getName());
        $this->currentScenario->appendChild($this->currentStep);

        if ($step instanceof Step) {
            $assertionsCount = \count($step->getExpectations()) + \count($step->getAssertions());
        } else {
            $assertionsCount = 0;
        }

        $this->currentStep->setAttribute('assertions', $assertionsCount);

        ++$this->scenarioTestsCount;
        ++$this->scenarioSetTestsCount;

        return $request;
    }

    public function leaveStep(
        AbstractStep $step,
        RequestInterface $request,
        ResponseInterface $response,
        Context $context
    ) {
        foreach ($step->getErrors() as $failedAssertion) {
            $failure = $this->document->createElement('failure');
            $failure->setAttribute('message', $failedAssertion);
            $failure->setAttribute('type', 'performance assertion');
            $this->currentStep->appendChild($failure);

            ++$this->scenarioFailureCount;
            ++$this->scenarioSetFailureCount;
        }

        return $response;
    }

    public function abortStep(AbstractStep $step, RequestInterface $request, \Exception $exception, Context $context)
    {
        if ($exception instanceof ExpectationFailureException) {
            $failure = $this->document->createElement('failure');
            $failure->setAttribute('message', $exception->getMessage());
            $failure->setAttribute('type', 'expectation');
            $this->currentStep->appendChild($failure);

            ++$this->scenarioFailureCount;
            ++$this->scenarioSetFailureCount;

            return;
        }

        ++$this->scenarioErrorCount;
        ++$this->scenarioSetErrorCount;

        $error = $this->document->createElement('error');
        $error->setAttribute('message', $exception->getMessage());
        $error->setAttribute('type', \get_class($exception));
        $this->currentStep->appendChild($error);
    }

    public function leaveScenario(Scenario $scenario, Result $result, Context $context)
    {
        $this->currentScenario->setAttribute('errors', $this->scenarioErrorCount);
        $this->scenarioErrorCount = 0;

        $this->currentScenario->setAttribute('failures', $this->scenarioFailureCount);
        $this->scenarioFailureCount = 0;

        $this->currentScenario->setAttribute('tests', $this->scenarioTestsCount);
        $this->scenarioTestsCount = 0;
    }

    public function leaveScenarioSet(ScenarioSet $scenarios, Results $results)
    {
        $this->currentScenarioSet->setAttribute('errors', $this->scenarioSetErrorCount);
        $this->scenarioSetErrorCount = 0;

        $this->currentScenarioSet->setAttribute('failures', $this->scenarioSetFailureCount);
        $this->scenarioSetFailureCount = 0;

        $this->currentScenarioSet->setAttribute('tests', $this->scenarioSetTestsCount);
        $this->scenarioSetTestsCount = 0;
    }

    public function getXml()
    {
        return $this->document->saveXML();
    }
}
