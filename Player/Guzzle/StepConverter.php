<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Guzzle;

use Blackfire\Player\Exception\CrawlException;
use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\Exception\ExpressionSyntaxErrorException;
use Blackfire\Player\Psr7\StepConverterInterface;
use Blackfire\Player\Step\ClickStep;
use Blackfire\Player\Step\FollowStep;
use Blackfire\Player\Step\ReloadStep;
use Blackfire\Player\Step\Step;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\SubmitStep;
use Blackfire\Player\Step\VisitStep;
use Blackfire\Player\Context;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class StepConverter implements StepConverterInterface
{
    private $language;
    private $context;

    public function __construct(ExpressionLanguage $language, Context $context)
    {
        $this->language = $language;
        $this->context = $context;
    }

    /**
     * @return RequestInterface
     */
    public function createRequest(Step $step, RequestInterface $request = null, ResponseInterface $response = null)
    {
        $stepContext = $this->context->getStepContext();
        $previousRequest = null !== $request && null !== $response;

        if ($step instanceof VisitStep) {
            $request = $this->createRequestFromUri($step, $stepContext);
        } elseif ($step instanceof ClickStep) {
            if (!$previousRequest) {
                throw new CrawlException('Cannot click on a link without a previous request.');
            }

            $request = $this->createRequestFromLink($step, $stepContext);
        } elseif ($step instanceof SubmitStep) {
            if (!$previousRequest) {
                throw new CrawlException('Cannot submit a form without a previous request.');
            }

            $request = $this->createRequestFromForm($step, $stepContext);
        } elseif ($step instanceof FollowStep) {
            if (null === $request || null === $response) {
                throw new CrawlException('Unable to follow without a previous request.');
            }

            $request = $this->createRequestFromFollow($request, $response);
        } elseif ($step instanceof ReloadStep) {
            if (null === $request || null === $response) {
                throw new CrawlException('Unable to reload without a previous request.');
            }

            // just reload the current request
            Psr7\rewind_body($request);
        } else {
            throw new LogicException(sprintf('Unsupported step "%s".', get_class($step)));
        }

        return $request;
    }

    private function createRequestFromUri(VisitStep $step, StepContext $stepContext)
    {
        $uri = ltrim($this->evaluateExpression($step->getUri()), '/');
        $method = $step->getMethod() ? $this->evaluateExpression($step->getMethod()) : 'GET';
        $headers = $this->evaluateHeaders($stepContext);
        if (null === $body = $step->getBody()) {
            $body = $this->createBody($this->evaluateValues($step->getParameters()), $headers, $this->evaluateExpression($stepContext->isJson()));
        }

        return new Request($method, $this->fixUri($stepContext, $uri), $headers, $body);
    }

    private function createRequestFromLink(ClickStep $step, StepContext $stepContext)
    {
        $selector = $step->getSelector();

        $link = $this->evaluateExpression($selector, $this->context->getVariableValues(true));
        if (!count($link)) {
            throw new CrawlException(sprintf('Unable to click as link "%s" does not exist.', $selector));
        }
        $link = $link->link();

        return new Request($link->getMethod(), $this->fixUri($stepContext, $link->getUri()), $this->evaluateHeaders($stepContext));
    }

    private function createRequestFromForm(SubmitStep $step, StepContext $stepContext)
    {
        $selector = $step->getSelector();
        $form = $this->evaluateExpression($selector, $this->context->getVariableValues(true));

        if (!count($form)) {
            throw new CrawlException(sprintf('Unable to submit form as button "%s" does not exist.', $selector));
        }

        if (null === $body = $step->getBody()) {
            $formValues = $this->evaluateValues($step->getParameters());
            $form = $form->form($formValues);

            $headers = $this->evaluateHeaders($stepContext);
            $body = $this->createBody($form->getValues(), $headers, $this->evaluateExpression($stepContext->isJson()));

            /*
            // FIXME: when we have files, we need NOT use form_params
            if ($files = $form->getFiles()) {
                foreach ($files as $name => $file) {
                    'name' => $name,
                    'contents' => $file,
                }
            }
            */
        }

        return new Request($form->getMethod(), $this->fixUri($stepContext, $form->getUri()), $headers, $body);
    }

    private function createRequestFromFollow(RequestInterface $request, ResponseInterface $response)
    {
        if ('3' !== substr($response->getStatusCode(), 0, 1) || !$response->hasHeader('Location')) {
            throw new CrawlException('Unable to follow when no previous page is not a redirect.');
        }

        // logic from Guzzle\RedirectMiddleware
        // Request modifications to apply.
        $modify = [];

        // Use a GET request if this is an entity enclosing request and we are
        // not forcing RFC compliance, but rather emulating what all browsers
        // would do.
        $statusCode = $response->getStatusCode();
        if ($statusCode == 303 || ($statusCode <= 302 && $request->getBody())) {
            $modify['method'] = 'GET';
            $modify['body'] = '';
        }

        $modify['uri'] = Psr7\Uri::resolve($request->getUri(), $response->getHeaderLine('Location'));

        Psr7\rewind_body($request);

        // Add the Referer header only if we are not redirecting from https to http
        if ($modify['uri']->getScheme() === $request->getUri()->getScheme()) {
            $modify['set_headers']['Referer'] = (string) $request->getUri()->withUserInfo('', '');
        } else {
            $modify['remove_headers'][] = 'Referer';
        }

        // Remove Authorization header if host is different
        if ($request->getUri()->getHost() !== $modify['uri']->getHost()) {
            $modify['remove_headers'][] = 'Authorization';
        }

        return Psr7\modify_request($request, $modify);
    }

    private function fixUri(StepContext $stepContext, $uri)
    {
        $endpoint = $stepContext->getEndpoint() ? $this->evaluateExpression($stepContext->getEndpoint()) : null;

        if (!$endpoint) {
            return $uri;
        }

        return Psr7\Uri::resolve(Psr7\uri_for($endpoint), $uri);
    }

    private function createBody($parameters, &$headers, $isJson)
    {
        if (!$parameters) {
            return;
        }

        if ($isJson) {
            $headers['Content-Type'] = 'application/json';
        } else {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        if (is_string($parameters)) {
            return $parameters;
        }

        if ($isJson) {
            return Psr7\stream_for(json_encode($parameters));
        } else {
            return Psr7\stream_for(http_build_query($parameters, null, '&'));
        }
    }

    private function evaluateExpression($expression, $variables = null)
    {
        if (null === $variables) {
            $variables = $this->context->getVariableValues(true);
        }

        try {
            return $this->language->evaluate($expression, $variables);
        } catch (SyntaxError $e) {
            throw new ExpressionSyntaxErrorException(sprintf('Expression syntax error in "%s": %s', $expression, $e->getMessage()));
        }
    }

    private function evaluateValues($data)
    {
        if (is_string($data)) {
            return $this->evaluateExpression($data, $this->context->getVariableValues(false));
        }

        foreach ($data as $key => $value) {
            $data[$key] = $this->evaluateValues($value);
        }

        return $data;
    }

    private function evaluateHeaders(StepContext $stepContext)
    {
        $removedHeaders = [];
        $headers = [];
        foreach ($stepContext->getHeaders() as $header) {
            $header = $this->evaluateExpression($header);
            list($name, $value) = explode(':', $header);
            $value = ltrim($value);

            if ('false' === $value || empty($value) || isset($removedHeaders[$name])) {
                $removedHeaders[$name] = true;

                continue;
            }

            $headers[$name][] = $value;
        }

        if (null !== $auth = $stepContext->getAuth()) {
            $auth = $this->evaluateExpression($auth);
            if ('false' !== $auth && !empty($auth)) {
                list($username, $password) = explode(':', $auth);
                $password = ltrim($password);

                $headers['Authorization'] = sprintf('Basic %s', base64_encode(sprintf('%s:%s', $username, $password)));
            }
        }

        if (!isset($headers['User-Agent'])) {
            $headers['User-Agent'] = 'Blackfire PHP Player/1.0';
        }

        return $headers;
    }
}
