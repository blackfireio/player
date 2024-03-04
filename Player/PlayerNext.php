<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player;

use Blackfire\Player\Enum\BuildStatus;
use Blackfire\Player\Extension\ExceptionExtensionInterface;
use Blackfire\Player\Extension\NextStepExtensionInterface;
use Blackfire\Player\Extension\ScenarioExtensionInterface;
use Blackfire\Player\Extension\ScenarioSetExtensionInterface;
use Blackfire\Player\Extension\StepExtensionInterface;
use Blackfire\Player\Reporter\JsonViewReporter;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\ConfigurableStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\StepProcessor\StepContextFactory;
use Blackfire\Player\StepProcessor\StepProcessorInterface;
use Blackfire\Player\StepProcessor\VariablesEvaluator;

/**
 * @internal
 */
class PlayerNext
{
    /** @var (StepExtensionInterface|ScenarioExtensionInterface|ScenarioSetExtensionInterface|NextStepExtensionInterface|ExceptionExtensionInterface)[][] */
    private array $extensions = [];
    private ?array $extensionsSorted = null;
    private ?AbstractStep $currentStep = null;

    public function __construct(
        private readonly StepContextFactory $stepContextFactory,
        private readonly JsonViewReporter $reporter,
        private readonly StepProcessorInterface $stepProcessor,
        private readonly VariablesEvaluator $variablesEvaluator,
    ) {
    }

    public function addExtension(StepExtensionInterface|ScenarioExtensionInterface|ScenarioSetExtensionInterface|NextStepExtensionInterface|ExceptionExtensionInterface $extension, int $priority = 0): void
    {
        $this->extensions[$priority][] = $extension;
        $this->extensionsSorted = null;
    }

    public function run(ScenarioSet $scenarioSet, int $concurrency): ScenarioSetResult
    {
        foreach ($this->getSortedExtensions() as $extension) {
            if ($extension instanceof ScenarioSetExtensionInterface) {
                $extension->beforeScenarioSet($scenarioSet, $concurrency);
            }
        }

        $scenarioSetResult = new ScenarioSetResult();

        $fibers = [];
        foreach ($scenarioSet as $index => $scenario) {
            $fibers[uniqid('', true)] = new \Fiber(fn () => $this->handleScenario($index, $scenario, $scenarioSet));
        }

        $runningFibers = 0;
        $concurrency = max($concurrency, 1);
        while (!empty($fibers)) {
            $loopStart = microtime(true);
            foreach ($fibers as $key => $fiber) {
                if (!$fiber->isStarted()) {
                    if ($runningFibers >= $concurrency) {
                        break;
                    }

                    $fiber->start();
                    ++$runningFibers;
                } elseif ($fiber->isTerminated()) {
                    $scenarioSetResult->add($fiber->getReturn());
                    unset($fibers[$key]);
                    --$runningFibers;
                } elseif ($fiber->isSuspended()) {
                    $fiber->resume();
                }
            }

            $elapsed = (microtime(true) - $loopStart) * 1_000_000;
            $sleep = 10_000 - $elapsed;
            if ($sleep > 0) {
                usleep((int) $sleep);
            }
        }

        foreach ($this->getSortedExtensions() as $extension) {
            if ($extension instanceof ScenarioSetExtensionInterface) {
                $extension->afterScenarioSet($scenarioSet, $concurrency, $scenarioSetResult);
            }
        }

        return $scenarioSetResult;
    }

