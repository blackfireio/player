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
use Blackfire\Player\ExpressionLanguage\Provider as LanguageProvider;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\BlockStep;
use Blackfire\Player\Step\ClickStep;
use Blackfire\Player\Step\ConditionStep;
use Blackfire\Player\Step\EmptyStep;
use Blackfire\Player\Step\FollowStep;
use Blackfire\Player\Step\LoopStep;
use Blackfire\Player\Step\ReloadStep;
use Blackfire\Player\Step\Step;
use Blackfire\Player\Step\SubmitStep;
use Blackfire\Player\Step\VisitStep;
use Blackfire\Player\Step\WhileStep;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Webmozart\Glob\Glob;
use Webmozart\PathUtil\Path;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class Parser
{
    const REGEX_NAME = '[a-zA-Z_\x7f-\xff][\-a-zA-Z0-9_\x7f-\xff]*';

    private $inAGroup;
    private $variables;
    private $globalVariables;
    private $groups;
    private $expressionLanguage;

    public function __construct(array $globalVariables = [])
    {
        $this->expressionLanguage = new ExpressionLanguage(null, [new LanguageProvider()]);
        $this->globalVariables = $globalVariables;
    }

    /**
     * @return ScenarioSet
     */
    public function load($file)
    {
        if (!is_file($file) && 'php://stdin' !== $file) {
            throw new InvalidArgumentException(sprintf('File "%s" does not exist.', $file));
        }

        $extension = pathinfo($file, PATHINFO_EXTENSION);
        if ('bkf' !== $extension && 'php://stdin' !== $file) {
            throw new InvalidArgumentException(sprintf('Cannot load file "%s" because it does not have the right extension. Expected "bkf", got "%s".', $file, $extension));
        }

        return $this->parse(file_get_contents($file), $file);
    }

    public function parse($input, $file = null)
    {
        $input = new Input($input, $file);

        $this->variables = [];
        $this->groups = [];

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

        return $scenarios;
    }

    public function getGlobalVariables()
    {
        return $this->globalVariables;
    }

    private function parseSteps(Input $input, $expectedIndent)
    {
        $root = new EmptyStep();
        $current = null;
        while (!$input->isEof()) {
            $nextIndent = $input->getNextLineIndent();
            if ($nextIndent < $expectedIndent) {
                // finished
                return $root;
            } elseif ($nextIndent > $expectedIndent) {
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

    private function parseStep(Input $input, $expectedIndent = 0)
    {
        $line = $input->getNextLine();

        if ($input->getIndent() !== $expectedIndent) {
            throw new SyntaxErrorException(sprintf('Indentation is wrong %s.', $input->getContextString()));
        }

        if (!preg_match('/^('.self::REGEX_NAME.')(?:\s+(.+)$|$)/', $line, $matches)) {
            throw new SyntaxErrorException(sprintf('Unable to parse "%s" %s.', $line, $input->getContextString()));
        }

        $keyword = $matches[1];
        $hasArgs = isset($matches[2]);
        $arguments = isset($matches[2]) ? $matches[2] : null;

        if ('load' === $keyword) {
            if ($expectedIndent > 0) {
                throw new SyntaxErrorException(sprintf('A "load" can only be defined at root %s.', $input->getContextString()));
            }

            if (!$hasArgs) {
                throw new SyntaxErrorException(sprintf('A "load" takes a file pattern as a required argument %s.', $input->getContextString()));
            }

            if (!preg_match('{^("|\')(.+?)\1$}', $arguments, $matches)) {
                throw new SyntaxErrorException(sprintf('"load" takes a quoted string as an argument %s.', $input->getContextString()));
            }

            $glob = Path::makeAbsolute($matches[2], realpath(\dirname($input->getFile())));
            $paths = Glob::glob($glob);

            if (!$paths) {
                throw new \InvalidArgumentException(sprintf('File "%s" does not exist.', $glob));
            }

            $scenarios = new ScenarioSet();
            foreach ($paths as $path) {
                if (realpath($path) === realpath($input->getFile())) {
                    continue;
                }

                $scenarios->addScenarioSet($this->load($path));
            }

            return $scenarios;
        } elseif ('endpoint' === $keyword) {
            if ($expectedIndent > 0) {
                throw new SyntaxErrorException(sprintf('An "endpoint" can only be defined at root %s.', $input->getContextString()));
            }

            if (!$hasArgs) {
                throw new SyntaxErrorException(sprintf('An "endpoint" takes a URL as a required argument %s.', $input->getContextString()));
            }

            $this->globalVariables['endpoint'] = $arguments;

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

            if (!$hasArgs) {
                throw new SyntaxErrorException(sprintf('A "group" takes a name as a required argument %s.', $input->getContextString()));
            }

            $step = new BlockStep($input->getFile(), $input->getLine());
            $this->inAGroup = true;
            $this->groups[$arguments] = $step;
            $this->parseStepConfig($input, $step, $expectedIndent + 1, true);
            $step->setBlockStep($this->parseSteps($input, $expectedIndent + 1));

            return $step;
        } elseif ('block' === $keyword) {
            if ($hasArgs) {
                throw new SyntaxErrorException(sprintf('A "block" does not take any argument %s.', $input->getContextString()));
            }

            $step = new BlockStep($input->getFile(), $input->getLine());
            $this->parseStepConfig($input, $step, $expectedIndent + 1, true);
            $step->setBlockStep($this->parseSteps($input, $expectedIndent + 1));

            return $step;
        } elseif ('visit' === $keyword) {
            if (!$hasArgs) {
                throw new SyntaxErrorException(sprintf('A "visit" takes an expression as a required argument %s.', $input->getContextString()));
            }

            $step = new VisitStep($this->checkExpression($input, $arguments), $input->getFile(), $input->getLine());
        } elseif ('click' === $keyword) {
            if (!$hasArgs) {
                throw new SyntaxErrorException(sprintf('A "click" takes an expression as a required argument %s.', $input->getContextString()));
            }

            $step = new ClickStep($this->checkExpression($input, $arguments), $input->getFile(), $input->getLine());
        } elseif ('submit' === $keyword) {
            if (!$hasArgs) {
                throw new SyntaxErrorException(sprintf('A "submit" takes an expression as a required argument %s.', $input->getContextString()));
            }

            $step = new SubmitStep($this->checkExpression($input, $arguments), $input->getFile(), $input->getLine());
        } elseif ('include' === $keyword) {
            if (!$hasArgs) {
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
            if ($hasArgs) {
                throw new SyntaxErrorException(sprintf('A "follow" does not take any argument %s.', $input->getContextString()));
            }

            $step = new FollowStep($input->getFile(), $input->getLine());
        } elseif ('reload' === $keyword) {
            if ($hasArgs) {
                throw new SyntaxErrorException(sprintf('A "reload" does not take any argument %s.', $input->getContextString()));
            }

            $step = new ReloadStep($input->getFile(), $input->getLine());
        } elseif ('when' === $keyword) {
            if (!$hasArgs) {
                throw new SyntaxErrorException(sprintf('An "when" takes an expression as a required argument %s.', $input->getContextString()));
            }

            $step = new ConditionStep($this->checkExpression($input, $arguments), $input->getFile(), $input->getLine());
            $this->parseStepConfig($input, $step, $expectedIndent + 1, true);
            $step->setIfStep($this->parseSteps($input, $expectedIndent + 1));

            return $step;
        } elseif ('while' === $keyword) {
            if (!$hasArgs) {
                throw new SyntaxErrorException(sprintf('An "while" takes an expression as a required argument %s.', $input->getContextString()));
            }

            $step = new WhileStep($this->checkExpression($input, $arguments), $input->getFile(), $input->getLine());
            $this->parseStepConfig($input, $step, $expectedIndent + 1, true);
            $step->setWhileStep($this->parseSteps($input, $expectedIndent + 1));

            return $step;
        } elseif ('with' === $keyword) {
            if (!$hasArgs) {
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
            $step->setLoopStep($this->parseSteps($input, $expectedIndent + 1));

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

            if (!preg_match('/^('.self::REGEX_NAME.')\s+(.+)$/', $arguments, $matches)) {
                throw new SyntaxErrorException(sprintf('Unable to parse "expect" arguments "%s" %s.', $arguments, $input->getContextString()));
            }

            if (array_key_exists($matches[1], $this->globalVariables)) {
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

    private function parseStepConfig(Input $input, AbstractStep $step, $expectedIndent, $ignoreInvalid = false)
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

            $line = $input->getNextLine();

            if (!preg_match('/^('.self::REGEX_NAME.')(?:\s+(.+)$|$)/', $line, $matches)) {
                if ($ignoreInvalid) {
                    $input->rewindLine();

                    return;
                }

                throw new SyntaxErrorException(sprintf('Unable to parse "%s" %s.', $line, $input->getContextString()));
            }

            $keyword = $matches[1];
            $hasArgs = isset($matches[2]);
            $arguments = isset($matches[2]) ? $matches[2] : null;

            if ('name' === $keyword) {
                if (!$hasArgs) {
                    throw new SyntaxErrorException(sprintf('A "name" takes an expression as a required argument %s.', $input->getContextString()));
                }

                $step->name($this->checkExpression($input, $arguments));
            } elseif ('expect' === $keyword) {
                if (!$step instanceof Step) {
                    throw new LogicException(sprintf('"expect" is not available for step "%s" %s.', $this->formatStepType($step), $input->getContextString()));
                }

                if (!$hasArgs) {
                    throw new SyntaxErrorException(sprintf('An "expect" takes an expectation as a required argument %s.', $input->getContextString()));
                }

                $step->expect($this->checkExpression($input, $arguments));
            } elseif ('assert' === $keyword) {
                if (!$step instanceof Step) {
                    throw new LogicException(sprintf('"assert" is not available for step "%s" %s.', $this->formatStepType($step), $input->getContextString()));
                }

                if (!$hasArgs) {
                    throw new SyntaxErrorException(sprintf('An "assert" takes an assertion as a required argument %s.', $input->getContextString()));
                }

                $step->assert($arguments);
            } elseif ('set' === $keyword) {
                if (!$step instanceof Step && !$step instanceof BlockStep) {
                    throw new LogicException(sprintf('"set" is not available for step "%s" %s.', $this->formatStepType($step), $input->getContextString()));
                }

                if (!$hasArgs) {
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
                if (!$hasArgs) {
                    throw new SyntaxErrorException(sprintf('A "header" takes an header as a required argument %s.', $input->getContextString()));
                }

                $step->header($this->checkExpression($input, $arguments));
            } elseif ('auth' === $keyword) {
                if (!$hasArgs) {
                    throw new SyntaxErrorException(sprintf('A "auth" takes a string of format "username:password" as a required argument %s.', $input->getContextString()));
                }

                $step->auth($this->checkExpression($input, $arguments));
            } elseif ('wait' === $keyword) {
                if (!$hasArgs) {
                    throw new SyntaxErrorException(sprintf('A "wait" takes an expression as a required argument %s.', $input->getContextString()));
                }

                $step->wait($this->checkExpression($input, $arguments));
            } elseif ('follow_redirects' === $keyword) {
                $step->followRedirects($hasArgs ? $this->checkExpression($input, $arguments) : 'true');
            } elseif ('blackfire' === $keyword) {
                $step->blackfire($hasArgs ? $this->checkExpression($input, $arguments) : 'true');
            } elseif ('blackfire-build' === $keyword || 'blackfire-scenario' === $keyword) { // Internal keywords: do not use
                if (!$hasArgs) {
                    throw new SyntaxErrorException(sprintf('A "%s" takes a scenario uuid as a required argument %s.', $keyword, $input->getContextString()));
                }

                $step->blackfireScenario($this->checkExpression($input, $arguments));
            } elseif ('blackfire-request' === $keyword) { // Internal keywords: do not use
                if (!$hasArgs) {
                    throw new SyntaxErrorException(sprintf('A "blackfire-request" takes a request uuid as a required argument %s.', $input->getContextString()));
                }

                $step->blackfireRequest($this->checkExpression($input, $arguments));
            } elseif ('json' === $keyword) {
                $step->json($hasArgs ? $this->checkExpression($input, $arguments) : 'true');
            } elseif ('samples' === $keyword) {
                if (!$hasArgs) {
                    throw new SyntaxErrorException(sprintf('A "samples" takes a number as a required argument %s.', $input->getContextString()));
                }

                $step->samples($this->checkExpression($input, $arguments));
            } elseif ('warmup' === $keyword) {
                $step->warmup($hasArgs ? $this->checkExpression($input, $arguments) : 'true');
            } elseif ('body' === $keyword) {
                if (!$step instanceof VisitStep && !$step instanceof SubmitStep) {
                    throw new LogicException(sprintf('"param" is only available for "visit" or "submit" steps %s.', $input->getContextString()));
                }

                if (!$hasArgs) {
                    throw new SyntaxErrorException(sprintf('A "body" takes a string as a required argument %s.', $input->getContextString()));
                }

                $step->body($this->checkExpression($input, $arguments));
            } elseif ('param' === $keyword) {
                if (!$step instanceof VisitStep && !$step instanceof SubmitStep) {
                    throw new LogicException(sprintf('"param" is only available for "visit" or "submit" steps %s.', $input->getContextString()));
                }

                if (!preg_match('/^([^\s]+)\s+(.+)$/', $arguments, $matches)) {
                    throw new SyntaxErrorException(sprintf('Unable to parse "param" arguments "%s" %s.', $arguments, $input->getContextString()));
                }

                $step->param($matches[1], $this->checkExpression($input, $matches[2]));
            } elseif ('method' === $keyword) {
                if (!$step instanceof VisitStep) {
                    throw new LogicException(sprintf('"method" is only available for "visit" steps %s.', $input->getContextString()));
                }

                if (!$hasArgs) {
                    throw new SyntaxErrorException(sprintf('A "method" takes an HTTP verb as a required argument %s.', $input->getContextString()));
                }

                $step->method($this->checkExpression($input, $arguments));
            } elseif ('endpoint' === $keyword) {
                if (!$step instanceof BlockStep) {
                    throw new LogicException(sprintf('"endpoint" is only available for "scenario", "group", or "block" steps %s.', $input->getContextString()));
                }

                if (!$hasArgs) {
                    throw new SyntaxErrorException(sprintf('An "endpoint" takes a URL as a required argument %s.', $input->getContextString()));
                }

                $step->endpoint($this->checkExpression($input, $arguments));
            } elseif ('dump' === $keyword) {
                if (!$step instanceof Step) {
                    throw new LogicException(sprintf('"dump" is not available for step "%s" %s.', $this->formatStepType($step), $input->getContextString()));
                }

                if (!$hasArgs) {
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

    private function checkExpression(Input $input, $expression)
    {
        // for groups, expressions will be checked in context
        if ($this->inAGroup) {
            return $expression;
        }

        // We add the "endpoint" variables to be able to use it anywhere. The
        // value could be injected later, during the parsing or directly in the
        // scenarios via the CLI
        $variables = array_replace(['endpoint' => null], $this->globalVariables, $this->variables);

        try {
            $this->expressionLanguage->compile($expression, array_keys($variables));
        } catch (SyntaxError $e) {
            $position = strpos($input->getCurrentLine(), $expression);
            $error = preg_replace('/around position (\d+)\./', 'around position '.$position, $e->getMessage());

            // Detect an undefined variable to provide a more accurate error
            if (preg_match('/Variable "([^"]+)" is not valid/', $e->getMessage(), $matches)) {
                throw new ExpressionSyntaxErrorException(sprintf(<<<"EOE"
Variable "%s" is not defined %s. Did you forget to declare it ?
You can declare it in your file using the "set" option, or with the "--variable" CLI option.
If the Player is run through a Blackfire server, you can declare it in the "Variables" panel of the "Builds" tab.
EOE
                , $matches[1], $input->getContextString()));
            }

            throw new ExpressionSyntaxErrorException(sprintf('Expression syntax error: %s %s.', $error, $input->getContextString()));
        }

        return $expression;
    }

    private function formatStepType(AbstractStep $step)
    {
        return strtolower((new \ReflectionClass($step))->getShortName());
    }
}
