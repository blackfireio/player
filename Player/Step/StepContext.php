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

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class StepContext
{
    private string|null $auth = null;
    /** @var string[] */
    private array $headers = [];
    private string|null $wait = null;
    private string|null $json = null;
    private string|null $endpoint = null;
    private string|null $followRedirects = null;
    /** @var mixed[] */
    private array $variables = [];
    private string|null $blackfire = null;
    private string|null $samples = null;
    private string|null $warmup = null;
    private string|null $workingDir = null;

    public function update(ConfigurableStep $step, array $variables): void
    {
        $this->workingDir = $step->getFile() ? rtrim(\dirname($step->getFile()), '/').'/' : null;

        if (null !== $step->getWait()) {
            $this->wait = $step->getWait();
        }

        if (null !== $step->getAuth()) {
            $this->auth = $step->getAuth();
        }

        if (null !== $step->isJson()) {
            $this->json = $step->isJson();
        }

        if (null !== $step->isFollowingRedirects()) {
            $this->followRedirects = $step->isFollowingRedirects();
        }

        foreach ($step->getHeaders() as $header) {
            $this->headers[] = $header;
        }

        if (null !== $step->getBlackfire()) {
            $this->blackfire = $step->getBlackfire();
        }

        if (null !== $step->getSamples()) {
            $this->samples = $step->getSamples();
        }

        if (null !== $step->getWarmup()) {
            $this->warmup = $step->getWarmup();
        }

        if ($step instanceof BlockStep) {
            if (null !== $step->getEndpoint()) {
                $this->endpoint = $step->getEndpoint();
            }

            foreach ($variables as $key => $value) {
                $this->variables[$key] = $value;
            }
        }
    }

    public function variable(string $key, mixed $value): void
    {
        $this->variables[$key] = $value;
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

    public function isFollowingRedirects(): string
    {
        return null === $this->followRedirects ? 'false' : $this->followRedirects;
    }

    public function isJson(): string
    {
        return null === $this->json ? 'false' : $this->json;
    }

    public function getEndpoint(): string|null
    {
        return $this->endpoint;
    }

    /**
     * @return mixed[]
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getBlackfireEnv(): string|null
    {
        return $this->blackfire;
    }

    public function getSamples(): string
    {
        return null === $this->samples ? '1' : $this->samples;
    }

    public function getWarmup(): string
    {
        return null === $this->warmup ? 'true' : $this->warmup;
    }

    public function getWorkingDir(): string|null
    {
        return $this->workingDir;
    }
}
