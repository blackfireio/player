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

final class ValidationResult
{
    private $success;
    private $errors;
    private $missingVariables = [];

    public function __construct($success = true, array $errors = null)
    {
        $this->success = $success;
        $this->errors = $errors;
    }

    public function isSuccess()
    {
        return $this->success;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function setMissingVariables(array $missingVariables)
    {
        $this->missingVariables = $missingVariables;
    }

    public function getMissingVariables()
    {
        return $this->missingVariables;
    }
}
