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

use Blackfire\Player\Adapter\BlackfireSdkAdapterInterface;
use Blackfire\Player\Build\Build;
use Blackfire\Player\BuildApi;
use Blackfire\Player\Exception\ExpectationErrorException;
use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\Exception\RuntimeException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\Http\CrawlerFactory;
use Blackfire\Player\Http\Request;
use Blackfire\Player\Json;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\ScenarioSetResult;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\ConfigurableStep;
use Blackfire\Player\Step\ReloadStep;
use Blackfire\Player\Step\RequestStep;
use Blackfire\Player\Step\Step;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\StepInitiatorInterface;
use Blackfire\Profile;
use Blackfire\Profile\Configuration as ProfileConfiguration;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final readonly class BlackfireExtension implements NextStepExtensionInterface, StepExtensionInterface, ScenarioSetExtensionInterface
{
    public const int MAX_RETRY = 10;

    public const string HEADER_BLACKFIRE_QUERY = 'x-blackfire-query';
    public const string HEADER_BLACKFIRE_PROFILE_UUID = 'x-blackfire-profile-uuid';
    public const string HEADER_BLACKFIRE_RESPONSE = 'x-blackfire-response';

    public function __construct(
        private ExpressionLanguage $language,
        private BlackfireEnvResolver $blackfireEnvResolver,
        private BuildApi $buildApi,
        private BlackfireSdkAdapterInterface $blackfire,
    ) {
    }

    public function getPreviousSteps(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): iterable
    {
        if (!$step instanceof RequestStep) {
            return;
        }

        $request = $step->getRequest();

        if (false === $this->blackfireEnvResolver->resolve($stepContext, $scenarioContext, $step)) {
            return;
        }

        if (isset($request->headers[self::HEADER_BLACKFIRE_QUERY])) {
            return;
        }

        // we're gonna compute a number of warmup steps (basically ReloadSteps taking the same params as the currently processed step)
        $count = $this->warmupCount($stepContext, $request->method, $scenarioContext->getVariableValues($stepContext, true));
        // Those warmup steps are going to be processed _before_ the current step.
        // The current step processing will take place after the last warmup step is over.
        if ($count > 0) {
            yield from $this->computeWarmupSteps($step->getInitiator(), $scenarioContext, $count, $request);
        }
    }

    public function getNextSteps(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): iterable
    {
        if (!$scenarioContext->hasPreviousResponse()) {
            return;
        }
        $response = $scenarioContext->getLastResponse();
        if (empty($response->request->headers[self::HEADER_BLACKFIRE_PROFILE_UUID])) {
            return;
        }

        if (empty($response->headers[self::HEADER_BLACKFIRE_RESPONSE])) {
            return;
        }

        $blackfireResponseHeader = $response->headers[self::HEADER_BLACKFIRE_RESPONSE][0];
        if (!$this->shouldContinueSampling($blackfireResponseHeader, $scenarioContext, false)) {
            return;
        }

        $reload = new ReloadStep(initiator: $step instanceof StepInitiatorInterface ? $step->getInitiator() : ($step instanceof Step ? $step : null));
        $reload->name("'Reloading for Blackfire'");

        if ($step instanceof ConfigurableStep) {
            $reload->blackfire($step->getBlackfire());
        }

        yield $reload;
    }

    public function beforeStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
        if ($step instanceof Scenario) {
            $env = $this->blackfireEnvResolver->resolve($stepContext, $scenarioContext, $step);
            if (false === $env) {
                return;
            }

            // now let's find the build for that env. If it doesn't exists, create it
            $build = $this->buildApi->getOrCreate($env, $scenarioContext->getScenarioSet());
            $scenarioContext->setExtraValue('build', $build);

            // now assign the build UUID to the step
            $step->setBlackfireBuildUuid($build->uuid);

            return;
        }

        if ($step instanceof RequestStep) {
            $request = $step->getRequest();

            $env = $this->blackfireEnvResolver->resolve($stepContext, $scenarioContext, $step);
            if (false === $env) {
                unset($request->headers[self::HEADER_BLACKFIRE_QUERY]);

                return;
            }

            $this->blackfire->getConfiguration()->setEnv($env);

            if (isset($request->headers[self::HEADER_BLACKFIRE_QUERY])) {
                return;
            }

            $config = $this->createBuildProfileConfig($step->getInitiator(), $stepContext, $scenarioContext, $request);
            $profileRequest = $this->blackfire->createRequest($config);

            $blackfireNoCacheCookie = \sprintf('__blackfire=NO_CACHE%f', mt_rand() / mt_getrandmax());

            if (isset($request->headers['cookie'])) {
                $request->headers['cookie'] = [\sprintf('%s; %s', $request->headers['cookie'][0], $blackfireNoCacheCookie)];
            } else {
                $request->headers['cookie'] = [$blackfireNoCacheCookie];
            }

            $query = $profileRequest->getToken();

            // Send raw (without profiling) performance information
            $stats = $scenarioContext->getExtraValue('blackfire_ref_stats');
            if ($stats && \is_array($stats)) {
                $options = [
                    'profile_title' => Json::encode([
                        'blackfire-metadata' => [
                            'timers' => $stats,
                        ],
                    ]),
                ];
                $query .= '&'.http_build_query($options, '', '&', \PHP_QUERY_RFC3986);

                $scenarioContext->removeExtraValue('blackfire_ref_step');
                $scenarioContext->removeExtraValue('blackfire_ref_stats');
            }

            $request->headers[self::HEADER_BLACKFIRE_QUERY] = [$query];
            $request->headers[self::HEADER_BLACKFIRE_PROFILE_UUID] = [$profileRequest->getUuid()];
        }
    }

    public function afterStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
        if ($step instanceof RequestStep) {
            $response = $scenarioContext->getLastResponse();

            if ($step === $scenarioContext->getExtraValue('blackfire_ref_step')) {
                $scenarioContext->setExtraValue('blackfire_ref_stats', $response->stats);
            }

            if (empty($response->request->headers[self::HEADER_BLACKFIRE_PROFILE_UUID])) {
                return;
            }

            $uuid = $response->request->headers[self::HEADER_BLACKFIRE_PROFILE_UUID][0];
            if (empty($response->headers[self::HEADER_BLACKFIRE_RESPONSE])) {
                $probeNotFoundOrInvalidSignatureErrorMessage = 'Are you authorized to profile this page? Probe not found or invalid signature. Please read https://support.blackfire.platform.sh/hc/en-us/articles/4843027173778-Are-You-Authorized-to-Profile-this-Page-Probe-Not-Found-or-Invalid-signature-';
                $step->addError($probeNotFoundOrInvalidSignatureErrorMessage);

                throw new LogicException($probeNotFoundOrInvalidSignatureErrorMessage);
            }

            // Check if the profile needs more samples
            $blackfireResponseHeader = $response->headers[self::HEADER_BLACKFIRE_RESPONSE][0];
            if ($this->shouldContinueSampling($blackfireResponseHeader, $scenarioContext)) {
                return;
            }

            // Request is over. Read the profile
            if (null === $step->getInitiator()->getName() && null !== ($crawler = CrawlerFactory::create($response, $response->request->uri)) && \count($c = $crawler->filter('title'))) {
                $this->blackfire->updateProfile($uuid, $c->first()->text());
            }

            $parentStep = $step->getInitiator();
            $profile = $this->blackfire->getProfile($uuid);
            // Initialize profile data by calling getUrl()
            $profile->getUrl();

            $parentStep->setBlackfireProfileUuid($uuid);
            $this->assertProfile($parentStep, $profile);
        }
    }

    private function createBuildProfileConfig(Step $step, StepContext $stepContext, ScenarioContext $context, Request $request): ProfileConfiguration
    {
        $config = new ProfileConfiguration();

        $path = parse_url($request->uri, \PHP_URL_PATH) ?: '/';
        $config->setTitle($this->language->evaluate($step->getName() ?? Json::encode(\sprintf('%s resource', $path)), $context->getVariableValues($stepContext, true)));

        $query = parse_url($request->uri, \PHP_URL_QUERY);
        if ($query) {
            $path .= '?'.$query;
        }

        $env = $this->blackfireEnvResolver->resolve($stepContext, $context, $step);
        $build = $this->findEnvBuildFromExtraBag($env, $context->getScenarioSet());
        if (null === $build) {
            throw new RuntimeException(\sprintf('Could not find build for env %s in the ScenarioSet', $env));
        }

        $config->setIntention('build');
        $config->setBuildUuid($build->uuid);

        $config->setRequestInfo([
            'method' => $request->method,
            'path' => $path,
            'headers' => $step->getHeaders(),
        ]);

        foreach ($step->getAssertions() as $assertion) {
            $config->assert($assertion);
        }

        return $config;
    }

    private function assertProfile(Step $step, Profile $profile): void
    {
        if ($profile->isErrored()) {
            if ($profile->getTests()) {
                throw new ExpectationErrorException('At least one assertion is invalid.');
            }
            throw new ExpectationErrorException('None of your assertions apply to this scenario.');
        }
        if (!$profile->isSuccessful()) {
            $hasFailingAssertion = false;
            foreach ($profile->getTests() as $test) {
                foreach ($test->getFailures() as $failure) {
                    $hasFailingAssertion = true;
                    $step->addFailingAssertion(\sprintf('Assertion failed: %s', $failure));
                }
            }
            if (!$hasFailingAssertion) { // It is a recommendation report
                foreach ($profile->getRecommendations() as $test) {
                    foreach ($test->getFailures() as $failure) {
                        $step->addFailingAssertion(\sprintf('Assertion failed: %s', $failure));
                    }
                }
            }
        }
    }

    private function shouldContinueSampling(string $blackfireResponseHeader, ScenarioContext $scenarioContext, bool $checkProgress = true): bool
    {
        parse_str($blackfireResponseHeader, $values);

        $continue = isset($values['continue']) && 'true' === $values['continue'];

        if (!$continue) {
            $scenarioContext->setExtraValue('blackfire_progress', -1);
            $scenarioContext->setExtraValue('blackfire_retry', 0);
        } elseif (isset($values['progress']) && $checkProgress) {
            $prevProgress = $scenarioContext->getExtraValue('blackfire_progress', -1);
            $progress = (int) $values['progress'];

            if ($progress < $prevProgress) {
                throw new LogicException("Profiling progress is inconsistent (progress is going backward). That happens for instance when the project's infrastructure is behind a load balancer. Please read https://docs.blackfire.io/up-and-running/reverse-proxies#configuration-load-balancer");
            }
            if ($progress === $prevProgress) {
                $retry = $scenarioContext->getExtraValue('blackfire_retry', 0);
                ++$retry;
                if ($retry >= self::MAX_RETRY) {
                    throw new LogicException('Profiling progress is inconsistent (progress is not increasing). That happens for instance when using a reverse proxy or an HTTP cache server such as Varnish. Please read https://docs.blackfire.io/up-and-running/reverse-proxies#reverse-proxies-and-cdns');
                }
                $scenarioContext->setExtraValue('blackfire_retry', $retry);
            } else {
                $scenarioContext->setExtraValue('blackfire_retry', 0);
            }

            $scenarioContext->setExtraValue('blackfire_progress', $progress);
        }

        $wait = isset($values['wait']) ? (int) $values['wait'] : 0;
        if (0 !== $wait) {
            usleep(min($wait, 10000) * 1000);
        }

        return $continue;
    }

    private function warmupCount(StepContext $stepContext, string $requestMethod, array $contextVariables): int
    {
        $value = $this->language->evaluate($stepContext->getWarmup(), $contextVariables);

        if (false === $value) {
            return 0;
        }

        if (\in_array($requestMethod, ['GET', 'HEAD'], true)) {
            return true === $value ? 3 : (int) $value;
        }

        return 0;
    }

    /**
     * Yields warmupCount ReloadSteps + 1 which will be used as perf reference.
     */
    private function computeWarmupSteps(Step $step, ScenarioContext $scenarioContext, int $warmupCount, Request $request): iterable
    {
        for ($i = 0; $i < $warmupCount; ++$i) {
            $warmupStep = new RequestStep($request, $step);
            $warmupStep->warmup('false');

            // Warmup requests
            $warmupStep
                ->name(Json::encode(\sprintf('[Warmup] %s', trim($step->getName() ?? 'anonymous', '"'))))
                ->blackfire('false')
            ;

            yield $warmupStep;
        }

        // raw perf request
        $referencePerfStep = (new RequestStep($request, $step))
            ->warmup('false')
            ->name(Json::encode(\sprintf('[Reference] %s', trim($step->getName() ?? 'anonymous', '"'))))
            ->blackfire('false')
        ;

        $scenarioContext->setExtraValue('blackfire_ref_step', $referencePerfStep);

        yield $referencePerfStep;
    }

    public function beforeScenarioSet(ScenarioSet $scenarios, int $concurrency): void
    {
        if (!\is_string($scenarios->getName())) {
            return;
        }

        $scenarios->getExtraBag()->set('blackfire_build_name', trim($scenarios->getName(), '"'));
    }

    public function afterScenarioSet(ScenarioSet $scenarios, int $concurrency, ScenarioSetResult $scenarioSetResult): void
    {
        $bag = $scenarios->getExtraBag();

        if (!$this->blackfire->getConfiguration()->getEnv()) {
            return;
        }

        /** @var Build[] $builds */
        $builds = array_filter(
            $bag->all(),
            static fn (int|string $key): bool => \is_string($key) && str_starts_with($key, 'blackfire_build:'),
            \ARRAY_FILTER_USE_KEY
        );

        foreach ($builds as $key => $build) {
            $bag->remove($key);
        }
    }

    private function findEnvBuildFromExtraBag(string $env, ScenarioSet $scenarios): Build|null
    {
        $bag = $scenarios->getExtraBag();

        $buildKey = 'blackfire_build:'.$env;

        return $bag->has($buildKey) ? $bag->get($buildKey) : null;
    }
}
