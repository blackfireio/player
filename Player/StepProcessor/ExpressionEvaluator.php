<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\StepProcessor;

use Blackfire\Player\Exception\ExpressionSyntaxErrorException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\StepContext;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * @internal
 */
class ExpressionEvaluator
{
    public function __construct(
        private readonly ExpressionLanguage $language,
    ) {
    }

    public function evaluateExpression(string|null $expression, StepContext $stepContext, ScenarioContext $scenarioContext, bool $trim = true): mixed
    {
        if (!$expression) {
            return $expression;
        }
        try {
            return $this->language->evaluate($expression, $scenarioContext->getVariableValues($stepContext, $trim));
        } catch (SyntaxError $e) {
            throw new ExpressionSyntaxErrorException(\sprintf('Expression syntax error in "%s": %s', $expression, $e->getMessage()));
        }
    }

    /**
     * @return string[][]
     */
    public function evaluateHeaders(StepContext $stepContext, ScenarioContext $scenarioContext): array
    {
        $removedHeaders = [];
        $headers = [];
        foreach ($stepContext->getHeaders() as $header) {
            $header = $this->evaluateExpression($header, $stepContext, $scenarioContext);
            [$name, $value] = explode(':', $header, 2);
            $name = strtolower($name);
            $value = ltrim($value);

            if ('false' === $value || empty($value) || isset($removedHeaders[$name])) {
                $removedHeaders[$name] = true;

                continue;
            }

            $headers[$name][] = $value;
        }

        if (null !== $auth = $stepContext->getAuth()) {
            $auth = $this->evaluateExpression($auth, $stepContext, $scenarioContext);
            if ('false' !== $auth && !empty($auth)) {
                [$username, $password] = explode(':', $auth);
                $password = ltrim($password);

                $headers['authorization'] = [\sprintf('Basic %s', base64_encode(\sprintf('%s:%s', $username, $password)))];
            }
        }

        $headers['user-agent'] ??= ['Blackfire PHP Player/1.0'];

        return $headers;
    }

    public function evaluateValues(array $data, StepContext $stepContext, ScenarioContext $scenarioContext): array
    {
        foreach ($data as $key => $value) {
            if (!\is_array($value)) {
                $data[$key] = $this->evaluateExpression($value, $stepContext, $scenarioContext, false);
            } else {
                $data[$key] = $this->evaluateValues($value, $stepContext, $scenarioContext);
            }
        }

        return $data;
    }
}
