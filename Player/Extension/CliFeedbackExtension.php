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

use Blackfire\Player\Build\Build;
use Blackfire\Player\Exception\ExpectationFailureException;
use Blackfire\Player\Exception\NonFatalException;
use Blackfire\Player\Player;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\ScenarioResult;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\ScenarioSetResult;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\BlockStep;
use Blackfire\Player\Step\RequestStep;
use Blackfire\Player\Step\Step;
use Blackfire\Player\Step\StepContext;
use Symfony\Component\Console\Helper\Dumper;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class CliFeedbackExtension implements ScenarioSetExtensionInterface, ScenarioExtensionInterface, StepExtensionInterface, ExceptionExtensionInterface
{
    private int $scenarioCount;
    private int $stepCount;
    private int $stepDeep;
    private int $stepIndex;
    private int $failureCount;
    private bool $concurrency = false;
    private array $debugLines = [];

    private bool $debug;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly Dumper $dumper,
        private readonly int $terminalWidth,
    ) {
        $this->debug = $output->isVerbose();
    }

    public function beforeScenarioSet(ScenarioSet $scenarios, int $concurrency): void
    {
        $msg = sprintf('Blackfire Player %s', Player::version());

        $this->concurrency = ($concurrency > 1);
        if ($this->concurrency) {
            $msg .= sprintf(' - concurrency %d', $concurrency);
        }
        $this->output->writeln(sprintf('<fg=blue>%s</>', $msg));

        $this->scenarioCount = 0;
        $this->stepCount = 0;
        $this->failureCount = 0;
    }

    public function beforeScenario(Scenario $scenario, ScenarioContext $scenarioContext): void
    {
        ++$this->scenarioCount;
        $this->stepIndex = 0;
        $this->stepDeep = 0;

        $this->output->writeln('');
        $this->output->writeln(sprintf('<fg=blue>Scenario</> <title> %s </>', $scenario->getName() ?: $scenarioContext->getExtraValue('_index')));
    }

    public function beforeStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
        if ($step instanceof Scenario) {
            return;
        }
        if ($step instanceof Step) {
            ++$this->stepCount;
        }
        if (!$step instanceof RequestStep) {
            ++$this->stepIndex;
        }
        ++$this->stepDeep;

        $indent = $this->stepDeep > 0 ? str_repeat('  ', $this->stepDeep) : '';
        if ($step instanceof RequestStep) {
            $request = $step->getRequest();
            $name = sprintf('%s %s', $request->method, $request->uri);
            if (!$this->debug && (\strlen($name) - 3) > $this->terminalWidth) {
                $name = substr($name, 0, $this->terminalWidth - 3).'...';
            }
        } elseif ($name = $step->getName()) {
            $name = sprintf('<title>%s%s </>', $indent, $name);
        } else {
            $name = sprintf('%s[%s %d]', $indent, $step->getType(), $this->stepIndex);
        }

        $this->debug($this->linePrefix($scenarioContext).$name."\n");
    }

    public function afterStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
        if ($step instanceof Scenario) {
            return;
        }

        $this->clearLevelDebug($this->stepDeep);

        --$this->stepDeep;
        if (!$step instanceof Step) {
            return;
        }

        $variables = $scenarioContext->getVariableValues($stepContext, true);
        $dumpValues = $step->getDumpValuesName();
        $response = $scenarioContext->hasPreviousResponse() ? $scenarioContext->getLastResponse() : null;
        if ($response && \in_array('request', $dumpValues, true)) {
            $this->output->write(sprintf("<debug>request:</>\n%s\n", $response->request->toString()));
        }
        if ($response && \in_array('response', $dumpValues, true)) {
            $this->output->write(sprintf("<debug>response:</>\n%s\n", $response->toString()));
        }
        foreach ($dumpValues as $varName) {
            if ('request' === $varName || 'response' === $varName) {
                continue;
            }
            if (\array_key_exists($varName, $variables)) {
                $this->output->write(sprintf('<debug>%s:</> ', $varName));
                $this->dump($variables[$varName]);
            } else {
                throw new \InvalidArgumentException(sprintf('Could not dump "%s" as the variable is not defined.', $varName));
            }
        }

        if ($step->hasFailingExpectation()) {
            $this->printErrors($step, $step->getFailingExpectations());
        }
        if ($step->hasFailingAssertion()) {
            $this->printErrors($step, $step->getFailingAssertions());
        }
        if ($step->hasError()) {
            $this->printErrors($step, $step->getErrors());
        }
    }

    public function failStep(AbstractStep $step, \Throwable $exception): void
    {
        $this->printErrors($step, [$exception->getMessage()]);

        if ($exception instanceof ExpectationFailureException) {
            $this->printExpectationsFailure($exception);
        }
    }

    public function afterScenario(Scenario $scenario, ScenarioContext $scenarioContext, ScenarioResult $scenarioResult): void
    {
        // iterate over the scenario steps
        // if one of the steps has errors, we add an error to the ScenarioResult
        $scenarioErrors = iterator_to_array($this->searchForStepsWithErrors($scenario));
        if (null === $scenarioResult->getError() && $scenarioErrors) {
            $scenarioResult->setError(new NonFatalException(implode("\n", $scenarioErrors)));
        }

        if (!$scenarioResult->isErrored()) {
            $this->output->writeln($this->linePrefix($scenarioContext).'<success> </> OK');
        }

        /** @var Build $build */
        $build = $scenarioContext->getExtraValue('build');

        // render the current build URL, if any
        if ($build && $build->url) {
            $this->output->writeln(sprintf('Blackfire Report at <comment>%s</>', $build->url));
        }
    }

    public function afterScenarioSet(ScenarioSet $scenarios, int $concurrency, ScenarioSetResult $scenarioSetResult): void
    {
        $this->output->writeln('');

        $summary = sprintf('Scenarios <detail> %d </> - Steps <detail> %d </>', $this->scenarioCount, $this->stepCount);
        if ($scenarioSetResult->isErrored()) {
            $this->output->writeln(sprintf('<failure> KO </> %s - Failures <failure> %d </>', $summary, $this->failureCount));
        } else {
            $this->output->writeln(sprintf('<success> OK </> %s', $summary));
        }
    }

    private function printErrors(AbstractStep $step, array $errors): void
    {
        $this->clearAllDebug();

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

    private function debug(string $line): void
    {
        if ($this->concurrency || $this->debug || !$this->output->isDecorated()) {
            $this->output->write($line);
            if (!str_ends_with($line, "\n")) {
                $this->output->write("\n");
            }

            return;
        }

        $this->output->write("\r\033[K"); // clear the actual line
        $this->output->write($line);

        $this->debugLines[$this->stepDeep] ??= 0;
        $this->debugLines[$this->stepDeep] += substr_count($line, "\n");
    }

    private function clearAllDebug(): void
    {
        if ($this->concurrency || $this->debug || !$this->output->isDecorated()) {
            return;
        }

        foreach (array_reverse(array_keys($this->debugLines)) as $level) {
            $this->clearLevelDebug($level);
        }
    }

    private function clearLevelDebug(int $level): void
    {
        if ($this->concurrency || $this->debug || !$this->output->isDecorated()) {
            return;
        }

        $this->output->write("\r\033[K"); // clear the actual line
        while (($this->debugLines[$level] ?? 0) > 0) {
            $this->output->write("\033[1A\033[K");  // move the cursor up and clear the line
            --$this->debugLines[$level];
        }
        unset($this->debugLines[$level]);
    }

    private function printExpectationsFailure(ExpectationFailureException $exception): void
    {
        foreach ($exception->getResults() as $result) {
            $this->output->writeln(sprintf('<failure> </>   └ %s = %s', $result['expression'], $result['result']));
        }
    }

    private function linePrefix(ScenarioContext $scenarioContext): string
    {
        if ($this->concurrency) {
            return sprintf('<fg=blue>Scenario</> <title> %s </> ', $scenarioContext->getName() ?: $scenarioContext->getExtraValue('_index'));
        }

        return '';
    }

    private function dump(mixed $var): void
    {
        $this->output->writeln(($this->dumper)($var));
    }

    /**
     * Steps with errors are steps having failures or exceptions.
     */
    private function searchForStepsWithErrors(AbstractStep $step): iterable
    {
        yield from $step->getFailingAssertions();

        yield from $step->getFailingExpectations();

        yield from $step->getErrors();

        $generatedSteps = $step->getGeneratedSteps();
        foreach ($generatedSteps as $generatedStep) {
            yield from $this->searchForStepsWithErrors($generatedStep);
        }

        if ($step instanceof BlockStep) {
            $childrenSteps = $step->getSteps();
            /** @var AbstractStep $childStep */
            foreach ($childrenSteps as $childStep) {
                yield from $this->searchForStepsWithErrors($childStep);
            }
        }
    }
}
