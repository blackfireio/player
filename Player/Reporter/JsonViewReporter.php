<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Reporter;

use Blackfire\Player\Build\Build;
use Blackfire\Player\BuildApi;
use Blackfire\Player\Enum\BuildStatus;
use Blackfire\Player\Exception\ApiCallException;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\SentrySupport;
use Blackfire\Player\Serializer\ScenarioSetSerializer;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

/**
 * @internal
 */
class JsonViewReporter
{
    public function __construct(
        private readonly ScenarioSetSerializer $serializer,
        private readonly BuildApi $buildApi,
        private readonly OutputInterface $output,
    ) {
    }

    public function report(ScenarioSet $scenarioSet): void
    {
        $builds = array_filter(
            $scenarioSet->getExtraBag()->all(),
            static fn (int|string $key): bool => \is_string($key) && str_starts_with($key, 'blackfire_build:'),
            \ARRAY_FILTER_USE_KEY
        );

        /** @var Build $build */
        foreach ($builds as $build) {
            $jsonView = $this->serializer->serializeForJsonView($scenarioSet, $build);

            try {
                $this->buildApi->updateBuild($build, $jsonView);
            } catch (HttpExceptionInterface $e) {
                $statusCode = $e->getResponse()->getStatusCode();

                if (BuildStatus::DONE === $scenarioSet->getStatus()) {
                    SentrySupport::captureException($e, [
                        'extra' => [
                            'json_view' => $jsonView,
                        ],
                    ]);
                    throw new ApiCallException(\sprintf('%d: %s', $statusCode, 'Failed to send the last jsonview'), $statusCode, $e);
                }
                $this->output->writeln(\sprintf('<warning> </>%s', $e->getMessage()));
            }
        }
    }
}
