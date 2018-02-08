<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Psr7;

use Blackfire\Player\Context;
use Blackfire\Player\Exception\ExpressionSyntaxErrorException;
use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\BlockStep;
use Blackfire\Player\Step\ConditionStep;
use Blackfire\Player\Step\ConfigurableStep;
use Blackfire\Player\Step\EmptyStep;
use Blackfire\Player\Step\LoopStep;
use Blackfire\Player\Step\Step;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\WhileStep;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class RequestGenerator implements \IteratorAggregate
{
    private $language;
    private $stepConverter;
    private $step;
    private $context;
    private $request;
    private $response;
    private $contextStack;

    public function __construct(ExpressionLanguage $language, StepConverterInterface $stepConterter, AbstractStep $step, Context $context)
    {
        $this->language = $language;
        $this->stepConverter = $stepConterter;
        $this->step = $step;
        $this->context = $context;
        $this->setContextStack(new \SplStack());
    }

    public function setContext(\SplStack $contextStack, RequestInterface $request = null, ResponseInterface $response = null)
    {
        $this->setContextStack($contextStack);
        $this->request = $request;
        $this->response = $response;
    }

    public function getIterator()
    {
        $request = $this->request;
        $response = $this->response;
        $step = $this->step;

        do {
            $this->enterStep($step);

            if ($step instanceof EmptyStep) {
            } elseif ($step instanceof ConditionStep) {
                if ($this->evaluateExpression($step->getCondition(), $request)) {
                    $iter = $this->createIterator($step->getIfStep(), $request, $response);
                    $gen = $iter->getIterator();
                    do {
                        list($request, $response) = $this->checkGeneratorResult(yield $gen->key() => $gen->current());
                    } while ($gen->send([$request, $response]));
                } elseif ($step->getElseStep()) {
                    $iter = $this->createIterator($step->getElseStep(), $request, $response);
                    $gen = $iter->getIterator();
                    do {
                        list($request, $response) = $this->checkGeneratorResult(yield $gen->key() => $gen->current());
                    } while ($gen->send([$request, $response]));
                }
            } elseif ($step instanceof WhileStep) {
                while (true) {
                    if (!$this->evaluateExpression($step->getCondition(), $request)) {
                        break;
                    }

                    $iter = $this->createIterator($step->getWhileStep(), $request, $response);
                    $gen = $iter->getIterator();
                    do {
                        list($request, $response) = $this->checkGeneratorResult(yield $gen->key() => $gen->current());
                    } while ($gen->send([$request, $response]));
                }
            } elseif ($step instanceof LoopStep) {
                $iterator = $this->evaluateExpression($step->getIterator(), $request);
                foreach ($iterator as $key => $value) {
                    $this->contextStack->top()->variable($step->getKeyName(), $key);
                    $this->contextStack->top()->variable($step->getValueName(), $value);

                    $iter = $this->createIterator(clone $step->getLoopStep(), $request, $response);
                    $gen = $iter->getIterator();
                    do {
                        list($request, $response) = $this->checkGeneratorResult(yield $gen->key() => $gen->current());
                    } while ($gen->send([$request, $response]));
                }
            } elseif ($step instanceof BlockStep) {
                $iter = $this->createIterator($step->getBlockStep(), $request, $response);
                $gen = $iter->getIterator();
                do {
                    list($request, $response) = $this->checkGeneratorResult(yield $gen->key() => $gen->current());
                } while ($gen->send([$request, $response]));
            } elseif ($step instanceof Step) {
                $request = $this->stepConverter->createRequest($step, $request, $response);
                list($request, $response) = $this->checkGeneratorResult(yield $step => $request);

                $this->context->setRequestResponse($request, $response);
            } else {
                throw new LogicException(sprintf('Unsupported step "%s".', \get_class($step)));
            }

            $this->leaveStep($step);
        } while ($step = $step->getNext());
    }

    private function evaluateExpression($expression, RequestInterface $request = null)
    {
        $variables = $this->context->getVariableValues(true);

        try {
            return $this->language->evaluate($expression, $variables);
        } catch (SyntaxError $e) {
            throw new ExpressionSyntaxErrorException(sprintf('Expression syntax error in "%s": %s', $expression, $e->getMessage()));
        }
    }

    private function enterStep(AbstractStep $step)
    {
        if (!$step instanceof ConfigurableStep) {
            return;
        }

        if ($this->contextStack->isEmpty()) {
            $context = new StepContext();
        } else {
            $context = clone $this->contextStack->top();
        }

        // evaluate variables first
        $variables = [];
        if ($step instanceof BlockStep) {
            foreach ($step->getVariables() as $key => $value) {
                $variables[$key] = $this->language->evaluate($value, $variables);
            }
        }

        $context->update($step, $variables);

        $this->contextStack->push($context);
    }

    private function leaveStep(AbstractStep $step)
    {
        if (!$step instanceof ConfigurableStep) {
            return;
        }

        $this->contextStack->pop();
    }

    private function createIterator(AbstractStep $step, RequestInterface $request = null, ResponseInterface $response = null)
    {
        $iter = new self($this->language, $this->stepConverter, $step, $this->context);
        $iter->setContext($this->contextStack, $request, $response);

        return $iter;
    }

    private function checkGeneratorResult($result)
    {
        if (!$result[0] instanceof RequestInterface) {
            throw new \LogicException('A PSR-7 RequestInterface instance must be returned to the request generator.');
        }

        if (!$result[1] instanceof ResponseInterface) {
            throw new \LogicException('A PSR-7 ResponseInterface instance must be returned to the request generator.');
        }

        return $result;
    }

    private function setContextStack(\SplStack $contextStack)
    {
        $this->contextStack = $contextStack;
        $this->context->setContextStack($this->contextStack);
    }
}
