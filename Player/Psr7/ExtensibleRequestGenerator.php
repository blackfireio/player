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
use Blackfire\Player\Result;
use Blackfire\Player\Scenario;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class ExtensibleRequestGenerator implements \IteratorAggregate
{
    private $generator;
    private $scenario;
    private $context;
    private $extensions;
    private $originalNexts;
    private $result;

    public function __construct(\Generator $generator, Scenario $scenario, Context $context, array $extensions = [])
    {
        $this->generator = $generator;
        $this->scenario = $scenario;
        $this->context = $context;
        $this->extensions = $extensions;
        $this->originalNexts = new \SplObjectStorage();
    }

    public function getIterator()
    {
        foreach ($this->extensions as $extension) {
            $extension->enterScenario($this->scenario, $this->context);
        }

        $exception = null;
        try {
            do {
                $step = $this->generator->key();
                $request = $this->generator->current();

                // empty iterator
                if (null === $request) {
                    break;
                }

                foreach ($this->extensions as $extension) {
                    $request = $extension->enterStep($step, $request, $this->context);
                }

                list($request, $response) = (yield $step => $request);

                foreach ($this->extensions as $extension) {
                    $response = $extension->leaveStep($step, $request, $response, $this->context);
                }

                if (isset($this->originalNexts[$step]) && $this->originalNexts[$step]) {
                    $step->next($this->originalNexts[$step]);
                }

                $currentNext = $step->getNext();
                foreach ($this->extensions as $extension) {
                    if ($next = $extension->getNextStep($step, $request, $response, $this->context)) {
                        if ($step->getNext()) {
                            $next->next($step->getNext());
                        }
                        $step->next($next);
                    }
                }
                if ($step->getNext() !== $currentNext) {
                    $this->originalNexts[$step] = $currentNext;
                }
            } while ($this->generator->send([$request, $response]));
        } catch (\Exception $e) {
            $exception = $e;

            // No exceptions should be throw outside this method, otherwise they
            // will be caught by guzzle and will become silenced on PHP 7.0+
            try {
                foreach ($this->extensions as $extension) {
                    if (isset($step)) {
                        $extension->abortStep($step, $request, $exception, $this->context);
                    } else {
                        $extension->abortScenario($this->scenario, $exception, $this->context);
                    }
                }
            } catch (\Throwable $e) {
                echo $e;
            }
        } catch (\Error $e) {
            // BC with PHP < 7.0, because abortStep expect an \Exception
            $exception = new \ErrorException($e->getMessage(), $e->getCode(), E_ERROR, $e->getFile(), $e->getLine(), $e);

            // No exceptions should be throw outside this method, otherwise they
            // will be caught by guzzle and will become silenced on PHP 7.0+
            try {
                foreach ($this->extensions as $extension) {
                    if (isset($step)) {
                        $extension->abortStep($step, $request, $exception, $this->context);
                    } else {
                        $extension->abortScenario($this->scenario, $exception, $this->context);
                    }
                }
            } catch (\Throwable $e) {
                echo $e;
            }
        }

        // Can be converted to just return new Result()
        // when min version is PHP 7
        $this->result = new Result($this->context, $exception);

        foreach ($this->extensions as $extension) {
            $extension->leaveScenario($this->scenario, $this->result, $this->context);
        }
    }

    public function getResult()
    {
        return $this->result;
    }
}
