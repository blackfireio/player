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
    public function __construct(string|null $message = null, private readonly array $results = [], int $code = 0, \Exception|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getResults(): array
    {
        return $this->results;
    }
}
