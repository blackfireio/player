<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Extension;

use Blackfire\Player\Exception\ExpectationFailureException;
use Blackfire\Player\Step\AbstractStep;

/**
 * @internal
 */
final class ThrowableExtension implements ExceptionExtensionInterface
{
    public function failStep(AbstractStep $step, \Throwable $exception): void
    {
        if ($exception instanceof ExpectationFailureException) {
            $step->addFailingExpectation($exception->getMessage(), $exception->getResults());
        } else {
            $step->addError($exception->getMessage());
        }
    }
}
