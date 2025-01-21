<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Step;

use Blackfire\Player\Enum\BuildStatus;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class AbstractStep implements \Stringable
{
    #[Ignore]
    protected AbstractStep|null $next = null;
    #[Ignore]
    protected string|null $blackfireProfileUuid = null;
    protected BuildStatus $status = BuildStatus::TODO;

    private string|null $name = null;

    /**
     * @var [][]
     */
    #[Ignore]
    private array $failingExpectations = [];
    /**
     * @var string[]
     */
    #[Ignore]
    private array $failingAssertions = [];

    /**
     * Errors are:
     *  - The blackfire APIs are unavailable/ we cannot retrieve the profiles through the API
     *  - Incorrect step usage
     *  - The player encountered an unknown error.
     *
     * @var string[]
     */
    private array $errors = [];

    private array $deprecations = [];

    /** @var array ConfigurableStep[] */
    private array $generatedSteps = [];

    private string $uuid;

    private int|null $startedAt = null;

    private int|null $finishedAt = null;

    public function __construct(
        private readonly string|null $file = null,
        private readonly int|null $line = null,
    ) {
        $this->uuid = uuid_create(\UUID_TYPE_RANDOM);
    }

    public function __clone()
    {
        if (BuildStatus::TODO !== $this->status) {
            throw new \RuntimeException('Cannot clone a Processing Step');
        }

        $this->uuid = uuid_create(\UUID_TYPE_RANDOM);

        if (null !== $this->next) {
            $this->next = clone $this->next;
        }
    }

    public function __toString(): string
    {
        return \sprintf("└ %s\n", static::class);
    }

    public function name(string|null $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function next(self $step): self|null
    {
        $this->next = $step;

        return $step->getLast();
    }

    public function getNext(): self|null
    {
        return $this->next;
    }

    public function getName(): string|null
    {
        return '' === (string) $this->name ? null : $this->name;
    }

    public function getFile(): string|null
    {
        return $this->file;
    }

    public function getLine(): int|null
    {
        return $this->line;
    }

    public function addDeprecation(string $deprecation): void
    {
        $this->deprecations[] = $deprecation;
    }

    public function getDeprecations(): array
    {
        return $this->deprecations;
    }

    public function addFailingExpectation(string $reason, array $results): void
    {
        $this->failingExpectations[] = [
            'reason' => $reason,
            'results' => $results,
        ];
    }

    #[Ignore]
    public function hasFailingExpectation(): bool
    {
        return [] !== $this->failingExpectations;
    }

    public function addFailingAssertion(string $reason): void
    {
        $this->failingAssertions[] = $reason;
    }

    #[Ignore]
    public function hasFailingAssertion(): bool
    {
        return [] !== $this->failingAssertions;
    }

    public function addError(string $reason): void
    {
        $this->errors[] = $reason;
    }

    #[Ignore]
    public function hasError(): bool
    {
        return [] !== $this->errors;
    }

    /**
     * @return [][]
     */
    public function getFailingExpectations(): array
    {
        return $this->failingExpectations;
    }

    /**
     * @return string[]
     */
    public function getFailingAssertions(): array
    {
        return $this->failingAssertions;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @internal
     */
    #[Ignore]
    public function getLast(): self|null
    {
        if (null === $this->next) {
            return $this;
        }

        return $this->next->getLast();
    }

    #[SerializedName('type')]
    public function getType(): string|null
    {
        $type = explode('\\', static::class);
        $type = array_pop($type);

        if (str_ends_with($type, 'Step')) {
            $type = strtolower(substr($type, 0, -4));
        }

        if ('' === $type) {
            return null;
        }

        return strtolower($type);
    }

    #[SerializedName('blackfire_profile_uuid')]
    public function getSerializedBlackfireProfileUuid(): string|null
    {
        // we want to send the profile UUID only once it has been processed
        if (BuildStatus::DONE === $this->status) {
            return $this->blackfireProfileUuid;
        }

        return null;
    }

    #[SerializedName('failing_expectations')]
    public function getTruncatedFailingExpectations(): array
    {
        $truncatedFailingExpectations = [];
        $failingExpectations = $this->getFailingExpectations();

        foreach ($failingExpectations as $failingExpectation) {
            $truncatedResults = [];
            foreach ($failingExpectation['results'] as $result) {
                $truncatedResults[] = [
                    'expression' => $result['expression'],
                    'result' => \strlen((string) $result['result']) > 30 ? substr((string) $result['result'], 0, 30).'... (truncated)' : $result['result'],
                ];
            }

            $truncatedFailingExpectations[] = [
                'reason' => $failingExpectation['reason'],
                'results' => $truncatedResults,
            ];
        }

        return $truncatedFailingExpectations;
    }

    public function getBlackfireProfileUuid(): string|null
    {
        return $this->blackfireProfileUuid;
    }

    public function setBlackfireProfileUuid(string $blackfireProfileUuid): void
    {
        $this->blackfireProfileUuid = $blackfireProfileUuid;
    }

    public function setStatus(BuildStatus $status): void
    {
        $this->status = $status;
        switch ($status) {
            case BuildStatus::DONE:
                $this->finishedAt = $this->getTimingAsMs();
                break;
            case BuildStatus::IN_PROGRESS:
                $this->startedAt = $this->getTimingAsMs();
                break;
        }
    }

    public function getStatus(): string
    {
        return $this->status->value;
    }

    public function getStartedAt(): int|null
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): int|null
    {
        return $this->finishedAt;
    }

    public function addGeneratedStep(ConfigurableStep $step): void
    {
        if (BuildStatus::TODO === $this->status) {
            throw new \RuntimeException('Can not add a add a generated child step to a not-processing Step');
        }

        $this->generatedSteps[] = $step;
    }

    public function getGeneratedSteps(): array
    {
        return $this->generatedSteps;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    #[SerializedName('iid')]
    public function getInstanceId(): int
    {
        return spl_object_id($this);
    }

    private function getTimingAsMs(): int
    {
        return floor(microtime(true) * 1000);
    }
}
