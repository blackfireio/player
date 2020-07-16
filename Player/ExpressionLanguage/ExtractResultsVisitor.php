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

class ExtractResultsVisitor
{
    private static $ignoredFunctions = [
        'constant',
        'link',
        'css',
        'button',
        'xpath',
    ];

    private $functions;

    public function __construct(array $functions)
    {
        $this->functions = $functions;
    }

    public function extractResults(ParsedExpression $expression, array $variables)
    {
        $results = $this->visit($expression->getNodes(), $variables);

        return array_unique($results, SORT_REGULAR);
    }

    private function visit(Node\Node $node, array $variables, Node\Node $parentNode = null)
    {
        $subExpressions = [];

        foreach ($node->nodes as $k => $n) {
            $subExpressions = array_merge($subExpressions, $this->visit($n, $variables, $node));
        }

        if (
            $node instanceof Node\NameNode && (!$parentNode || !$parentNode instanceof Node\GetAttrNode) ||
            $node instanceof Node\GetAttrNode ||
            $node instanceof Node\FunctionNode && !\in_array($node->attributes['name'], self::$ignoredFunctions, true)
        ) {
            $subExpressions[] = [
                'expression' => $node->dump(),
                'result' => $this->formatResult($node->evaluate($this->functions, $variables)),
            ];
        }

        return $subExpressions;
    }

    private function formatResult($value)
    {
        return (new VariableFormatter())->formatResult($value);
    }
}
