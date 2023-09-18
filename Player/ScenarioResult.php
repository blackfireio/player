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

use Blackfire\Player\Exception\ApiCallException;
use Blackfire\Player\Exception\ExpectationFailureException;
use Blackfire\Player\Exception\NonFatalException;

/**
 * @internal
 */
class ScenarioResult
{
    public function __construct(
        private readonly ScenarioContext $scenarioContext,
        private null|\Throwable $error,
    ) {
    }

    public function isBlackfireNetworkError(): bool
    {
        return null !== $this->error && $this->error instanceof ApiCallException;
    }

    public function isFatalError(): bool
    {
        return null !== $this->error
            && !$this->error instanceof ExpectationFailureException
            && !$this->error instanceof NonFatalException;
    }

    public function isExpectationError(): bool
    {
        return $this->error instanceof ExpectationFailureException;
    }

    public function isErrored(): bool
    {
        return null !== $this->error;
    }

    public function getError(): ?\Throwable
    {
        return $this->error;
    }

    public function getScenarioName(): null|string
    {
        return $this->scenarioContext->getName();
    }

    public function getValues(): array
    {
        return $this->scenarioContext->getValues();
    }

    public function setError(\Throwable $error): void
    {
        $this->error = $error;
    }
}
