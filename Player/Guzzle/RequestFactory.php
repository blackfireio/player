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
use Blackfire\Player\Step;
use Blackfire\Player\ValueBag;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class RequestFactory
{
    private $language;

    public function __construct(ExpressionLanguage $language)
    {
        $this->language = $language;
    }

    public function create(Step $step, ValueBag $values = null, RequestInterface $request = null, ResponseInterface $response = null, Crawler $crawler = null)
    {
        if (null === $values) {
            $values = new ValueBag();
        }

        if ($step->getUri()) {
            $request = $this->createRequestFromUri($step, $values);
        } elseif ($step->getLinkSelector()) {
            $request = $this->createRequestFromLink($step, $values, $crawler);
        } elseif ($step->getFormSelector()) {
            $request = $this->createRequestFromForm($step, $values, $crawler);
        } elseif ($step->isFollow()) {
            $request = $this->createRequestFromFollow($step, $values, $request, $response, $crawler);
        } else {
            throw new LogicException('A step needs a URI, a link, a form, or a follow redirect.');
        }

        return $request->withHeader('X-Request-Id', substr(sha1($request->getUri()), 0, 7));
    }

    private function createRequestFromUri(Step $step, ValueBag $values)
    {
        $uri = $this->language->evaluate($step->getUri(), $values->all(true));

        $headers = $step->getHeaders();
        if (!isset($headers['User-Agent'])) {
            $headers['User-Agent'] = 'Blackfire PHP Player/1.0';
        }

        $body = $this->createBody($values, $step->getFormValues(), $headers, $step->isJson());

        return new Request($step->getMethod(), $this->fixUri($step, $uri), $headers, $body);
    }

    private function createRequestFromLink(Step $step, ValueBag $values, Crawler $crawler = null)
    {
        $selector = $step->getLinkSelector();
        $link = $this->language->evaluate($selector, ['_crawler' => $crawler] + $values->all(true));

        if (!count($link)) {
            throw new CrawlException(sprintf('Unable to click as link "%s" does not exist.', $selector));
        }
        $link = $link->link();

        return new Request($link->getMethod(), $this->fixUri($step, $link->getUri()), $step->getHeaders());
    }

    private function createRequestFromForm(Step $step, ValueBag $values, Crawler $crawler = null)
    {
        $selector = $step->getFormSelector();
        $form = $this->language->evaluate($selector, ['_crawler' => $crawler] + $values->all(true));

        if (!count($form)) {
            throw new CrawlException(sprintf('Unable to submit form as button "%s" does not exist.', $selector));
        }
        $formValues = $this->evaluateValues($values, $step->getFormValues());
        $form = $form->form($formValues);

        $headers = $step->getHeaders();
        $body = $this->createBody($values, $form->getValues(), $headers, $step->isJson());
        /*
        // FIXME: when we have files, we need NOT use form_params
        if ($files = $form->getFiles()) {
            foreach ($files as $name => $file) {
                'name' => $name,
                'contents' => $file,
            }
        }
        */

        return new Request($form->getMethod(), $this->fixUri($step, $form->getUri()), $headers, $body);
    }

    private function createRequestFromFollow(Step $step, ValueBag $values, RequestInterface $request = null, ResponseInterface $response = null, Crawler $crawler = null)
    {
        if (null === $request || null === $response) {
            throw new CrawlException('Unable to follow when no previous page.');
        }

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

    private function fixUri(Step $step, $uri)
    {
        if (!$step->getEndpoint()) {
            return $uri;
        }

        return Psr7\Uri::resolve(Psr7\uri_for($step->getEndpoint()), $uri);
    }

    private function createBody(ValueBag $values, $parameters, &$headers, $isJson)
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

    private function evaluateValues(ValueBag $values, $data)
    {
        if (is_string($data)) {
            return $this->language->evaluate($data, $values->all(false));
        }

        foreach ($data as $key => $value) {
            $data[$key] = $this->language->evaluate($value, $values->all(false));
        }

        return $data;
    }
}
