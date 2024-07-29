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
use Blackfire\Player\Exception\LogicException;
use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class ScenarioSet implements \IteratorAggregate
{
    /** @var bool[] */
    #[Ignore]
    private array $keys = [];
    private ValueBag $extraBag;
    private string|null $name = null;
    /** @var string[] */
    private array $variables = [];
    private int $version = 0;

    private BuildStatus $status = BuildStatus::IN_PROGRESS;

    private string|null $endpoint = null;
    private string|null $blackfireEnvironment = null;

    public function __construct(
        /** @var Scenario[] */
        private array $scenarios = [],
    ) {
        $this->extraBag = new ValueBag();
    }

    public function __toString()
    {
        $str = '';
        $ind = 0;
        foreach ($this->scenarios as $scenario) {
            $str .= \sprintf(">>> Scenario %d <<<\n", ++$ind);
            $str .= $scenario."\n";
        }

        return $str;
    }

    public function addScenarioSet(self $scenarioSet): void
    {
        foreach ($scenarioSet as $scenario) {
            $this->add($scenario);
        }
    }

    public function computeNextVersion(): int
    {
        ++$this->version;

        return $this->version;
    }

    public function add(Scenario $scenario): void
    {
        if (null !== $scenario->getKey() && isset($this->keys[$scenario->getKey()])) {
            throw new LogicException(\sprintf('Scenario key "%s" is already defined.', $scenario->getKey()));
        }

        $this->scenarios[] = $scenario;

        $this->keys[$scenario->getKey()] = true;
    }

    public function name(string|null $name): void
    {
        $this->name = $name;
    }

    public function getName(): string|null
    {
        return $this->name;
    }

    #[Ignore]
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->scenarios);
    }

    public function getScenarios(): array
    {
        return $this->scenarios;
    }

    public function getExtraBag(): ValueBag
    {
        return $this->extraBag;
    }

    public function setVariables(array $variables): void
    {
        $this->variables = $variables;
    }

    public function setVariable(string $key, string $value): void
    {
        $this->variables[$key] = $value;
    }

    /**
     * @return string[]
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getEndpoint(): string|null
    {
        return $this->endpoint;
    }

    public function setEndpoint(string|null $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    public function getBlackfireEnvironment(): string|null
    {
        return $this->blackfireEnvironment;
    }

    public function setBlackfireEnvironment(string|null $blackfireEnvironment): void
    {
        $this->blackfireEnvironment = $blackfireEnvironment;
    }

    public function getStatus(): BuildStatus
    {
        return $this->status;
    }

    public function setStatus(BuildStatus $status): void
    {
        $this->status = $status;
    }
}
