<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Psr7;

use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class CrawlerFactory
{
    public static function create(ResponseInterface $response, $uri): ?Crawler
    {
        if (!$response->hasHeader('Content-Type')) {
            return null;
        }

        if (false === strpos($response->getHeaderLine('Content-Type'), 'html') && false === strpos($response->getHeaderLine('Content-Type'), 'xml')) {
            return null;
        }

        $crawler = new Crawler(null, $uri);
        $crawler->addContent((string) $response->getBody(), $response->getHeaderLine('Content-Type'));

        return $crawler;
    }
}
