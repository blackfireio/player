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
use Symfony\Component\DomCrawler\UriResolver as SymfonyUriResolver;

/**
 * @internal
 */
class UriResolver
{
    public function resolveUri(string|null $baseUri, string $uri): string
    {
        if (!$baseUri) {
            if (null === parse_url($uri, \PHP_URL_SCHEME)) {
                throw new CrawlException(sprintf('Unable to crawl a non-absolute URI (/%s). Did you forget to set an "endpoint"?', $uri));
            }

            return $uri;
        }

        $resolvedUri = SymfonyUriResolver::resolve($uri, $baseUri);

        return rtrim($resolvedUri, '?');
    }

    public function buildUrl(array $parsed): string
    {
        $pass = $parsed['pass'] ?? null;
        $user = $parsed['user'] ?? null;
        $userinfo = null !== $pass ? "$user:$pass" : $user;

        $port = $parsed['port'] ?? 0;
        $scheme = $parsed['scheme'] ?? '';
        $query = $parsed['query'] ?? '';
        $fragment = $parsed['fragment'] ?? '';
        $authority = (
            (null !== $userinfo ? "$userinfo@" : '').
            ($parsed['host'] ?? '').
            ($port ? ":$port" : '')
        );

        return
            (\strlen($scheme) > 0 ? "$scheme:" : '').
            (\strlen($authority) > 0 ? "//$authority" : '').
            ($parsed['path'] ?? '').
            (\strlen($query) > 0 ? "?$query" : '').
            (\strlen($fragment) > 0 ? "#$fragment" : '')
        ;
    }
}
