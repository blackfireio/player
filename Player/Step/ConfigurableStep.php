<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Step;

use Symfony\Component\Serializer\Annotation as SymfonySerializer;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class ConfigurableStep extends AbstractStep
{
    private $auth;
    private $headers = [];
    private $wait;
    private $json;
    /** @SymfonySerializer\Ignore */
    private $followRedirects;
    private $blackfire;
    private $blackfireRequest;
    private $blackfireScenario;
    private $samples;
    private $warmup;

    public function followRedirects($follow)
    {
        $this->followRedirects = $follow;

        return $this;
    }

    public function header($header)
    {
        $this->headers[] = $header;

        return $this;
    }

    public function auth($auth)
    {
        $this->auth = $auth;

        return $this;
    }

    public function wait($wait)
    {
        $this->wait = $wait;

        return $this;
    }

    public function json()
    {
        $this->json = true;

        return $this;
    }

    public function blackfire($env)
    {
        $this->blackfire = $env;

        return $this;
    }

    public function blackfireRequest($request)
    {
        $this->blackfireRequest = $request;

        return $this;
    }

    public function blackfireScenario($scenario)
    {
        $this->blackfireScenario = $scenario;

        return $this;
    }

    public function samples($samples)
    {
        $this->samples = $samples;

        return $this;
    }

    public function warmup($warmup)
    {
        $this->warmup = $warmup;

        return $this;
    }

    /** @SymfonySerializer\SerializedName("follow_redirects") */
    public function isFollowingRedirects()
    {
        return $this->followRedirects;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getAuth()
    {
        return $this->auth;
    }

    public function getWait()
    {
        return $this->wait;
    }

    public function isJson()
    {
        return $this->json;
    }

    /** @SymfonySerializer\Ignore() */
    public function getBlackfire()
    {
        return $this->blackfire;
    }

    /** @SymfonySerializer\SerializedName("is_blackfire_enabled") */
    public function isBlackfireEnabled(): ?bool
    {
        if (!$this->getType()) {
            return null;
        }

        return 'false' !== $this->blackfire;
    }

    public function getBlackfireRequest()
    {
        return $this->blackfireRequest;
    }

    public function getBlackfireScenario()
    {
        return $this->blackfireScenario;
    }

    public function getSamples()
    {
        return $this->samples;
    }

    public function getWarmup()
    {
        return $this->warmup;
    }
}
