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

use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

use function Sentry\captureException;

/**
 * @internal
 */
class VariableResolver
{
    public function __construct(
        private readonly ExpressionLanguage $language,
    ) {
    }

    public function resolve(array $toResolve = [], array $variables = []): array
    {
        $resolved = [];
        while ($toResolve) {
            $succeed = false;
            foreach ($toResolve as $key => $value) {
                try {
                    $resolved[$key] = null === $value || '' === $value ? $value : $this->language->evaluate($value, $resolved + $variables);
                    unset($toResolve[$key]);
                    $succeed = true;
                } catch (SyntaxError $lastException) {
                    captureException($lastException);
                }
            }

            if (false === $succeed && isset($lastException)) {
                throw $lastException;
            }
        }

        return $resolved;
    }
}
