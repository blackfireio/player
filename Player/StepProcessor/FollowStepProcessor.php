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
use Blackfire\Player\Extension\BlackfireExtension;
use Blackfire\Player\Http\Request;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\FollowStep;
use Blackfire\Player\Step\RequestStep;
use Blackfire\Player\Step\StepContext;

/**
 * @internal
 */
class FollowStepProcessor implements StepProcessorInterface
{
    public function __construct(
        private readonly UriResolver $uriResolver,
    ) {
    }

    public function supports(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): bool
    {
        return $step instanceof FollowStep;
    }

    /**
     * @param FollowStep $step
     */
    public function process(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): iterable
    {
        if (!$this->supports($step, $stepContext, $scenarioContext)) {
            throw new \LogicException(\sprintf('Cannot handle steps of type "%s".', get_debug_type($step)));
        }

        if (!$scenarioContext->hasPreviousResponse()) {
            throw new CrawlException('Unable to follow without a previous request.');
        }

        $previousResponse = $scenarioContext->getLastResponse();
        $previousRequest = $previousResponse->request;
        if (!str_starts_with((string) $previousResponse->statusCode, '3') || empty($previousResponse->headers['location'])) {
            throw new CrawlException('Unable to follow when no previous page is not a redirect.');
        }

        // logic from Guzzle\RedirectMiddleware
        // Request modifications to apply.
        $modify = [
            'method' => $previousRequest->method,
            'headers' => $previousRequest->headers,
            'body' => $previousRequest->body,
        ];

        // Use a GET request if this is an entity enclosing request and we are
        // not forcing RFC compliance, but rather emulating what all browsers
        // would do.
        $statusCode = $previousResponse->statusCode;
        if (303 === $statusCode || ($statusCode <= 302 && ($previousRequest->options['body'] ?? $previousRequest->options['json'] ?? null))) {
            $modify['method'] = 'GET';
            $modify['body'] = null;
            unset($modify['headers']['content-type']);
        }
        $modify['uri'] = $this->uriResolver->resolveUri($previousRequest->uri, $previousResponse->headers['location'][0]);

        $newUri = parse_url($modify['uri']) + ['host' => '', 'scheme' => ''];
        $previousUri = parse_url($previousRequest->uri) + ['host' => '', 'scheme' => ''];

        // Add the Referer header only if we are not redirecting from HTTPS to HTTP
        if ($newUri['scheme'] !== $previousUri['scheme']) {
            unset($modify['headers']['referer']);
        } else {
            $modify['headers']['referer'] = [$this->uriResolver->buildUrl(['user' => null, 'pass' => null] + $previousUri)];
        }

        // Remove Authorization header if host is different
        if ($newUri['host'] !== $previousUri['host']) {
            unset($modify['headers']['authorization']);
        }

        // Remove the Blackfire Query
        unset($modify['headers'][BlackfireExtension::HEADER_BLACKFIRE_QUERY]);
        unset($modify['headers'][BlackfireExtension::HEADER_BLACKFIRE_PROFILE_UUID]);

        yield new RequestStep(
            new Request($modify['method'], $modify['uri'], $modify['headers'], $modify['body']),
            $step,
        );
    }
}
