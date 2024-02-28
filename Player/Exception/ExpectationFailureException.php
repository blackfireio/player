<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Exception;

/**
 * @internal
 */
class ExpectationFailureException extends LogicException
{
    private array $results;

    public function __construct(?string $message = null, array $results = [], int $code = 0, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->results = $results;
    }

    public function getResults(): array
    {
        return $this->results;
    }
}
