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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class CliFeedbackExtension extends AbstractExtension
{
    private $output;
    private $scenarioCount;
    private $stepCount;
    private $stepIndex;
    private $failureCount;
    private $dumper;
    private $debug;
    private $terminalWidth;

    public function __construct(OutputInterface $output, $terminalWidth)
    {
        $this->output = $output;
        $this->debug = $output->isVerbose();
        $this->terminalWidth = $terminalWidth;

        $h = fopen('php://memory', 'r+b');
        $dumper = new CliDumper($h);
        $cloner = new VarCloner();
        $this->dumper = function ($var) use ($dumper, $cloner, $h, $output) {
            $dumper->dump($cloner->cloneVar($var));

            $data = stream_get_contents($h, -1, 0);
            rewind($h);
            ftruncate($h, 0);

            $output->writeln(rtrim($data));
        };
    }

    public function enterScenarioSet(ScenarioSet $scenarios, $concurrency)
    {
        $msg = 'Blackfire Player';
        if ($concurrency > 1) {
            $msg .= sprintf(' - concurrency %d', $concurrency);
        }
        $this->output->writeln(sprintf('<fg=blue>%s</>', $msg));

        $this->scenarioCount = 0;
        $this->stepCount = 0;
        $this->failureCount = 0;
    }

    public function enterScenario(Scenario $scenario, Context $context)
    {
        ++$this->scenarioCount;
        $this->stepIndex = 0;

        $this->output->writeln('');
        $this->output->writeln(sprintf('<fg=blue>Scenario</> <title> %s </>', $scenario->getName() ?: '~Untitled~'));
    }

    public function enterStep(AbstractStep $step, RequestInterface $request, Context $context)
    {
        ++$this->stepCount;
        ++$this->stepIndex;

        if ($name = $step->getName()) {
            $name = sprintf('<title> %s </>', $name);
        } else {
            $name = sprintf('Step %d', $this->stepIndex);
        }
        $this->output->writeln($name);

        $line = sprintf('%s %s', $request->getMethod(), $request->getUri());
        if (!$this->debug && (\strlen($line) - 3) > $this->terminalWidth) {
            $line = substr($line, 0, $this->terminalWidth - 3).'...';
        }
        $this->output->write($line);

        return $request;
    }

    public function leaveStep(AbstractStep $step, RequestInterface $request, ResponseInterface $response, Context $context)
    {
        if ($this->debug || !$this->output->isDecorated()) {
            $this->output->writeln('');
        } else {
            $this->output->write("\r\033[K\033[1A\033[K");
        }

        if (!$step instanceof Step) {
            return $response;
        }

        foreach ($step->getDumpValuesName() as $varName) {
            if ('request' === $varName) {
                $this->output->write(sprintf("<debug>request:</>\n%s\n", \GuzzleHttp\Psr7\str($request)));
            } elseif ('response' === $varName) {
                $this->output->write(sprintf("<debug>response:</>\n%s\n", \GuzzleHttp\Psr7\str($response)));
            } elseif (\array_key_exists($varName, $context->getVariableValues())) {
                $this->output->write(sprintf('<debug>%s:</> ', $varName));
                $dump = $this->dumper;
                $dump($context->getVariableValues()[$varName]);
            } else {
                throw new \InvalidArgumentException(sprintf('Could not dump "%s" as the variable is not defined.', $varName));
            }
        }

        if ($step->hasErrors()) {
            $this->printErrors($step, $step->getErrors());
        }

        return $response;
    }

    public function abortStep(AbstractStep $step, RequestInterface $request, \Exception $exception, Context $context)
    {
        $this->printErrors($step, [$exception->getMessage()]);

        if ($exception instanceof ExpectationFailureException) {
            $this->printExpectationsFailure($exception);
        }
    }

    public function abortScenario(Scenario $scenario, \Exception $exception, Context $context)
    {
        $this->printErrors($scenario, [$exception->getMessage()]);

        if ($exception instanceof ExpectationFailureException) {
            $this->printExpectationsFailure($exception);
        }
    }

    private function printErrors(AbstractStep $step, array $errors)
    {
        if ($this->debug || !$this->output->isDecorated()) {
            $this->output->write("\n");
        } else {
            $this->output->write("\r\033[K\033[1A\033[K");
        }

        ++$this->failureCount;

        $ctx = $step instanceof Scenario ? 'Failure on scenario' : 'Failure on step';

        $name = $step->getName();
        if ($name) {
            $ctx .= sprintf(' <title> %s </>', $name);
        }

        if ($step->getFile()) {
            $ctx .= sprintf(' defined in <title>%s</> at line <title> %d </>', $step->getFile(), $step->getLine());
        } elseif ($step->getLine()) {
            $ctx .= sprintf(' defined at line <title>%d</>', $step->getLine());
        }

        $this->output->writeln(sprintf('<failure> </> %s', $ctx));

        foreach ($errors as $error) {
            $lines = explode("\n", $error);

            $this->output->writeln(sprintf('<failure> </> └ <failure>%s</>', array_shift($lines)));
            foreach ($lines as $line) {
                $this->output->writeln(sprintf('<failure> </>   %s', $line));
            }
        }
    }

    private function printExpectationsFailure(ExpectationFailureException $exception)
    {
        foreach ($exception->getResults() as $result) {
            $this->output->writeln(sprintf('<failure> </>   └ %s = %s', $result['expression'], $result['result']));
        }
    }

    public function leaveScenario(Scenario $scenario, Result $result, Context $context)
    {
        if (!$result->isErrored()) {
            $this->output->writeln(sprintf('<success> </> OK'));
        }
    }

    public function leaveScenarioSet(ScenarioSet $scenarios, Results $results)
    {
        $this->output->writeln('');

        $summary = sprintf('Scenarios <detail> %d </> - Steps <detail> %d </>', $this->scenarioCount, $this->stepCount);
        if ($results->isErrored()) {
            $this->output->writeln(sprintf('<failure> KO </> %s - Failures <failure> %d </>', $summary, $this->failureCount));
        } else {
            $this->output->writeln(sprintf('<success> OK </> %s', $summary));
        }
    }
}
