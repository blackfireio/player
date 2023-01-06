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

use Symfony\Component\Serializer\Annotation as SymfonySerializer;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class AbstractStep
{
    /** @SymfonySerializer\Ignore  */
    protected ?AbstractStep $next = null;
    protected ?string $blackfireProfileUuid = null;

    private ?string $name = null;
    private array $errors = [];

    public function __construct(
        private readonly ?string $file = null,
        private readonly ?int $line = null,
    ) {
    }

    public function __clone()
    {
        if ($this->next) {
            $this->next = clone $this->next;
        }
    }

    public function __toString()
    {
        return sprintf("└ %s\n", static::class);
    }

    public function name(?string $name)
    {
        $this->name = $name;

        return $this;
    }

    public function next(self $step): ?self
    {
        $this->next = $step;

        return $step->getLast();
    }

    public function getNext(): ?self
    {
        return $this->next;
    }

    public function getName(): ?string
    {
        return $this->name ?: null;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function getLine(): ?int
    {
        return $this->line;
    }

    public function addError($error): void
    {
        $this->errors[] = $error;
    }

    public function hasErrors(): bool
    {
        return \count($this->errors) > 0;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @internal
     *
     * @SymfonySerializer\Ignore
     */
    public function getLast(): ?self
    {
        if (!$this->next) {
            return $this;
        }

        return $this->next->getLast();
    }

    /** @SymfonySerializer\SerializedName("type") */
    public function getType(): ?string
    {
        $type = explode('\\', static::class);
        $type = array_pop($type);

        if (str_ends_with($type, 'Step')) {
            $type = strtolower(substr($type, 0, -4));
        }

        return strtolower($type);
    }

    public function getBlackfireProfileUuid(): ?string
    {
        return $this->blackfireProfileUuid;
    }

    public function setBlackfireProfileUuid(?string $blackfireProfileUuid): void
    {
        $this->blackfireProfileUuid = $blackfireProfileUuid;
    }
}
