<?php

namespace Blackfire\Player;

use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class VariableResolver
{
    private $language;

    public function __construct(ExpressionLanguage $language)
    {
        $this->language = $language;
    }

    public function resolve(array $toResolve = [])
    {
        $resolved = [];
        while ($toResolve) {
            $lastException = null;
            $succeed = false;
            foreach ($toResolve as $key => $value) {
                try {
                    if (null === $value || '' === $value) {
                        $resolved[$key] = $value;
                    } else {
                        $resolved[$key] = $this->language->evaluate($value, $resolved);
                    }
                    unset($toResolve[$key]);
                    $succeed = true;
                } catch (SyntaxError $lastException) {
                }
            }

            if (false === $succeed && $lastException) {
                throw $lastException;
            }
        }

        return $resolved;
    }
}
