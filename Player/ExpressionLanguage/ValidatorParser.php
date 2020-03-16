<?php

namespace Blackfire\Player\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\Node;
use Symfony\Component\ExpressionLanguage\Parser as SymfonyParser;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\ExpressionLanguage\Token;
use Symfony\Component\ExpressionLanguage\TokenStream;

class ValidatorParser extends SymfonyParser
{
    private $functions;
    private $stream;
    private $names;
    private $missingNames = [];

    public function __construct(array $functions)
    {
        $this->functions = $functions;

        parent::__construct($functions);
    }

    public function parse(TokenStream $stream, $names = [])
    {
        $this->stream = $stream;
        $this->names = $names;
        $this->missingNames = [];

        return parent::parse($stream, $names);
    }

    public function parsePrimaryExpression()
    {
        $token = $this->stream->current;
        switch ($token->type) {
            case Token::NAME_TYPE:
                $this->stream->next();
                switch ($token->value) {
                    case 'true':
                    case 'TRUE':
                        return new Node\ConstantNode(true);

                    case 'false':
                    case 'FALSE':
                        return new Node\ConstantNode(false);

                    case 'null':
                    case 'NULL':
                        return new Node\ConstantNode(null);

                    default:
                        if ('(' === $this->stream->current->value) {
                            if (false === isset($this->functions[$token->value])) {
                                throw new SyntaxError(sprintf('The function "%s" does not exist', $token->value), $token->cursor, $this->stream->getExpression(), $token->value, array_keys($this->functions));
                            }

                            $node = new Node\FunctionNode($token->value, $this->parseArguments());
                        } else {
                            if (!\in_array($token->value, $this->names, true)) {
                                $this->missingNames[] = $token->value;
                                $name = $token->value;
                            } else {
                                // is the name used in the compiled code different
                                // from the name used in the expression?
                                if (\is_int($name = array_search($token->value, $this->names))) {
                                    $name = $token->value;
                                }
                            }

                            $node = new Node\NameNode($name);
                        }
                }
                break;

            case Token::NUMBER_TYPE:
            case Token::STRING_TYPE:
                $this->stream->next();

                return new Node\ConstantNode($token->value);

            default:
                if ($token->test(Token::PUNCTUATION_TYPE, '[')) {
                    $node = $this->parseArrayExpression();
                } elseif ($token->test(Token::PUNCTUATION_TYPE, '{')) {
                    $node = $this->parseHashExpression();
                } else {
                    throw new SyntaxError(sprintf('Unexpected token "%s" of value "%s"', $token->type, $token->value), $token->cursor, $this->stream->getExpression());
                }
        }

        return $this->parsePostfixExpression($node);
    }

    public function getMissingNames()
    {
        return $this->missingNames;
    }
}
