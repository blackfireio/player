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

use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class ConfigurableStep extends AbstractStep
{
    private ?string $auth = null;
    /** @var string[] */
    private array $headers = [];
    private ?string $wait = null;
    private ?string $json = null;
    #[Ignore]
    private ?string $followRedirects = null;
    private ?string $blackfire = null;
    private ?string $samples = null;
    private ?string $warmup = null;

    public function followRedirects(?string $follow): self
    {
        $this->followRedirects = $follow;

        return $this;
    }

    public function header(string $header): self
    {
        $this->headers[] = $header;

        return $this;
    }

    public function auth(?string $auth): self
    {
        $this->auth = $auth;

        return $this;
    }

    public function wait(?string $wait): self
    {
        $this->wait = $wait;

        return $this;
    }

    public function json(?string $json): self
    {
        $this->json = $json;

        return $this;
    }

    public function blackfire(?string $env): self
    {
        $this->blackfire = $env;

        return $this;
    }

    public function samples(?string $samples): self
    {
        $this->samples = $samples;

        return $this;
    }

    public function warmup(?string $warmup): self
    {
        $this->warmup = $warmup;

        return $this;
    }

    #[SerializedName('follow_redirects')]
    public function isFollowingRedirects(): ?string
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

    public function getAuth(): ?string
    {
        return $this->auth;
    }

    public function getWait(): ?string
    {
        return $this->wait;
    }

    public function isJson(): ?string
    {
        return $this->json;
    }

    #[Ignore]
    public function getBlackfire(): ?string
    {
        return $this->blackfire;
    }

    #[SerializedName('is_blackfire_enabled')]
    public function isBlackfireEnabled(): ?bool
    {
        if (!$this->getType()) {
            return null;
        }

        return 'false' !== $this->blackfire;
    }

    public function getSamples(): ?string
    {
        return $this->samples;
    }

    public function getWarmup(): ?string
    {
        return $this->warmup;
    }
}
