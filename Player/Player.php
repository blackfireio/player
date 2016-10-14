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

use Blackfire\Player\Extension\ExtensionInterface;
use Blackfire\Player\Exception\RuntimeException;
use Blackfire\Player\ExpressionLanguage\Provider as LanguageProvider;
use Blackfire\Player\Extension\BlackfireExtension;
use Blackfire\Player\Extension\FeedbackExtension;
use Blackfire\Player\Extension\FollowExtension;
use Blackfire\Player\Extension\TestsExtension;
use Blackfire\Player\Extension\TracerExtension;
use Blackfire\Player\Extension\WaitExtension;
use Blackfire\Player\Guzzle\StepConverter;
use GuzzleHttp\Promise\EachPromise;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class Player
{
    private $runner;
    private $language;
    private $extensions = [];

    public function __construct(RunnerInterface $runner, $tracer = false)
    {
        $this->runner = $runner;
        $this->addExtension(new FeedbackExtension($this->getLanguage()));
        if ($tracer) {
            $this->addExtension(new TracerExtension(sys_get_temp_dir().'/'.sha1(uniqid(mt_rand(), true))));
        }
        $this->addExtension(new TestsExtension($this->getLanguage()));
        $this->addExtension(new BlackfireExtension($this->getLanguage()));
        $this->addExtension(new WaitExtension($this->getLanguage()));
        $this->addExtension(new FollowExtension($this->getLanguage()));
    }

    public function addExtension(ExtensionInterface $extension)
    {
        $this->extensions[] = $extension;
    }

    /**
     * @return Results
     */
    public function run(ScenarioSet $scenarioSet, $concurrency = null)
    {
        $runs = [];
        $requestGenerators = [];
        $i = 0;
        foreach ($scenarioSet as $scenario) {
            $key = null !== $scenario->getKey() ? $scenario->getKey() : ++$i;

            $context = new Context($scenario->getName());
            $stepConverter = new StepConverter($this->getLanguage(), $context);
            $requestGenerator = new Psr7\RequestGenerator($this->getLanguage(), $stepConverter, $scenario, $context);
            $requestGenerator = new Psr7\ExtensibleRequestGenerator($requestGenerator->getIterator(), $scenario, $context, $this->extensions);
            $requestIterator = $requestGenerator->getIterator();
            $context->setGenerator($requestIterator);

            $requestGenerators[$key] = $requestGenerator;
            $runs[$key] = new Run($requestIterator, $scenario, $context);
        }

        if (!$concurrency) {
            $concurrency = min(count($runs), $this->runner->getMaxConcurrency());
        } elseif ($concurrency > $this->runner->getMaxConcurrency()) {
            throw new RuntimeException('Concurrency (%d) must be less than or equal to the number of clients (%s)', $concurrency, $this->runner->getMaxConcurrency());
        }

        foreach ($this->extensions as $extension) {
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

        foreach ($this->extensions as $extension) {
            $extension->leaveScenarioSet($scenarioSet, $results);
        }

        return $results;
    }

    private function getLanguage()
    {
        if (null === $this->language) {
            $this->language = new ExpressionLanguage(null, [new LanguageProvider()]);
        }

        return $this->language;
    }
}
