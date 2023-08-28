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

use Blackfire\Player\Exception\ExpressionSyntaxErrorException;
use Blackfire\Player\Exception\InvalidArgumentException;
use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\Exception\SyntaxErrorException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\BlockStep;
use Blackfire\Player\Step\ClickStep;
use Blackfire\Player\Step\ConditionStep;
use Blackfire\Player\Step\ConfigurableStep;
use Blackfire\Player\Step\EmptyStep;
use Blackfire\Player\Step\FollowStep;
use Blackfire\Player\Step\LoopStep;
use Blackfire\Player\Step\ReloadStep;
use Blackfire\Player\Step\Step;
use Blackfire\Player\Step\SubmitStep;
use Blackfire\Player\Step\VisitStep;
use Blackfire\Player\Step\WhileStep;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\Yaml\Parser as YamlParser;
use Webmozart\Glob\Glob;
use Webmozart\PathUtil\Path;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class Parser
{
    private const DEPRECATION_ENV_RESOLVING = 'Resolving an environment at the scenario level using the "blackfire" property is deprecated. Please use `--blackfire-env` instead. %s.';
    public const REGEX_NAME = '[a-zA-Z_\x7f-\xff][\-a-zA-Z0-9_\x7f-\xff]*';

    private const KEYWORD_ENDPOINT = 'endpoint';
    private const KEYWORD_BLACKFIRE_ENV = 'blackfire-env';

    private bool $inAGroup;
    /** @var string[] */
    private array $variables;
    /** @var string[] */
    private array $globalVariables = [];
    /** @var string[] */
    private array $missingVariables = [];
    private array $groups;
    private ?string $name = null;

    public function __construct(
        private readonly ExpressionLanguage $expressionLanguage,
        /** @var string[] */
        private readonly array $externalVariables = [],
        private readonly bool $allowMissingVariables = false,
    ) {
    }

    /**
     * @param string|resource $file
     */
    public function load(mixed $file): ScenarioSet
    {
        // Groups are global to all files
        $this->groups = [];

        return $this->doLoad($file);
    }

    /**
     * @param string|resource $file
     */
    protected function doLoad(mixed $file): ScenarioSet
    {
        if (\is_resource($file)) {
            fseek($file, 0);

            return $this->parse(stream_get_contents($file));
        }

        if (!is_file($file)) {
            throw new InvalidArgumentException(sprintf('File "%s" does not exist.', $file));
        }

        $extension = pathinfo($file, \PATHINFO_EXTENSION);
        if ('yml' === $extension || 'yaml' === $extension) {
            $input = (new YamlParser())->parseFile($file);
            if (!isset($input['scenarios'])) {
                throw new InvalidArgumentException(sprintf('File "%s" should have a "scenarios" entry but none was found.', $file));
            }
            $input = $input['scenarios'];
        } elseif ('bkf' !== $extension) {
            throw new InvalidArgumentException(sprintf('Cannot load file "%s" because it does not have the right extension. Expected "bkf", got "%s".', $file, $extension));
        } else {
            $input = file_get_contents($file);
        }

        return $this->parse($input, $file);
    }

    public function parse(string $input, string $file = null): ScenarioSet
    {
        $input = new Input($input, $file);

        // Variables are scoped to the current file
        $this->variables = [];

        $scenarios = new ScenarioSet();
        while (!$input->isEof()) {
            $this->inAGroup = false;

            $step = $this->parseStep($input);

            if ($step instanceof ScenarioSet) {
                $scenarios->addScenarioSet($step);
            } elseif ($step instanceof Scenario) {
                $scenarios->add($step);
            }
        }

        $scenarios->name($this->name);
        $scenarios->setVariables($this->getGlobalVariables());

        $endpoint = $this->getGlobalVariables()[self::KEYWORD_ENDPOINT] ?? '';
        $blackfireEnv = $this->getGlobalVariables()[self::KEYWORD_BLACKFIRE_ENV] ?? null;
        $scenarios->setEndpoint($endpoint);
        $scenarios->setBlackfireEnvironment($blackfireEnv);

        foreach ($scenarios as $scenario) {
            if (!$scenario->getEndpoint()) {
                $scenario->endpoint($endpoint);
            }
        }

        return $scenarios;
    }

    public function getGlobalVariables(): array
    {
        return array_replace($this->globalVariables, $this->externalVariables);
    }

    public function getMissingVariables(): array
    {
        return $this->missingVariables;
    }

    private function parseSteps(Input $input, int $expectedIndent): AbstractStep
    {
        $root = new EmptyStep();
        $current = null;
        while (!$input->isEof()) {
            $nextIndent = $input->getNextLineIndent();
            if ($nextIndent < $expectedIndent) {
                // finished
                return $root;
            }
            if ($nextIndent > $expectedIndent) {
                throw new SyntaxErrorException(sprintf('Indentation too wide %s.', $input->getContextString()));
            }

            $step = $this->parseStep($input, $expectedIndent);

            if (null === $current) {
                $root = $step;
            } else {
                $current->next($step);
            }

            $current = $step;
        }

        return $root;
    }

    private function parseStep(Input $input, int $expectedIndent = 0): ScenarioSet|AbstractStep
    {
        $line = $input->getNextLine();

        if ($input->getIndent() !== $expectedIndent) {
            throw new SyntaxErrorException(sprintf('Indentation is wrong %s.', $input->getContextString()));
        }

        if (!preg_match('/^('.self::REGEX_NAME.')(?:\s+(.+)$|$)/', $line, $matches)) {
            throw new SyntaxErrorException(sprintf('Unable to parse "%s" %s.', $line, $input->getContextString()));
        }

        $keyword = $matches[1];
        $arguments = $matches[2] ?? null;

        if ('load' === $keyword) {
            if ($expectedIndent > 0) {
                throw new SyntaxErrorException(sprintf('A "load" can only be defined at root %s.', $input->getContextString()));
            }

            if (null === $arguments) {
                throw new SyntaxErrorException(sprintf('A "load" takes a file pattern as a required argument %s.', $input->getContextString()));
            }

            if (!preg_match('{^("|\')(.+?)\1$}', $arguments, $matches)) {
                throw new SyntaxErrorException(sprintf('"load" takes a quoted string as an argument %s.', $input->getContextString()));
            }

            $glob = Path::makeAbsolute($matches[2], realpath(\dirname((string) $input->getFile())));
            $paths = Glob::glob($glob);

            if (!$paths) {
                throw new InvalidArgumentException(sprintf('File "%s" does not exist.', $glob));
            }

            $scenarios = new ScenarioSet();
            foreach ($paths as $path) {
                if (realpath($path) === realpath((string) $input->getFile())) {
                    continue;
                }

                $scenarios->addScenarioSet($this->doLoad($path));
            }

            return $scenarios;
        }

        if (self::KEYWORD_BLACKFIRE_ENV === $keyword) {
            if ($expectedIndent > 0) {
                throw new SyntaxErrorException(sprintf('A "%s" can only be defined at root %s.', self::KEYWORD_BLACKFIRE_ENV, $input->getContextString()));
            }

            if (null === $arguments) {
                throw new SyntaxErrorException(sprintf('A "%s" takes either an env UUID or an environment name as a required argument %s.', self::KEYWORD_BLACKFIRE_ENV, $input->getContextString()));
            }

            $this->globalVariables[self::KEYWORD_BLACKFIRE_ENV] = $arguments;

            $step = new EmptyStep();
        } elseif (self::KEYWORD_ENDPOINT === $keyword) {
            if ($expectedIndent > 0) {
                throw new SyntaxErrorException(sprintf('An "%s" can only be defined at root %s.', self::KEYWORD_ENDPOINT, $input->getContextString()));
            }

            if (null === $arguments) {
                throw new SyntaxErrorException(sprintf('An "%s" takes a URL as a required argument %s.', self::KEYWORD_ENDPOINT, $input->getContextString()));
            }

            $this->globalVariables[self::KEYWORD_ENDPOINT] = $arguments;

            $step = new EmptyStep();
        } elseif ('name' === $keyword) {
            if ($expectedIndent > 0) {
                throw new SyntaxErrorException(sprintf('A "name" can only be defined at root %s.', $input->getContextString()));
            }

            if (null === $arguments) {
                throw new SyntaxErrorException(sprintf('A "name" takes an expression as a required argument %s.', $input->getContextString()));
            }

            $this->name = $arguments;

            $step = new EmptyStep();
        } elseif ('scenario' === $keyword) {
            if ($expectedIndent > 0) {
                throw new SyntaxErrorException(sprintf('A "scenario" can only be defined at root %s.', $input->getContextString()));
            }

            $step = new Scenario($arguments, $input->getFile(), $input->getLine());
            $this->parseStepConfig($input, $step, $expectedIndent + 1, true);
            $step->setBlockStep($this->parseSteps($input, $expectedIndent + 1));

            return $step;
        } elseif ('group' === $keyword) {
            if ($expectedIndent > 0) {
                throw new SyntaxErrorException(sprintf('A "group" can only be defined at root %s.', $input->getContextString()));
            }

            if (null === $arguments) {
                throw new SyntaxErrorException(sprintf('A "group" takes a name as a required argument %s.', $input->getContextString()));
            }

            $step = new BlockStep($input->getFile(), $input->getLine());
            $this->inAGroup = true;
            $this->groups[$arguments] = $step;
            $this->parseStepConfig($input, $step, $expectedIndent + 1, true);
            $step->setBlockStep($this->parseSteps($input, $expectedIndent + 1));

            return $step;
        } elseif ('block' === $keyword) {
            if (null !== $arguments) {
                throw new SyntaxErrorException(sprintf('A "block" does not take any argument %s.', $input->getContextString()));
            }

            $step = new BlockStep($input->getFile(), $input->getLine());
            $this->parseStepConfig($input, $step, $expectedIndent + 1, true);
            $step->setBlockStep($this->parseSteps($input, $expectedIndent + 1));

            return $step;
        } elseif ('visit' === $keyword) {
            if (null === $arguments) {
                throw new SyntaxErrorException(sprintf('A "visit" takes an expression as a required argument %s.', $input->getContextString()));
            }

            $step = new VisitStep($this->checkExpression($input, $arguments), $input->getFile(), $input->getLine());
        } elseif ('click' === $keyword) {
            if (null === $arguments) {
                throw new SyntaxErrorException(sprintf('A "click" takes an expression as a required argument %s.', $input->getContextString()));
            }

            $step = new ClickStep($this->checkExpression($input, $arguments), $input->getFile(), $input->getLine());
        } elseif ('submit' === $keyword) {
            if (null === $arguments) {
                throw new SyntaxErrorException(sprintf('A "submit" takes an expression as a required argument %s.', $input->getContextString()));
            }

            $step = new SubmitStep($this->checkExpression($input, $arguments), $input->getFile(), $input->getLine());
        } elseif ('include' === $keyword) {
            if (null === $arguments) {
                throw new SyntaxErrorException(sprintf('An "include" takes an expression as a required argument %s.', $input->getContextString()));
            }

            if (!isset($this->groups[$arguments])) {
                throw new LogicException(sprintf('Block "%s" does not exist.', $arguments));
            }

            $step = clone $this->groups[$arguments];
            $this->parseStepConfig($input, $step, $expectedIndent + 1, true);
            $block = $this->parseSteps($input, $expectedIndent + 1);
            if (!$block instanceof EmptyStep) {
                if ($step->getBlockStep() instanceof EmptyStep) {
                    $step->setBlockStep($block);
                } else {
                    $step->getBlockStep()->next($block);
                }
            }

            return $step;
        } elseif ('follow' === $keyword) {
            if (null !== $arguments) {
                throw new SyntaxErrorException(sprintf('A "follow" does not take any argument %s.', $input->getContextString()));
            }

            $step = new FollowStep($input->getFile(), $input->getLine());
        } elseif ('reload' === $keyword) {
            if (null !== $arguments) {
                throw new SyntaxErrorException(sprintf('A "reload" does not take any argument %s.', $input->getContextString()));
            }

            $step = new ReloadStep($input->getFile(), $input->getLine());
        } elseif ('when' === $keyword) {
            if (null === $arguments) {
                throw new SyntaxErrorException(sprintf('An "when" takes an expression as a required argument %s.', $input->getContextString()));
            }

            $step = new ConditionStep($this->checkExpression($input, $arguments), $input->getFile(), $input->getLine());
            $this->parseStepConfig($input, $step, $expectedIndent + 1, true);
            $childStep = $this->parseSteps($input, $expectedIndent + 1);
            if (!$childStep->getNext()) {
                $step->setIfStep($childStep);
            } else {
                $blockStep = new BlockStep();
                $blockStep->setBlockStep($childStep);
                $step->setIfStep($blockStep);
            }

            return $step;
        } elseif ('while' === $keyword) {
            if (null === $arguments) {
                throw new SyntaxErrorException(sprintf('An "while" takes an expression as a required argument %s.', $input->getContextString()));
            }

            $step = new WhileStep($this->checkExpression($input, $arguments), $input->getFile(), $input->getLine());
            $this->parseStepConfig($input, $step, $expectedIndent + 1, true);
            $childStep = $this->parseSteps($input, $expectedIndent + 1);
            if (!$childStep->getNext()) {
                $step->setWhileStep($childStep);
            } else {
                $blockStep = new BlockStep();
                $blockStep->setBlockStep($childStep);
                $step->setWhileStep($blockStep);
            }

            return $step;
        } elseif ('with' === $keyword) {
            if (null === $arguments) {
                throw new SyntaxErrorException(sprintf('An "with" takes an expression as a required argument %s.', $input->getContextString()));
            }

            // valueName in values
            // keyName, valueName in values
            if (!preg_match('/^(.+)\s+in\s+(.+)$/', $arguments, $matches)) {
                throw new SyntaxErrorException(sprintf('A "with" step value must be like "url in urls" %s.', $input->getContextString()));
            }

            $values = $matches[2];
            $keyName = '_';
            $valueName = $matches[1];

            if (preg_match('/^(.+),\s*(.+)$/', $valueName, $matches)) {
                $keyName = $matches[1];
                $valueName = $matches[2];
            }

            $keyNameExists = isset($this->variables[$keyName]);
            $valueNameExists = isset($this->variables[$valueName]);

            $this->variables[$keyName] = $keyName;
            $this->variables[$valueName] = $valueName;

            $step = new LoopStep($this->checkExpression($input, $values), $keyName, $valueName, $input->getFile(), $input->getLine());
            $this->parseStepConfig($input, $step, $expectedIndent + 1, true);
            $childStep = $this->parseSteps($input, $expectedIndent + 1);
            if (!$childStep->getNext()) {
                $step->setLoopStep($childStep);
            } else {
                $blockStep = new BlockStep();
                $blockStep->setBlockStep($childStep);
                $step->setLoopStep($blockStep);
            }

            if (!$keyNameExists) {
                unset($this->variables[$keyName]);
            }

            if (!$valueNameExists) {
                unset($this->variables[$valueName]);
            }

            return $step;
        } elseif ('set' === $keyword) {
            if ($expectedIndent > 0) {
                throw new LogicException(sprintf('A "set" can only be defined before steps %s.', $input->getContextString()));
            }
            if (null === $arguments) {
                throw new SyntaxErrorException(sprintf('A "set" takes an argument %s.', $input->getContextString()));
            }

            if (!preg_match('/^('.self::REGEX_NAME.')\s+(.+)$/', $arguments, $matches)) {
                throw new SyntaxErrorException(sprintf('Unable to parse "expect" arguments "%s" %s.', $arguments, $input->getContextString()));
            }

            if (\array_key_exists($matches[1], $this->globalVariables)) {
                throw new LogicException(sprintf('You cannot redeclare the global variable "%s" %s', $matches[1], $input->getContextString()));
            }

            $this->globalVariables[$matches[1]] = $matches[2];

            return new EmptyStep($input->getFile(), $input->getLine());
        } else {
            throw new SyntaxErrorException(sprintf('Unknown keyword "%s" %s.', $keyword, $input->getContextString()));
        }

        $this->parseStepConfig($input, $step, $expectedIndent + 1);

        return $step;
    }

    private function parseStepConfig(Input $input, AbstractStep $step, int $expectedIndent, bool $ignoreInvalid = false): void
    {
        while (!$input->isEof()) {
            $nextIndent = $input->getNextLineIndent();

            // step is finished
            if ($nextIndent < $expectedIndent) {
                return;
            }

            if ($nextIndent > $expectedIndent) {
                throw new SyntaxErrorException(sprintf('Indentation too wide %s.', $input->getContextString()));
            }

            if (!$step instanceof ConfigurableStep) {
                throw new SyntaxErrorException('Cannot configure a non configurable step.');
            }

            $line = $input->getNextLine();

            if (!preg_match('/^('.self::REGEX_NAME.')(?:\s+(.+)$|$)/', $line, $matches)) {
                if ($ignoreInvalid) {
                    $input->rewindLine();

                    return;
                }

                throw new SyntaxErrorException(sprintf('Unable to parse "%s" %s.', $line, $input->getContextString()));
            }

            $keyword = $matches[1];
            $arguments = $matches[2] ?? null;

            if ('name' === $keyword) {
                if (null === $arguments) {
                    throw new SyntaxErrorException(sprintf('A "name" takes an expression as a required argument %s.', $input->getContextString()));
                }

                $step->name($this->checkExpression($input, $arguments));
            } elseif ('expect' === $keyword) {
                if (!$step instanceof Step) {
                    throw new LogicException(sprintf('"expect" is not available for step "%s" %s.', $this->formatStepType($step), $input->getContextString()));
                }

                if (null === $arguments) {
                    throw new SyntaxErrorException(sprintf('An "expect" takes an expectation as a required argument %s.', $input->getContextString()));
                }

                $step->expect($this->checkExpression($input, $arguments));
            } elseif ('assert' === $keyword) {
                if (!$step instanceof Step) {
                    throw new LogicException(sprintf('"assert" is not available for step "%s" %s.', $this->formatStepType($step), $input->getContextString()));
                }

                if (null === $arguments) {
                    throw new SyntaxErrorException(sprintf('An "assert" takes an assertion as a required argument %s.', $input->getContextString()));
                }

                $step->assert($arguments);
            } elseif ('set' === $keyword) {
                if (!$step instanceof Step && !$step instanceof BlockStep) {
                    throw new LogicException(sprintf('"set" is not available for step "%s" %s.', $this->formatStepType($step), $input->getContextString()));
                }

                if (null === $arguments) {
                    throw new SyntaxErrorException(sprintf('A "set" takes a name and a value as required arguments %s.', $input->getContextString()));
                }

                if (!preg_match('/^('.self::REGEX_NAME.')\s+(.+)$/', $arguments, $matches)) {
                    throw new SyntaxErrorException(sprintf('Unable to parse "set" arguments "%s" %s.', $arguments, $input->getContextString()));
                }

                if ($step->has($matches[1])) {
                    throw new LogicException(sprintf('You cannot redeclare the variable "%s" %s', $matches[1], $input->getContextString()));
                }

                $this->variables[$matches[1]] = $matches[1];
                $step->set($matches[1], $this->checkExpression($input, $matches[2]));
            } elseif ('header' === $keyword) {
                if (null === $arguments) {
                    throw new SyntaxErrorException(sprintf('A "header" takes an header as a required argument %s.', $input->getContextString()));
                }

                $step->header($this->checkExpression($input, $arguments));
            } elseif ('auth' === $keyword) {
                if (null === $arguments) {
                    throw new SyntaxErrorException(sprintf('A "auth" takes a string of format "username:password" as a required argument %s.', $input->getContextString()));
                }

                $step->auth($this->checkExpression($input, $arguments));
            } elseif ('wait' === $keyword) {
                if (null === $arguments) {
                    throw new SyntaxErrorException(sprintf('A "wait" takes an expression as a required argument %s.', $input->getContextString()));
                }

                $step->wait($this->checkExpression($input, $arguments));
            } elseif ('follow_redirects' === $keyword) {
                $step->followRedirects(null !== $arguments ? $this->checkExpression($input, $arguments) : 'true');
            } elseif ('blackfire' === $keyword) {
                $step->blackfire(null !== $arguments ? $this->checkExpression($input, $arguments) : 'true');
                if (!\in_array($step->getBlackfire(), ['true', 'false'], true)) {
                    // if the `blackfire` keyword match anything than true or false, we are trying to resolve an environment.
                    SentrySupport::captureMessage('blackfire property used to resolve the blackfire environment');
                    // $this->output->writeln(sprintf('<warning>%s</warning>', self::DEPRECATION_ENV_RESOLVING));
                    $step->addDeprecation(sprintf(self::DEPRECATION_ENV_RESOLVING, $input->getContextString()));
                }
            } elseif ('json' === $keyword) {
                $step->json(null !== $arguments ? $this->checkExpression($input, $arguments) : 'true');
            } elseif ('samples' === $keyword) {
                if (null === $arguments) {
                    throw new SyntaxErrorException(sprintf('A "samples" takes a number as a required argument %s.', $input->getContextString()));
                }

                $step->samples($this->checkExpression($input, $arguments));
            } elseif ('warmup' === $keyword) {
                $step->warmup(null !== $arguments ? $this->checkExpression($input, $arguments) : 'true');
            } elseif ('body' === $keyword) {
                if (!$step instanceof VisitStep && !$step instanceof SubmitStep) {
                    throw new LogicException(sprintf('"param" is only available for "visit" or "submit" steps %s.', $input->getContextString()));
                }

                if (null === $arguments) {
                    throw new SyntaxErrorException(sprintf('A "body" takes a string as a required argument %s.', $input->getContextString()));
                }

                $step->body($this->checkExpression($input, $arguments));
            } elseif ('param' === $keyword) {
                if (!$step instanceof VisitStep && !$step instanceof SubmitStep) {
                    throw new LogicException(sprintf('"param" is only available for "visit" or "submit" steps %s.', $input->getContextString()));
                }

                if (null === $arguments) {
                    throw new SyntaxErrorException(sprintf('A "param" takes a required argument %s.', $input->getContextString()));
                }

                if (!preg_match('/^([^\s]+)\s+(.+)$/', $arguments, $matches)) {
                    throw new SyntaxErrorException(sprintf('Unable to parse "param" arguments "%s" %s.', $arguments, $input->getContextString()));
                }

                $step->param($matches[1], $this->checkExpression($input, $matches[2]));
            } elseif ('method' === $keyword) {
                if (!$step instanceof VisitStep) {
                    throw new LogicException(sprintf('"method" is only available for "visit" steps %s.', $input->getContextString()));
                }

                if (null === $arguments) {
                    throw new SyntaxErrorException(sprintf('A "method" takes an HTTP verb as a required argument %s.', $input->getContextString()));
                }

                $step->method($this->checkExpression($input, $arguments));
            } elseif ('endpoint' === $keyword) {
                if (!$step instanceof BlockStep) {
                    throw new LogicException(sprintf('"endpoint" is only available for "scenario", "group", or "block" steps %s.', $input->getContextString()));
                }

                if (null === $arguments) {
                    throw new SyntaxErrorException(sprintf('An "endpoint" takes a URL as a required argument %s.', $input->getContextString()));
                }

                $step->endpoint($this->checkExpression($input, $arguments));
            } elseif ('dump' === $keyword) {
                if (!$step instanceof Step) {
                    throw new LogicException(sprintf('"dump" is not available for step "%s" %s.', $this->formatStepType($step), $input->getContextString()));
                }

                if (null === $arguments) {
                    throw new SyntaxErrorException(sprintf('A "dump" takes a required argument %s.', $input->getContextString()));
                }

                $step->setDumpValuesName(explode(' ', $arguments));
            } elseif ($ignoreInvalid) {
                // step configuration is finished
                $input->rewindLine();

                return;
            } else {
                throw new SyntaxErrorException(sprintf('Unknown keyword "%s" %s.', $keyword, $input->getContextString()));
            }
        }
    }

    private function checkExpression(Input $input, string $expression): string
    {
        // for groups, expressions will be checked in context
        if ($this->inAGroup) {
            return $expression;
        }

        // We add the "endpoint" and "blackfire-env" variables to be able to use it anywhere. The
        // value could be injected later, during the parsing or directly in the
        // scenarios via the CLI
        $variables = array_replace([
            self::KEYWORD_ENDPOINT => null,
            self::KEYWORD_BLACKFIRE_ENV => null,
        ], $this->globalVariables, $this->externalVariables, $this->variables);

        try {
            $missingVariables = $this->expressionLanguage->checkExpression($expression, array_keys($variables), $this->allowMissingVariables);
            $this->missingVariables = array_unique(array_merge($this->missingVariables, $missingVariables));
        } catch (SyntaxError $e) {
            $position = strpos($input->getCurrentLine(), $expression);
            $error = preg_replace('/around position (\d+)\./', 'around position '.$position, $e->getMessage());

            // Detect an undefined variable to provide a more accurate error
            if (preg_match('/Variable "[^"]+" is not valid/', $e->getMessage())) {
                throw new ExpressionSyntaxErrorException(sprintf('%s

Did you forget to declare it?
You can declare it in your file using the "set" option, or with the "--variable" CLI option.
If the Player is run through a Blackfire server, you can declare it in the "Variables" panel of the "Builds" tab.
', $e->getMessage()));
            }

            throw new ExpressionSyntaxErrorException(sprintf('Expression syntax error: %s %s.', $error, $input->getContextString()));
        }

        return $expression;
    }

    private function formatStepType(AbstractStep $step): string
    {
        return strtolower((new \ReflectionClass($step))->getShortName());
    }
}