    private function handleScenario(int|string $index, Scenario $scenario, ScenarioSet $scenarioSet): ScenarioResult
    {
        $scenarioContext = new ScenarioContext($scenario->getName(), $scenarioSet);
        $scenarioContext->setExtraValue('_index', $index);

        foreach ($this->getSortedExtensions() as $extension) {
            if ($extension instanceof ScenarioExtensionInterface) {
                $extension->beforeScenario($scenario, $scenarioContext);
            }
        }

        $exception = null;
        try {
            $this->handleStep(
                $scenario,
                $this->stepContextFactory->createStepContext($scenario, new StepContext()),
                $scenarioContext,
                $scenarioSet
            );
        } catch (\Throwable $e) {
            $exception = $e;
            foreach ($this->getSortedExtensions() as $extension) {
                if ($extension instanceof ExceptionExtensionInterface) {
                    $extension->failStep($this->currentStep, $e);
                }
            }
        }

        $scenarioResult = new ScenarioResult($scenarioContext, $exception);
        foreach ($this->getSortedExtensions() as $extension) {
            if ($extension instanceof ScenarioExtensionInterface) {
                $extension->afterScenario($scenario, $scenarioContext, $scenarioResult);
            }
        }

        return $scenarioResult;
    }

    private function handleStep(ConfigurableStep $step, StepContext $stepContext, ScenarioContext $scenarioContext, ScenarioSet $scenarioSet): void
    {
        $step->setStatus(BuildStatus::IN_PROGRESS);

        try {
            $this->currentStep = $step;
            foreach ($this->getSortedExtensions() as $extension) {
                if ($extension instanceof NextStepExtensionInterface) {
                    foreach ($extension->getPreviousSteps($step, $stepContext, $scenarioContext) as $childStep) {
                        if (!$childStep instanceof ConfigurableStep) {
                            // skip empty steps
                            continue;
                        }
                        $step->addGeneratedStep($childStep);

                        $this->handleStep(
                            $childStep,
                            $this->stepContextFactory->createStepContext($childStep, $stepContext),
                            $scenarioContext,
                            $scenarioSet
                        );
                    }
                }
            }

            foreach ($this->getSortedExtensions() as $extension) {
                if ($extension instanceof StepExtensionInterface) {
                    $extension->beforeStep($step, $stepContext, $scenarioContext);
                }
            }

            $this->reporter->report($scenarioSet);

            foreach ($this->stepProcessor->process($step, $stepContext, $scenarioContext) as $childStep) {
                if (!$childStep instanceof ConfigurableStep) {
                    // skip empty steps
                    continue;
                }
                $step->addGeneratedStep($childStep);

                $this->handleStep(
                    $childStep,
                    $this->stepContextFactory->createStepContext($childStep, $stepContext, $scenarioContext),
                    $scenarioContext,
                    $scenarioSet
                );
            }

            foreach ($this->getSortedExtensions() as $extension) {
                if ($extension instanceof StepExtensionInterface) {
                    $extension->afterStep($step, $stepContext, $scenarioContext);
                }
            }

            foreach ($this->getSortedExtensions() as $extension) {
                if ($extension instanceof NextStepExtensionInterface) {
                    foreach ($extension->getNextSteps($step, $stepContext, $scenarioContext) as $childStep) {
                        if (!$childStep instanceof ConfigurableStep) {
                            // skip empty steps
                            continue;
                        }
                        $step->addGeneratedStep($childStep);

                        $this->handleStep(
                            $childStep,
                            $this->stepContextFactory->createStepContext($childStep, $stepContext),
                            $scenarioContext,
                            $scenarioSet
                        );
                    }
                }
            }
        } finally {
            $step->setStatus(BuildStatus::DONE);

            $this->variablesEvaluator->evaluate($step, $stepContext, $scenarioContext);

            $this->reporter->report($scenarioSet);
        }
    }

    /**
     * Provides the extensions list sorted by priority.
     *
     * @return (StepExtensionInterface|ScenarioExtensionInterface|ScenarioSetExtensionInterface|NextStepExtensionInterface|ExceptionExtensionInterface)[]
     */
    private function getSortedExtensions(): array
    {
        if (null !== $this->extensionsSorted) {
            return $this->extensionsSorted;
        }

        krsort($this->extensions);

        return $this->extensionsSorted = array_merge(...$this->extensions);
    }
}
