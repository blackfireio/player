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
use Blackfire\Player\Step\ClickStep;
use Blackfire\Player\Step\RequestStep;
use Blackfire\Player\Step\StepContext;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @internal
 */
class ClickStepProcessor implements StepProcessorInterface
{
    public function __construct(
        private readonly ExpressionEvaluator $expressionEvaluator,
        private readonly UriResolver $uriResolver,
    ) {
    }

    public function supports(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): bool
    {
        return $step instanceof ClickStep;
    }

    /**
     * @param ClickStep $step
     */
    public function process(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): iterable
    {
        if (!$this->supports($step, $stepContext, $scenarioContext)) {
            throw new \LogicException(\sprintf('Cannot handle steps of type "%s".', get_debug_type($step)));
        }

        if (!$scenarioContext->hasPreviousResponse()) {
            throw new CrawlException('Cannot click on a link without a previous request.');
        }

        $selector = $step->getSelector();

        $link = $this->expressionEvaluator->evaluateExpression($selector, $stepContext, $scenarioContext);
        if (!$link instanceof Crawler) {
            throw new CrawlException('You can only click on links as returned by the link() function.');
        }
        if (0 === \count($link)) {
            throw new CrawlException(\sprintf('Unable to click as link "%s" does not exist.', $selector));
        }
        $link = $link->link();

        yield new RequestStep(
            new Request(
                $link->getMethod(),
                $this->uriResolver->resolveUri($this->expressionEvaluator->evaluateExpression($stepContext->getEndpoint(), $stepContext, $scenarioContext) ?? '', $link->getUri()),
                $this->expressionEvaluator->evaluateHeaders($stepContext, $scenarioContext)
            ),
            $step,
        );
    }
}
