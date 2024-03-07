<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Adapter;

use Blackfire\Client;
use Blackfire\ClientConfiguration;
use Blackfire\Exception\ApiException;
use Blackfire\Player\Build\Build;
use Blackfire\Player\Exception\ApiCallException;
use Blackfire\Profile;
use Blackfire\Profile\Configuration;
use Blackfire\Profile\Request;

class BlackfireSdkAdapter implements BlackfireSdkAdapterInterface
{
    public function __construct(
        private readonly Client $blackfireClient,
    ) {
    }

    public function getConfiguration(): ClientConfiguration
    {
        try {
            return $this->blackfireClient->getConfiguration();
        } catch (ApiException $e) {
            throw $this->createApiCallException($e);
        }
    }

    public function createRequest(string|Configuration|null $config = null): Request
    {
        try {
            return $this->blackfireClient->createRequest($config);
        } catch (ApiException $e) {
            throw $this->createApiCallException($e);
        }
    }

    public function updateProfile(string $uuid, string $title, array|null $metadata = null): bool
    {
        try {
            return $this->blackfireClient->updateProfile($uuid, $title, $metadata);
        } catch (ApiException $e) {
            throw $this->createApiCallException($e);
        }
    }

    public function getProfile(string $uuid): Profile
    {
        try {
            return $this->blackfireClient->getProfile($uuid);
        } catch (ApiException $e) {
            throw $this->createApiCallException($e);
        }
    }

    public function startBuild(string|null $env = null, array $options = []): Build
    {
        try {
            $sdkBuild = $this->blackfireClient->startBuild($env, $options);

            return new Build($sdkBuild->getUuid(), $sdkBuild->getUrl());
        } catch (ApiException $e) {
            throw $this->createApiCallException($e);
        }
    }

    private function createApiCallException(ApiException $e): ApiCallException
    {
        // Remove the headers from the exception
        $message = preg_replace('/ \[headers: [^\]]*\]$/', '', $e->getMessage());

        return new ApiCallException($message, $e->getCode(), $e);
    }
}
