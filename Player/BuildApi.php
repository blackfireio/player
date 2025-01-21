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

use Blackfire\Player\Adapter\BlackfireSdkAdapterInterface;
use Blackfire\Player\Build\Build;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
class BuildApi
{
    public function __construct(
        /** @deprecated replace it with the blackfireHttpClient */
        private readonly BlackfireSdkAdapterInterface $blackfireSdkClient,
        private readonly HttpClientInterface $blackfireHttpClient,
    ) {
    }

    public function getOrCreate(string $env, ScenarioSet $scenarioSet): Build
    {
        $scenarioSetBag = $scenarioSet->getExtraBag();
        $buildKey = 'blackfire_build:'.$env;
        $build = $scenarioSetBag->has($buildKey) ? $scenarioSetBag->get($buildKey) : null;
        if (!$build) {
            if (isset($_ENV['BLACKFIRE_BUILD_UUID'])) {
                $build = new Build($_ENV['BLACKFIRE_BUILD_UUID']);
            } else {
                $buildName = $scenarioSetBag->has('blackfire_build_name') ? $scenarioSetBag->get('blackfire_build_name') : null;
                $build = $this->createBuild($buildName, $env);
            }

            $scenarioSetBag->set($buildKey, $build);
        }

        return $build;
    }

    public function createBuild(string|null $buildName, string $env): Build
    {
        $options = [
            'trigger_name' => 'Blackfire Player',
            'build_name' => $buildName,
        ];

        if (isset($_SERVER['BLACKFIRE_EXTERNAL_ID'])) {
            $options['external_id'] = $_SERVER['BLACKFIRE_EXTERNAL_ID'];
        }

        if (isset($_SERVER['BLACKFIRE_EXTERNAL_PARENT_ID'])) {
            $options['external_parent_id'] = $_SERVER['BLACKFIRE_EXTERNAL_PARENT_ID'];
        }

        $build = $this->blackfireSdkClient->startBuild($env, $options);
        SentrySupport::addBreadcrumb('Build has been created', [
            'uuid' => $build->uuid,
        ]);

        return $build;
    }

    public function updateBuild(Build $build, string $jsonView): void
    {
        $response = $this->blackfireHttpClient->request('POST', \sprintf('/api/v3/builds/%s/update', $build->uuid), [
            'body' => $jsonView,
        ]);

        // consume HttpResponse Asynchronously
        // as we might run the player in concurrent mode using fibers, we need to mark the fiber suspended to allow
        // asynchronous response processing
        foreach ($this->blackfireHttpClient->stream($response, 0.01) as $chunk) {
            if (!$chunk->isTimeout()) {
                continue;
            }
            if (null === \Fiber::getCurrent()) {
                continue;
            }
            \Fiber::suspend();
        }
    }
}
