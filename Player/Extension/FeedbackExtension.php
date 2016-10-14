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

use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Context;
use Blackfire\Player\Result;
use Blackfire\Player\Results;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class FeedbackExtension extends AbstractExtension
{
    private $language;
    private $stream;
    private $scenarioCount;
    private $stepCount;
    private $stepIndex;
    private $failureCount;
    private $concurrency;

    public function __construct(ExpressionLanguage $language, $stream = null)
    {
        $this->language = $language;
        $this->stream = $stream ?: STDOUT;
    }

    public function enterScenarioSet(ScenarioSet $scenarios, $concurrency)
    {
        $msg = 'Blackfire Player';
        if ($concurrency > 1) {
            $msg .= sprintf(' - concurrency %d', $concurrency);
        };
        fwrite($this->stream, sprintf("\033[34m%s\033[39m\n", $msg));

        $this->scenarioCount = 0;
        $this->stepCount = 0;
        $this->failureCount = 0;
        $this->concurrency = $concurrency;
    }

    public function enterScenario(Scenario $scenario, Context $context)
    {
        ++$this->scenarioCount;
        $this->stepIndex = 0;

        fwrite($this->stream, sprintf("\n\033[34mScenario \033[43;30m %s \033[39;49m\n", $scenario->getName()));
    }

    public function enterStep(AbstractStep $step, RequestInterface $request, Context $context)
    {
        ++$this->stepCount;
        ++$this->stepIndex;

        if ($name = $this->getStepName($step, $context)) {
            $name = sprintf("\033[43;30m %s \033[49;39m", $name);
        } else {
            $name = sprintf('Step %d', $this->stepIndex);
        }
        fwrite($this->stream, sprintf("%s\n", $name));
        fwrite($this->stream, sprintf("%s %s", $request->getMethod(), $request->getUri()));

        return $request;
    }

    public function leaveStep(AbstractStep $step, RequestInterface $request, ResponseInterface $response, Context $context)
    {
        fwrite($this->stream, "\r\033[K\033[1A\033[K");

        return $response;
    }

    public function abortStep(AbstractStep $step, RequestInterface $request, \Exception $exception, Context $context)
    {
        ++$this->failureCount;

        $name = $this->getStepName($step, $context);
        $msg = $exception->getMessage();
        $ctx = ' on step';
        if ($name) {
            $ctx .= sprintf(" \033[43;30m %s \033[49;39m", $name);
        }
        if ($step->getFile()) {
            $ctx .= sprintf(" defined in \033[43;30m %s \033[49;39m at line \033[43;30m %d \033[49;39m", $step->getFile(), $step->getLine());
        } elseif ($step->getLine()) {
            $ctx .= sprintf(" defined at line \033[43;30m %d \033[49;39m", $step->getLine());
        }

        fwrite($this->stream, sprintf("\033[41;37m \033[49;39m Failure%s\n", $ctx));
        $lines = explode("\n", $msg);
        fwrite($this->stream, sprintf("\033[41;37m \033[49;39m └ %s\n", array_shift($lines)));
        foreach ($lines as $line) {
            fwrite($this->stream, sprintf("\033[41;37m \033[49;39m %s\n", $line));
        }
    }

    public function leaveScenario(Scenario $scenario, Result $result, Context $context)
    {
        if (!$result->isErrored()) {
            fwrite($this->stream, sprintf("\033[42m \033[49;39m OK\n"));
        }
    }

    public function leaveScenarioSet(ScenarioSet $scenarios, Results $results)
    {
        fwrite($this->stream, "\n");

        $summary = sprintf("Scenarios \033[44;37m %d \033[49;39m - Steps \033[44;37m %d \033[49;39m", $this->scenarioCount, $this->stepCount);
        if ($results->isErrored()) {
            fwrite($this->stream, sprintf("\033[41;37m KO \033[49;39m %s - Failures \033[41;37m %d \033[49;39m\n", $summary, $this->failureCount));
        } else {
            fwrite($this->stream, sprintf("\033[42;30m OK \033[49;39m %s\n", $summary));
        }
    }

    private function getStepName(AbstractStep $step, Context $context)
    {
        if (!$step->getName()) {
            return;
        }

        try {
            return $this->language->evaluate($step->getName(), $context->getVariableValues(true));
        } catch (SyntaxError $e) {
            throw new ExpressionSyntaxErrorException(sprintf('Expression syntax error in "%s": %s', $expression, $e->getMessage()));
        }
    }
}
