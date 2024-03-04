<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Http;

use Symfony\Component\DomCrawler\Crawler;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class CrawlerFactory
{
    public static function create(Response $response, ?string $uri): ?Crawler
    {
        $contentType = $response->headers['content-type'][0] ?? null;
        if (!$contentType) {
            return null;
        }

        if (!str_contains($contentType, 'html') && !str_contains($contentType, 'xml')) {
            return null;
        }

        $crawler = new Crawler(null, $uri);
        $crawler->addContent($response->body, $contentType);

        return $crawler;
    }
}
