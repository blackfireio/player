<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Extension;

use Blackfire\Player\Scenario;
use Blackfire\Player\Step;
use Blackfire\Player\ValueBag;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
interface ExtensionInterface
{
    public function registerHandlers(HandlerStack $stack);

    public function preRun(Scenario $scenario, ValueBag $values, ValueBag $extra);

    public function prepareRequest(Step $step, ValueBag $values, RequestInterface $request, $options);

    public function processResponse(RequestInterface $request, ResponseInterface $response, Step $step, ValueBag $values = null, Crawler $crawler = null);

    public function postRun(Scenario $scenario, ValueBag $values, ValueBag $extra);
}
