<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Validator;

/**
 * @internal
 */
final class ValidationResult
{
    /** @var string[] */
    private array $missingVariables = [];

    public function __construct(
        private readonly bool $success = true,
        private readonly ?array $errors = null,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }

    /**
     * @param string[] $missingVariables
     */
    public function setMissingVariables(array $missingVariables): void
    {
        $this->missingVariables = $missingVariables;
    }

    /**
     * @return string[]
     */
    public function getMissingVariables(): array
    {
        return $this->missingVariables;
    }
}
