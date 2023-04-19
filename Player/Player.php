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

use Blackfire\Player\Exception\RuntimeException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\Extension\ExtensionInterface;
use Blackfire\Player\Extension\FollowExtension;
use Blackfire\Player\Extension\NameResolverExtension;
use Blackfire\Player\Extension\TestsExtension;
use Blackfire\Player\Extension\TmpDirExtension;
use Blackfire\Player\Extension\WaitExtension;
use Blackfire\Player\Extension\WatchdogExtension;
use Blackfire\Player\Guzzle\StepConverter;
use GuzzleHttp\Promise\EachPromise;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class Player
{
    private array $extensions = [];

    public function __construct(
        private readonly RunnerInterface $runner,
        private readonly ExpressionLanguage $language,
    ) {
        $this->addExtension(new TmpDirExtension());
        $this->addExtension(new NameResolverExtension($this->language), 1024);
        $this->addExtension(new TestsExtension($this->language), 512);
        $this->addExtension(new WaitExtension($this->language));
        $this->addExtension(new FollowExtension($this->language));
        $this->addExtension(new WatchdogExtension());
    }

    public function addExtension(ExtensionInterface $extension, $priority = 0): void
    {
        $this->extensions[$priority][] = $extension;
    }

    public function run(ScenarioSet $scenarioSet, $concurrency = null): Results
    {
        krsort($this->extensions);
        $extensions = \call_user_func_array('array_merge', $this->extensions);

        $runs = [];
        $requestGenerators = [];
        $i = 0;
        foreach ($scenarioSet as $scenario) {
            $key = null !== $scenario->getKey() ? $scenario->getKey() : ++$i;

            $context = new Context($scenario->getName(), $scenarioSet->getExtraBag());
            $stepConverter = new StepConverter($this->language, $context);
            $requestGenerator = new Psr7\RequestGenerator($this->language, $stepConverter, $scenario, $context);
            $requestGenerator = new Psr7\ExtensibleRequestGenerator($requestGenerator->getIterator(), $scenario, $context, $extensions);
            $requestIterator = $requestGenerator->getIterator();
            $context->setGenerator($requestIterator);

            $requestGenerators[$key] = $requestGenerator;
            $runs[$key] = new Run($requestIterator, $scenario, $context);
        }

        if (!$concurrency) {
            $concurrency = min(\count($runs), $this->runner->getMaxConcurrency());
        } elseif ($concurrency > $this->runner->getMaxConcurrency()) {
            throw new RuntimeException(sprintf('Concurrency (%d) must be less than or equal to the number of clients (%s).', $concurrency, $this->runner->getMaxConcurrency()));
        }

        foreach ($extensions as $extension) {
            $extension->enterScenarioSet($scenarioSet, $concurrency);
        }

        $requests = function () use ($runs) {
            $i = 0;
            $count = $this->runner->getMaxConcurrency();
            foreach ($runs as $key => $run) {
                $client = (++$i) % $count;
                $run->setClientId($client);

                // empty iterator
                if (null === $request = $run->getIterator()->current()) {
                    continue;
                }

                yield $key => $this->runner->send($client, $request, $run->getContext());
            }
        };

        $end = function ($response, $key) use ($runs) {
            $this->runner->end($runs[$key]->getClientId());
        };

        (new EachPromise($requests(), [
            'concurrency' => $concurrency,
            'fulfilled' => $end,
            'rejected' => $end,
        ]))->promise()->wait();

        $results = new Results();
        foreach ($requestGenerators as $key => $generator) {
            $results->addResult($key, $generator->getResult());
        }

        foreach ($extensions as $extension) {
            $extension->leaveScenarioSet($scenarioSet, $results);
        }

        return $results;
    }

    public static function version(): string
    {
        static $v;

        if ($v) {
            return $v;
        }
        // This is variable is used to replace the version
        // by box, see https://github.com/box-project/box/blob/master/doc/configuration.md#replaceable-placeholders
        $version = '@git-version@';
        $testPart1 = '@';

        // let's not write the same string, otherwise it would be replaced !
        if ($testPart1.'git-version@' === $version) {
            $composer = Json::decode(file_get_contents(__DIR__.'/../composer.json'));
            $version = $composer['extra']['branch-alias']['dev-master'];
        }

        $v = $version;

        return $version;
    }
}
