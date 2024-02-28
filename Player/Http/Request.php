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
 * @internal
 */
class Request
{
    public const CONTENT_TYPE_RAW = '';
    public const CONTENT_TYPE_JSON = 'application/json';
    public const CONTENT_TYPE_FORM = 'application/x-www-form-urlencoded';

    public function __construct(
        public string $method,
        public string $uri,
        /** @var string[][] */
        public array $headers = [],
        public string|iterable|\Closure|null $body = null,
        public array $options = [],
    ) {
    }

    public function toString(): string
    {
        $uri = parse_url($this->uri) + ['path' => '/', 'query' => '', 'host' => ''];

        $target = $uri['path'] ?: '/';
        if ($uri['query']) {
            $target .= '?'.$uri['query'];
        }

        $msg = trim($this->method.' '.$target).' HTTP/1.1';
        if (!isset($this->headers['host'])) {
            $msg .= "\r\nHost: ".$uri['host'];
        }

        foreach ($this->headers as $name => $values) {
            if ('set-cookie' === $name) {
                foreach ($values as $value) {
                    $msg .= "\r\n{$name}: ".$value;
                }
            } else {
                $msg .= "\r\n{$name}: ".implode(', ', $values);
            }
        }

        if (\is_string($this->body)) {
            return $msg."\r\n\r\n".$this->body;
        }

        return $msg."\r\n\r\n";
    }
}
