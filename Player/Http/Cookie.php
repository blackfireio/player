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

/**
 * @see https://github.com/symfony/browser-kit/blob/6.2/Cookie.php
 *
 * @internal
 */
final readonly class Cookie implements \Stringable
{
    /**
     * Handles dates as defined by RFC 2616 section 3.3.1, and also some other
     * non-standard, but common formats.
     */
    private const array DATE_FORMATS = [
        'D, d M Y H:i:s T',
        'D, d-M-y H:i:s T',
        'D, d-M-Y H:i:s T',
        'D, d-m-y H:i:s T',
        'D, d-m-Y H:i:s T',
        'D M j G:i:s Y',
        'D M d H:i:s Y T',
    ];

    private function __construct(
        private string $name,
        private string $value,
        private string|null $expires,
        private string $path,
        private string $domain,
        private bool $secure,
    ) {
    }

    /**
     * Returns the HTTP representation of the Cookie.
     */
    public function __toString(): string
    {
        $cookie = \sprintf('%s=%s', $this->name, $this->value);

        if (null !== $this->expires) {
            $dateTime = \DateTimeImmutable::createFromFormat('U', $this->expires, new \DateTimeZone('GMT'));
            $cookie .= '; expires='.str_replace('+0000', '', $dateTime->format(self::DATE_FORMATS[0]));
        }

        if ('' !== $this->domain) {
            $cookie .= '; domain='.$this->domain;
        }

        if ('' !== $this->path) {
            $cookie .= '; path='.$this->path;
        }

        if ($this->secure) {
            $cookie .= '; secure';
        }

        return $cookie;
    }

    /**
     * Creates a Cookie instance from a Set-Cookie header value.
     *
     * @throws \InvalidArgumentException
     */
    public static function fromString(string $cookie, string|null $url = null): static
    {
        $parts = explode(';', $cookie);

        if (!str_contains($parts[0], '=')) {
            throw new \InvalidArgumentException(\sprintf('The cookie string "%s" is not valid.', $parts[0]));
        }

        [$name, $value] = explode('=', array_shift($parts), 2);

        $values = [
            'name' => trim($name),
            'value' => trim($value),
            'expires' => null,
            'path' => '/',
            'domain' => '',
            'secure' => false,
        ];

        if (null !== $url) {
            if ((false === $urlParts = parse_url($url)) || !isset($urlParts['host'])) {
                throw new \InvalidArgumentException(\sprintf('The URL "%s" is not valid.', $url));
            }

            $values['domain'] = $urlParts['host'];
            $values['path'] = isset($urlParts['path']) ? substr($urlParts['path'], 0, strrpos($urlParts['path'], '/')) : '';
        }

        foreach ($parts as $part) {
            $part = trim($part);

            if ('secure' === strtolower($part)) {
                // Ignore the secure flag if the original URI is not given or is not HTTPS
                if (null === $url) {
                    continue;
                }
                if (!isset($urlParts['scheme'])) {
                    continue;
                }
                if ('https' !== $urlParts['scheme']) {
                    continue;
                }
                $values['secure'] = true;
                continue;
            }

            if (2 === \count($elements = explode('=', $part, 2))) {
                if ('expires' === strtolower($elements[0])) {
                    $elements[1] = self::parseDate($elements[1]);
                }

                $values[strtolower($elements[0])] = $elements[1];
            }
        }

        return new self(
            $values['name'],
            $values['value'],
            $values['expires'],
            $values['path'] ?: '/',
            $values['domain'],
            $values['secure'],
        );
    }

    private static function parseDate(string $dateValue): string|null
    {
        // trim single quotes around date if present
        if (($length = \strlen($dateValue)) > 1 && "'" === $dateValue[0] && "'" === $dateValue[$length - 1]) {
            $dateValue = substr($dateValue, 1, -1);
        }

        foreach (self::DATE_FORMATS as $dateFormat) {
            if (false !== $date = \DateTimeImmutable::createFromFormat($dateFormat, $dateValue, new \DateTimeZone('GMT'))) {
                return $date->format('U');
            }
        }

        // attempt a fallback for unusual formatting
        if (false !== $date = date_create_immutable($dateValue, new \DateTimeZone('GMT'))) {
            return $date->format('U');
        }

        return null;
    }

    /**
     * Gets the name of the cookie.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the raw value of the cookie.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Gets the path of the cookie.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Gets the domain of the cookie.
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Returns the secure flag of the cookie.
     */
    public function isSecure(): bool
    {
        return $this->secure;
    }

    /**
     * Returns true if the cookie has expired.
     */
    public function isExpired(): bool
    {
        return null !== $this->expires && 0 != $this->expires && $this->expires <= time();
    }
}
