<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player;

use Blackfire\Player\Extension\ExtensionInterface;
use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\Exception\RuntimeException;
use Blackfire\Player\ExpressionLanguage\Provider as LanguageProvider;
use Blackfire\Player\Guzzle\ExpectationsMiddleware;
use Blackfire\Player\Guzzle\RequestFactory;
use Blackfire\Player\Guzzle\StepMiddleware;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise\EachPromise;
use Psr\Log\LoggerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class Player
{
    private $clients = [];
    private $language;
    private $requestFactory;
    private $handlersRegistered = false;
    private $logger;
    private $extensions = [];

    /**
     * @param GuzzleClient|GuzzleClient[] $client
     */
    public function __construct($client)
    {
        $clients = [];

        if (is_array($client)) {
            $clients = $client;
        } else {
            $clients[] = $client;
        }

        foreach ($clients as $c) {
            if (!$c instanceof GuzzleClient) {
                throw new LogicException('Blackfire Player accepts a Guzzle client or an array of Guzzle clients.');
            }
        }

        $this->clients = $clients;
    }

    public function addExtension(ExtensionInterface $extension)
    {
        $this->extensions[] = $extension;
    }

    /**
     * @return Result
     */
    public function run(Scenario $scenario)
    {
        return $this->runMulti(new ScenarioSet([$scenario]))[0];
    }

    /**
     * @return array
     */
    public function runMulti(ScenarioSet $scenarioSet, $concurrency = null)
    {
        $results = [];
        $this->registerHandlers();

        $requests = [];
        foreach ($scenarioSet as $key => $scenario) {
            $valueBag = new ValueBag($scenario->getValues());
            $extraBag = new ValueBag();
            $step = $scenario->getRoot();
            $options = [
                'step' => $step,
                'values' => $valueBag,
                'extra' => $extraBag,
                'http_errors' => true,
            ];

            foreach ($this->extensions as $extension) {
                $extension->preRun($scenario, $valueBag, $extraBag);
            }

            $requests[$key] = [$this->createRequest($step, $valueBag), $options, $scenario];
        }

        if (!$concurrency) {
            $concurrency = min(count($requests), count($this->clients));
        }

        $this->logger and $this->logger->debug(sprintf('Concurrency set to "%d"', $concurrency));

        if ($concurrency > count($this->clients)) {
            throw new RuntimeException('Concurrency (%d) must be less than or equal to the number of Guzzle clients (%s)', $concurrency, count($this->clients));
        }

        $fulfilled = $rejected = function ($response, $key) use (&$results, $requests) {
            $valueBag = $requests[$key][1]['values'];
            $extraBag = $requests[$key][1]['extra'];
            $scenario = $requests[$key][2];

            $exception = null;
            if ($response instanceof \Exception) {
                $exception = $response;

                $this->logger and $this->logger->error(sprintf('Scenario "%s" ended with an error: %s', $scenario->getTitle(), $exception->getMessage()));
            }

            foreach ($this->extensions as $extension) {
                $extension->postRun($scenario, $valueBag, $extraBag);
            }

            $results[$key] = new Result($valueBag, $extraBag, $exception);
        };

        $requests = function () use ($requests) {
            $i = 0;
            $count = count($this->clients);
            foreach ($requests as $key => $data) {
                $client = (++$i) % $count;

                $this->logger and $this->logger->info(sprintf('Starting scenario "%s" (sent to client %d)', $data[2]->getTitle(), $client));

                yield $key => $this->clients[$client]->sendAsync($data[0], $data[1]);

                // cleanup cookies on the client
                if ($cookieJar = $this->clients[$client]->getConfig('cookies')) {
                    $cookieJar->clear();
                }
            }
        };

        (new EachPromise($requests(), [
            'concurrency' => $concurrency,
            'fulfilled' => $fulfilled,
            'rejected' => $rejected,
        ]))->promise()->wait();

        return $results;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setExpressionLanguage(ExpressionLanguage $language)
    {
        $this->language = $language;
    }

    private function registerHandlers()
    {
        if ($this->handlersRegistered) {
            return;
        }

        foreach ($this->clients as $client) {
            $stack = $client->getConfig('handler');
            $stack->unshift(StepMiddleware::create($this->getRequestFactory(), $this->getLanguage(), $this->extensions, $this->logger), 'scenario');
            $stack->push(ExpectationsMiddleware::create($this->getLanguage(), $this->logger), 'expectations');

            foreach ($this->extensions as $extension) {
                $extension->registerHandlers($stack);
            }
        }

        $this->handlersRegistered = true;
    }

    private function createRequest(Step $step, ValueBag $values)
    {
        return $this->getRequestFactory()->create($step, $values);
    }

    private function getRequestFactory()
    {
        if (null === $this->requestFactory) {
            $this->requestFactory = new RequestFactory($this->getLanguage());
        }

        return $this->requestFactory;
    }

    private function getLanguage()
    {
        if (null === $this->language) {
            $this->language = new ExpressionLanguage(null, [new LanguageProvider()]);
        }

        return $this->language;
    }
}
