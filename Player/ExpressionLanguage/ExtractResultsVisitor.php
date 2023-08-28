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

use Symfony\Component\ExpressionLanguage\Node;
use Symfony\Component\ExpressionLanguage\ParsedExpression;

/**
 * @internal
 */
class ExtractResultsVisitor
{
    private const IGNORED_FUNCTIONS = [
        'constant',
        'link',
        'css',
        'button',
        'xpath',
    ];

    public function __construct(
        private readonly array $functions,
    ) {
    }

    public function extractResults(ParsedExpression $expression, array $variables): array
    {
        $results = $this->visit($expression->getNodes(), $variables);

        return array_unique($results, \SORT_REGULAR);
    }

    private function visit(Node\Node $node, array $variables, Node\Node $parentNode = null): array
    {
        $subExpressions = [];

        foreach ($node->nodes as $n) {
            $subExpressions[] = $this->visit($n, $variables, $node);
        }

        $subExpressions = array_merge(...$subExpressions);

        if (
            $node instanceof Node\NameNode && (!$parentNode instanceof Node\GetAttrNode)
            || $node instanceof Node\GetAttrNode
            || $node instanceof Node\FunctionNode && !\in_array($node->attributes['name'], self::IGNORED_FUNCTIONS, true)
        ) {
            $subExpressions[] = [
                'expression' => $node->dump(),
                'result' => $this->formatResult($node->evaluate($this->functions, $variables)),
            ];
        }

        return $subExpressions;
    }

    private function formatResult(mixed $value): string
    {
        return (new VariableFormatter())->formatResult($value);
    }
}
