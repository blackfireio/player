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

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class ConfigurableStep extends AbstractStep
{
    private string|null $auth = null;
    /** @var string[] */
    private array $headers = [];
    private string|null $wait = null;
    private string|null $json = null;
    #[Ignore]
    private string|null $followRedirects = null;
    private string|null $blackfire = null;
    private string|null $samples = null;
    private string|null $warmup = null;

    public function followRedirects(string|null $follow): self
    {
        $this->followRedirects = $follow;

        return $this;
    }

    public function header(string $header): self
    {
        $this->headers[] = $header;

        return $this;
    }

    public function auth(string|null $auth): self
    {
        $this->auth = $auth;

        return $this;
    }

    public function wait(string|null $wait): self
    {
        $this->wait = $wait;

        return $this;
    }

    public function json(string|null $json): self
    {
        $this->json = $json;

        return $this;
    }

    public function blackfire(string|null $env): self
    {
        $this->blackfire = $env;

        return $this;
    }

    public function samples(string|null $samples): self
    {
        $this->samples = $samples;

        return $this;
    }

    public function warmup(string|null $warmup): self
    {
        $this->warmup = $warmup;

        return $this;
    }

    #[SerializedName('follow_redirects')]
    public function isFollowingRedirects(): string|null
    {
        return $this->followRedirects;
    }

    /**
     * @return string[]
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getAuth(): string|null
    {
        return $this->auth;
    }

    public function getWait(): string|null
    {
        return $this->wait;
    }

    public function isJson(): string|null
    {
        return $this->json;
    }

    #[Ignore]
    public function getBlackfire(): string|null
    {
        return $this->blackfire;
    }

    #[SerializedName('is_blackfire_enabled')]
    public function isBlackfireEnabled(): bool|null
    {
        if (!$this->getType()) {
            return null;
        }

        return 'false' !== $this->blackfire;
    }

    public function getSamples(): string|null
    {
        return $this->samples;
    }

    public function getWarmup(): string|null
    {
        return $this->warmup;
    }
}
