<?php

namespace Blackfire\Player\ExpressionLanguage;

use Symfony\Component\DomCrawler\Crawler;
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
        switch (true) {
            case true === $value:
                return 'true';

            case false === $value:
                return 'false';

            case null === $value:
                return 'null';

            case is_numeric($value):
                return $value;

            case \is_array($value):
                if ($this->isHash($value)) {
                    $str = '{';

                    foreach ($value as $key => $v) {
                        if (\is_int($key)) {
                            $str .= sprintf('%s: %s, ', $key, $this->formatResult($v));
                        } else {
                            $str .= sprintf('"%s": %s, ', $this->dumpEscaped($key), $this->dumpEscaped($v));
                        }
                    }

                    return rtrim($str, ', ').'}';
                }

                $str = '[';

                foreach ($value as $key => $v) {
                    $str .= sprintf('%s, ', $this->dumpEscaped($v));
                }

                return rtrim($str, ', ').']';

            case \is_object($value):
                /** @var string $value */
                $value = $this->convertObjectToString($value);

                return sprintf('"%s"', $this->dumpEscaped($value));

            default:
                return sprintf('"%s"', $this->dumpEscaped($value));
        }
    }

    protected function convertObjectToString($value)
    {
        if (method_exists($value, '__toString')) {
            return $value->__toString();
        }

        if ($value instanceof Crawler) {
            return $value->html();
        }

        return sprintf('(object) "%s"', \get_class($value));
    }

    protected function isHash(array $value)
    {
        $expectedKey = 0;

        foreach ($value as $key => $val) {
            if ($key !== $expectedKey++) {
                return true;
            }
        }

        return false;
    }

    protected function dumpEscaped($value)
    {
        return str_replace(['\\', '"'], ['\\\\', '\"'], $value);
    }
}
