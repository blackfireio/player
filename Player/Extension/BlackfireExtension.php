<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Extension;

use Blackfire\Bridge\Guzzle\Middleware as BlackfireMiddleware;
use Blackfire\Build;
use Blackfire\Client as BlackfireClient;
use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\Scenario;
use Blackfire\Player\Step;
use Blackfire\Player\ValueBag;
use Blackfire\Profile\Configuration as ProfileConfiguration;
use GuzzleHttp\HandlerStack;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class BlackfireExtension implements ExtensionInterface
{
    private $blackfire;
    private $logger;

    public function __construct(BlackfireClient $blackfire, LoggerInterface $logger = null)
    {
        $this->blackfire = $blackfire;
        $this->logger = $logger;
    }

    public function registerHandlers(HandlerStack $stack)
    {
        $stack->push(BlackfireMiddleware::create($this->blackfire), 'blackfire');
    }

    public function preRun(Scenario $scenario, ValueBag $values, ValueBag $extra)
    {
        $extra->set('blackfire_build', $this->registerBlackfire($scenario->getTitle()));
    }

    public function prepareRequest(Step $step, $options)
    {
        unset($options['blackfire']);
        if ($step->isBlackfireEnabled()) {
            $build = $options['extra']->has('blackfire_build') ? $options['extra']->get('blackfire_build') : null;
            $options['blackfire'] = $this->createBlackfireConfig($options['step'], $build);
        }

        return $options;
    }

    public function processResponse(RequestInterface $request, ResponseInterface $response, Step $step, ValueBag $values = null, Crawler $crawler = null)
    {
        if (!$uuid = $response->getHeaderLine('X-Blackfire-Profile-Uuid')) {
            return;
        }

        if (null !== $crawler && !$step->getTitle()) {
            if (count($c = $crawler->filter('title'))) {
                $this->blackfire->updateProfile($uuid, $c->first()->text());
            }
        }

        $this->assertProfile($request, $response);
    }

    public function postRun(Scenario $scenario, ValueBag $values, ValueBag $extra)
    {
        if ($extra->has('blackfire_build')) {
            $build = $extra->get('blackfire_build');

            // did we profiled anything?
            if (!$build->getJobCount()) {
                // don't finish the build as it won't work with 0 profiles
                $this->logger->error(sprintf('Report "%s" aborted as it has no profiles', $scenario->getTitle()));

                $extra->remove('blackfire_build');

                return;
            }

            $extra->set('blackfire_report', $report = $this->blackfire->endBuild($build));
            $extra->remove('blackfire_build');
        }

        // avoid getting the report if not needed
        if (!$this->logger) {
            return;
        }

        try {
            if ($report->isErrored()) {
                $this->logger->critical(sprintf('Report "%s" errored', $scenario->getTitle()));
            } else {
                if ($report->isSuccessful()) {
                    $this->logger->debug(sprintf('Report "%s" pass', $scenario->getTitle()));
                } else {
                    $this->logger->error(sprintf('Report "%s" failed', $scenario->getTitle()));
                }
            }
        } catch (BlackfireException $e) {
            $this->logger->critical(sprintf('Report "%s" is not available (%s)', $scenario->getTitle(), $e->getMessage()));
        }

        $this->logger->info(sprintf('Report "%s" URL: %s', $scenario->getTitle(), $report->getUrl()));
    }

    private function registerBlackfire($title)
    {
        if (!$env = $this->blackfire->getConfiguration()->getEnv()) {
            throw new LogicException('You must set the environment you want to work with on the Blackfire client configuration.');
        }

        return $this->blackfire->createBuild($env, [
            'title' => $title,
            'trigger_name' => 'Blackfire Player',
        ]);
    }

    private function createBlackfireConfig(Step $step, Build $build = null)
    {
        $config = new ProfileConfiguration();
        if (null !== $build) {
            $config->setBuild($build);
        }
        $config->setSamples($step->getSamples());
        $config->setTitle($step->getTitle());
        foreach ($step->getAssertions() as $assertion) {
            $config->assert($assertion);
        }

        return $config;
    }

    private function assertProfile(RequestInterface $request, ResponseInterface $response)
    {
        if (!$this->logger) {
            return;
        }

        try {
            $profile = $this->blackfire->getProfile($response->getHeaderLine('X-Blackfire-Profile-Uuid'));

            if ($profile->isErrored()) {
                $this->logger->critical('Assertions errored', ['request' => $request->getHeaderLine('X-Request-Id')]);
            } else {
                if ($profile->isSuccessful()) {
                    $this->logger->debug('Assertions pass', ['request' => $request->getHeaderLine('X-Request-Id')]);
                } else {
                    foreach ($profile->getTests() as $test) {
                        foreach ($test->getFailures() as $failure) {
                            $this->logger->error(sprintf('Assertion "%s" failed', $failure), ['request' => $request->getHeaderLine('X-Request-Id')]);
                        }
                    }

                    $this->logger->debug('Assertions fail', ['request' => $request->getHeaderLine('X-Request-Id')]);
                }
            }
        } catch (BlackfireException $e) {
            $this->logger->critical(sprintf('Profile is not available (%s)', $e->getMessage()), ['request' => $request->getHeaderLine('X-Request-Id')]);
        }
    }
}
