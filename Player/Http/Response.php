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
class Response
{
    public function __construct(
        public readonly Request $request,
        public readonly int $statusCode,
        /** @var string[][] */
        public readonly array $headers,
        public readonly string $body,
        public readonly array $stats,
    ) {
    }

    public function toString(): string
    {
        $msg = 'HTTP/1.1 '.$this->statusCode;

        foreach ($this->headers as $name => $values) {
            if ('set-cookie' === $name) {
                foreach ($values as $value) {
                    $msg .= "\r\n{$name}: ".$value;
                }
            } else {
                $msg .= "\r\n{$name}: ".implode(', ', $values);
            }
        }

        return $msg."\r\n\r\n".$this->body;
    }
}
