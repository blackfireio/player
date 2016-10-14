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

use Blackfire\Player\Context;
use Blackfire\Player\RunnerInterface;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\RequestInterface;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
final class Runner implements RunnerInterface
{
    private $clients;
    private $handlersRegistered = false;

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
                throw new LogicException('The Guzzle runner accepts a Guzzle client or an array of Guzzle clients.');
            }
        }

        $this->clients = $clients;
    }

    public function getMaxConcurrency()
    {
        return count($this->clients);
    }

    public function send($clientId, RequestInterface $request, Context $context)
    {
        $this->registerHandlers();

        return $this->clients[$clientId]->sendAsync($request, [
            'player_context' => $context,
            'http_errors' => false,
            'allow_redirects' => false,
        ]);
    }

    public function end($clientId)
    {
        // cleanup cookies on the client
        if ($cookieJar = $this->clients[$clientId]->getConfig('cookies')) {
            $cookieJar->clear();
        }
    }

    private function registerHandlers()
    {
        if ($this->handlersRegistered) {
            return;
        }

        foreach ($this->clients as $client) {
            $stack = $client->getConfig('handler');
            $stack->unshift(PlayerMiddleware::create(), 'scenario');
        }

        $this->handlersRegistered = true;
    }
}
