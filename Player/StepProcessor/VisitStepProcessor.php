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

use Blackfire\Player\Exception\CrawlException;
use Blackfire\Player\Http\Request;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\RequestStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\VisitStep;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @internal
 */
class VisitStepProcessor implements StepProcessorInterface
{
    public function __construct(
        private readonly ExpressionEvaluator $expressionEvaluator,
        private readonly UriResolver $uriResolver,
    ) {
    }

    public function supports(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): bool
    {
        return $step instanceof VisitStep;
    }

    /**
     * @param VisitStep $step
     */
    public function process(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): iterable
    {
        if (!$this->supports($step, $stepContext, $scenarioContext)) {
            throw new \LogicException(\sprintf('Cannot handle steps of type "%s".', get_debug_type($step)));
        }

        $uri = $this->expressionEvaluator->evaluateExpression($step->getUri(), $stepContext, $scenarioContext);
        if ($uri instanceof Crawler) {
            throw new CrawlException('It looks like you used "visit" and "link" together. You should use "click" instead');
        }

        $uri = ltrim($uri, '/');
        $method = $this->expressionEvaluator->evaluateExpression($step->getMethod(), $stepContext, $scenarioContext) ?: 'GET';
        $headers = $this->expressionEvaluator->evaluateHeaders($stepContext, $scenarioContext);
        if (null === $body = $step->getBody()) {
            if ($this->expressionEvaluator->evaluateExpression($stepContext->isJson(), $stepContext, $scenarioContext)) {
                $headers['content-type'] ??= [Request::CONTENT_TYPE_JSON];
            } else {
                $headers['content-type'] ??= [Request::CONTENT_TYPE_FORM];
            }
            $body = $this->expressionEvaluator->evaluateValues($step->getParameters(), $stepContext, $scenarioContext);
        } else {
            $headers['content-type'] ??= [Request::CONTENT_TYPE_RAW];
            $body = $this->expressionEvaluator->evaluateExpression($body, $stepContext, $scenarioContext);
        }

        yield new RequestStep(
            new Request(
                $method,
                $this->uriResolver->resolveUri($this->expressionEvaluator->evaluateExpression($stepContext->getEndpoint(), $stepContext, $scenarioContext), $uri),
                $headers,
                $body
            ),
            $step,
        );
    }
}
