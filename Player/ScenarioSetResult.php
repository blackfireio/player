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

/**
 * @internal
 */
class ScenarioSetResult
{
    /** @var ScenarioResult[] */
    private array $results = [];

    public function add(ScenarioResult $scenarioResult): void
    {
        $this->results[] = $scenarioResult;
    }

    /**
     * @return ScenarioResult[]
     */
    public function getScenarioResults(): array
    {
        return $this->results;
    }

    public function isFatalError(): bool
    {
        foreach ($this->results as $result) {
            if ($result->isFatalError()) {
                return true;
            }
        }

        return false;
    }

    public function isExpectationError(): bool
    {
        foreach ($this->results as $result) {
            if ($result->isExpectationError()) {
                return true;
            }
        }

        return false;
    }

    public function isErrored(): bool
    {
        foreach ($this->results as $result) {
            if ($result->isErrored()) {
                return true;
            }
        }

        return false;
    }

    public function getValues(): array
    {
        $values = [];
        foreach ($this->results as $result) {
            $values[] = $result->getValues();
        }

        return $values;
    }
}
