<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage as SymfonyExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Lexer;
use Symfony\Component\ExpressionLanguage\ParsedExpression;

/**
 * @internal
 */
class ExpressionLanguage extends SymfonyExpressionLanguage
{
    private ?ExtractResultsVisitor $resultsVisitor = null;
    private ?ValidatorParser $parser = null;
    private ?Lexer $lexer = null;

    public function extractResults(ParsedExpression $expression, array $variables): array
    {
        return $this->getResultVisitor()->extractResults($expression, $variables);
    }

    public function checkExpression(string $expression, array $names, bool $allowMissingNames = false): array
    {
        $missingNames = [];
        if ($allowMissingNames) {
            [$expression, $missingNames] = $this->parseAllowMissingNames($expression, $names);
        }

        $this->compile($expression, $names);

        return $missingNames;
    }

    private function parseAllowMissingNames(string $expression, array $names): array
    {
        $parser = $this->getParser();

        $nodes = $parser->parse($this->getLexer()->tokenize($expression), $names);
        $parsedExpression = new ParsedExpression($expression, $nodes);

        return [$parsedExpression, $parser->getMissingNames()];
    }

    private function getLexer(): Lexer
    {
        return $this->lexer ??= new Lexer();
    }

    private function getParser(): ValidatorParser
    {
        return $this->parser ??= new ValidatorParser($this->functions);
    }

    private function getResultVisitor(): ExtractResultsVisitor
    {
        return $this->resultsVisitor ??= new ExtractResultsVisitor($this->functions);
    }
}
