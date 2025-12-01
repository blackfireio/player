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

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @see https://github.com/symfony/browser-kit/blob/6.2/CookieJar.php
 *
 * @internal
 */
class CookieJar
{
    /** @var Cookie[][][] */
    private array $cookieJar = [];

    public function set(Cookie $cookie): void
    {
        $this->cookieJar[$cookie->getDomain()][$cookie->getPath()][$cookie->getName()] = $cookie;
    }

    public function clear(): void
    {
        $this->cookieJar = [];
    }

    public function updateFromSetCookie(array $setCookies, string|null $uri = null): void
    {
        $cookies = [];

        foreach ($setCookies as $cookie) {
            foreach (explode(',', (string) $cookie) as $i => $part) {
                if (0 === $i || preg_match('/^(?P<token>\s*[0-9A-Za-z!#\$%\&\'\*\+\-\.^_`\|~]+)=/', $part)) {
                    $cookies[] = ltrim($part);
                } else {
                    $cookies[\count($cookies) - 1] .= ','.$part;
                }
            }
        }

        foreach ($cookies as $cookie) {
            try {
                $this->set(Cookie::fromString($cookie, $uri));
            } catch (\InvalidArgumentException) {
                // invalid cookies are just ignored
            }
        }
    }

    public function updateFromResponse(ResponseInterface $response, string|null $uri = null): void
    {
        $headers = $response->getHeaders(false);
        $this->updateFromSetCookie($headers['set-cookie'] ?? [], $uri);
    }

    public function allValues(string $uri): array
    {
        $this->flushExpiredCookies();

        $parts = array_replace(['path' => '/'], parse_url($uri));
        $cookies = [];
        foreach ($this->cookieJar as $domain => $pathCookies) {
            if ('' !== $domain) {
                $domain = '.'.ltrim((string) $domain, '.');
                if (!str_ends_with('.'.$parts['host'], $domain)) {
                    continue;
                }
            }

            foreach ($pathCookies as $path => $namedCookies) {
                if (!str_starts_with((string) $parts['path'], (string) $path)) {
                    continue;
                }

                foreach ($namedCookies as $cookie) {
                    if ($cookie->isSecure() && 'https' !== $parts['scheme']) {
                        continue;
                    }

                    $cookies[$cookie->getName()] = $cookie->getValue();
                }
            }
        }

        return $cookies;
    }

    public function flushExpiredCookies(): void
    {
        foreach ($this->cookieJar as $domain => $pathCookies) {
            foreach ($pathCookies as $path => $namedCookies) {
                foreach ($namedCookies as $name => $cookie) {
                    if ($cookie->isExpired()) {
                        unset($this->cookieJar[$domain][$path][$name]);
                    }
                }
            }
        }
    }
}
