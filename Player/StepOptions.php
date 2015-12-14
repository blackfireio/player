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

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class StepOptions
{
    private $headers = [];
    private $delay;
    private $endpoint;

    public function endpoint($endpoint)
    {
        $this->endpoint = $endpoint;
    }

    public function delay($delay)
    {
        $this->delay = $delay;
    }

    public function header($key, $value)
    {
        $this->headers[$key][] = $value;
    }

    public function auth($username, $password)
    {
        $this->header('Authorization', $this->generateAuthorizationHeader($username, $password));
    }

    public function getEndpoint()
    {
        return $this->endpoint;
    }

    public function getDelay()
    {
        return $this->delay;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function generateAuthorizationHeader($username, $password)
    {
        return 'Basic '.base64_encode(sprintf('%s:%s', $username, $password));
    }
}
