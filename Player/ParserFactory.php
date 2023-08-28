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

/**
 * @internal
 */
class ParserFactory
{
    public function __construct(
        private readonly ExpressionLanguage $expressionLanguage,
    ) {
    }

    public function createParser(array $externalVariables, bool $allowMissingVariables = false): Parser
    {
        return new Parser(
            $this->expressionLanguage,
            $externalVariables,
            $allowMissingVariables,
        );
    }
}
