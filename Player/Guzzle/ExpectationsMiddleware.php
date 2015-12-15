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

use Blackfire\Player\Exception\ExpectationErrorException;
use Blackfire\Player\Exception\ExpectationFailureException;
use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\Exception\InvalidArgumentException;
use Blackfire\Player\ValueBag;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError as ExpressionSyntaxError;

/**
 * This middleware does not play well with the Redirect one as expectations
 * from the request that redirects will be run on redirected requests as well.
 *
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class ExpectationsMiddleware
{
    private $handler;
    private $language;
    private $logger;

    public function __construct(callable $handler, ExpressionLanguage $language, LoggerInterface $logger = null)
    {
        $this->handler = $handler;
        $this->language = $language;
        $this->logger = $logger;
    }

    public static function create(ExpressionLanguage $language, LoggerInterface $logger = null)
    {
        return function (callable $handler) use ($language, $logger) {
            return new self($handler, $language, $logger);
        };
    }

    /**
     * @return PromiseInterface
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        $fn = $this->handler;

        if (!isset($options['expectations']) && !isset($options['extractions'])) {
            return $fn($request, $options);
        }

        if (isset($options['expectations']) && !is_array($options['expectations'])) {
            throw new InvalidArgumentException('The "expectations" option must be an array.');
        }

        if (isset($options['extractions']) && !is_array($options['extractions'])) {
            throw new InvalidArgumentException('The "extractions" option must be an array.');
        }

        return $fn($request, $options)
            ->then(function (ResponseInterface $response) use ($request, $options) {
                return $this->processResponse($request, $options, $response);
            });
    }

    /**
     * @param RequestInterface                   $request
     * @param array                              $options
     * @param ResponseInterface|PromiseInterface $response
     *
     * @return ResponseInterface|PromiseInterface
     */
    public function processResponse(RequestInterface $request, array $options, ResponseInterface $response)
    {
        $crawler = null;
        if ($response->hasHeader('Content-Type') && false !== strpos($response->getHeaderLine('Content-Type'), 'html')) {
            $crawler = new Crawler(null, $request->getUri());
            $crawler->addContent((string) $response->getBody(), $response->getHeaderLine('Content-Type'));
        }

        $values = null;
        if (isset($options['values'])) {
            if (!$options['values'] instanceof ValueBag) {
                throw new InvalidArgumentException('The "values" option must be an instance of Blackfire\Player\ValueBag.');
            }

            $values = $options['values'];
        }

        if (isset($options['expectations'])) {
            $this->checkExpectations($options['expectations'], $options['values'], $crawler, $request, $response);
        }

        if (isset($options['extractions'])) {
            $this->extractVariables($options['extractions'], $options['values'], $crawler, $request, $response);
        }

        return $response;
    }

    private function checkExpectations(array $expectations, ValueBag $values = null, Crawler $crawler = null, RequestInterface $request, ResponseInterface $response)
    {
        if (null === $values) {
            $values = new ValueBag();
        }
        $variables = $this->createVariables($response, $crawler);

        foreach ($expectations as $expression) {
            try {
                $result = $this->language->evaluate($expression, $variables + $values->all(true));

                if (null !== $result && false !== $result && 0 !== $result) {
                    $this->logger and $this->logger->debug(sprintf('Expectation "%s" pass', $expression), ['request' => $request->getHeaderLine('X-Request-Id')]);
                } else {
                    $msg = sprintf('Expectation "%s" failed', $expression);

                    $this->logger and $this->logger->error($msg, ['request' => $request->getHeaderLine('X-Request-Id')]);

                    throw new ExpectationFailureException($msg);
                }
            } catch (ExpressionSyntaxError $e) {
                $msg = sprintf('Expectation syntax error in "%s": %s', $expression, $e->getMessage());

                $this->logger and $this->logger->critical($msg, ['request' => $request->getHeaderLine('X-Request-Id')]);

                throw new ExpectationErrorException($msg);
            }
        }
    }

    private function extractVariables($extractions, ValueBag $values = null, Crawler $crawler = null, RequestInterface $request, ResponseInterface $response)
    {
        if (null === $values) {
            throw new LogicException('Unable to extract variables if no ValueBag is registered.');
        }

        $variables = $this->createVariables($response, $crawler);

        foreach ($extractions as $name => $extract) {
            list($expression, $attributes) = $extract;
            if (!is_array($attributes)) {
                $attributes = [$attributes];
            }

            try {
                $data = $this->language->evaluate($expression, $variables + $values->all(true));
                if ($data instanceof Crawler) {
                    $value = $data->extract($attributes);

                    if (count($attributes) == 1) {
                        $data = count($data) > 1 ? $value : $value[0];
                    } else {
                        $data = $value;
                    }
                }

                $values->set($name, $data);
            } catch (ExpressionSyntaxError $e) {
                $msg = sprintf('Syntax Error in "%s": %s', $expression, $e->getMessage());

                $this->logger and $this->logger->critical($msg, ['request' => $request->getHeaderLine('X-Request-Id')]);

                throw new ExpectationErrorException($msg);
            }
        }
    }

    private function createVariables(ResponseInterface $response, Crawler $crawler = null)
    {
        return ['_response' => $response, '_crawler' => $crawler];
    }
}
