<?php

namespace Blackfire\Player\ExpressionLanguage;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage as SymfonyExpressionLanguage;
use Symfony\Component\ExpressionLanguage\Lexer;
use Symfony\Component\ExpressionLanguage\ParsedExpression;

/**
 * @internal
 */
class ExpressionLanguage extends SymfonyExpressionLanguage
{
    private $resultsVisitor;

    public function __construct(CacheItemPoolInterface $cache = null, array $providers = [])
    {
        parent::__construct($cache, $providers);

        $this->resultsVisitor = new ExtractResultsVisitor($this->functions);
    }

    public function extractResults(ParsedExpression $expression, array $variables)
    {
        return $this->resultsVisitor->extractResults($expression, $variables);
    }

    public function checkExpression($expression, $names, $allowMissingNames = false)
    {
        $missingNames = [];
        if ($allowMissingNames) {
            list($expression, $missingNames) = $this->parseAllowMissingNames($expression, $names);
        }

        $this->compile($expression, $names);

        return $missingNames;
    }

    private function parseAllowMissingNames($expression, $names)
    {
        if ($expression instanceof ParsedExpression) {
            return $expression;
        }

        $parser = new ValidatorParser($this->functions);
        $lexer = new Lexer();

        $nodes = $parser->parse($lexer->tokenize((string) $expression), $names);
        $parsedExpression = new ParsedExpression((string) $expression, $nodes);

        return [$parsedExpression, $parser->getMissingNames()];
    }
}
