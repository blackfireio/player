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

use Blackfire\ClientConfiguration;
use Blackfire\Player\Build\Build;
use Blackfire\Profile;
use Blackfire\Profile\Configuration;
use Blackfire\Profile\Request;

interface BlackfireSdkAdapterInterface
{
    public function getConfiguration(): ClientConfiguration;

    public function createRequest(string|Configuration|null $config = null): Request;

    public function updateProfile(string $uuid, string $title, ?array $metadata = null): bool;

    public function getProfile(string $uuid): Profile;

    public function startBuild(?string $env = null, array $options = []): Build;
}
