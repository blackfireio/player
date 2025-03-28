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

use Blackfire\Player\Http\CookieJar;
use Blackfire\Player\Http\Request;
use Blackfire\Player\Http\Response;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\RequestStep;
use Blackfire\Player\Step\StepContext;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
class RequestStepProcessor implements StepProcessorInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CookieJar $cookieJar,
    ) {
    }

    public function supports(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): bool
    {
        return $step instanceof RequestStep;
    }

    /**
     * @param RequestStep $step
     */
    public function process(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): iterable
    {
        if (!$this->supports($step, $stepContext, $scenarioContext)) {
            throw new \LogicException(\sprintf('Cannot handle steps of type "%s".', get_debug_type($step)));
        }

        $request = $step->getRequest();

        $options = [
            'headers' => $request->headers,
            ...$request->options,
        ];
        switch (($request->headers['content-type'] ?? [])[0] ?? null) {
            case Request::CONTENT_TYPE_JSON:
                $options['json'] = $request->body;
                break;
            default:
                $options['body'] = $request->body;
                break;
        }

        $cookies = [];
        foreach ($this->cookieJar->allValues($request->uri) as $name => $value) {
            $cookies[] = $name.'='.$value;
        }
        if ([] !== $cookies) {
            $headers = $options['headers'];
            $headers['cookie'] = implode('; ', $cookies);
            $options['headers'] = $headers;
        }

        $httpResponse = $this->httpClient->request(
            $request->method,
            $request->uri,
            $options
        );

        // consume HttpResponse Asynchronously
        try {
            foreach ($this->httpClient->stream($httpResponse, 0.01) as $chunk) {
                if ($chunk->isTimeout()) {
                    \Fiber::suspend();
                }
            }
        } catch (HttpExceptionInterface) {
            // Do not rethrow exception related to http status code (4xx, 5xx errors)
        }

        $this->cookieJar->updateFromResponse($httpResponse, $request->uri);

        $responseInfo = $httpResponse->getInfo();
        $stats = [
            'total' => $responseInfo['total_time'] ?? null,
            'name_lookup' => $responseInfo['namelookup_time'] ?? null,
            'connect' => $responseInfo['connect_time'] ?? null,
            'pre_transfer' => $responseInfo['pretransfer_time'] ?? null,
            'start_transfer' => $responseInfo['starttransfer_time'] ?? null,
            'post_transfer' => isset($responseInfo['posttransfer_time_us']) ? ($responseInfo['posttransfer_time_us'] / 1000000) : null,
        ];

        $response = new Response($request, $httpResponse->getStatusCode(), $httpResponse->getHeaders(false), $httpResponse->getContent(false), $stats);

        // Update context with generated data
        $scenarioContext->setLastResponse($response);

        return [];
    }
}
