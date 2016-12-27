<?php

namespace Blackfire\Player\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage as SymfonyExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ParsedExpression;

class ExpressionLanguage extends SymfonyExpressionLanguage
{
    private $resultsVisitor;

    public function __construct(ParserCacheInterface $cache = null, array $providers = array())
    {
        parent::__construct($cache, $providers);

        $this->resultsVisitor = new ExtractResultsVisitor($this->functions);
    }

    public function extractResults(ParsedExpression $expression, array $variables)
    {
        return $this->resultsVisitor->extractResults($expression, $variables);
    }
}
