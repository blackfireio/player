<?php

declare(strict_types=1);

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Extension;

use Blackfire\Build\Build as SdkBuild;
use Blackfire\Client;
use Blackfire\ClientConfiguration;
use Blackfire\Player\Adapter\BlackfireSdkAdapter;
use Blackfire\Player\Build\Build;
use Blackfire\Player\BuildApi;
use Blackfire\Player\Enum\BuildStatus;
use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ExpressionLanguage\Provider;
use Blackfire\Player\Extension\BlackfireEnvResolver;
use Blackfire\Player\Extension\BlackfireExtension;
use Blackfire\Player\Http\Request as HttpRequest;
use Blackfire\Player\Http\Response as HttpResponse;
use Blackfire\Player\Json;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\ReloadStep;
use Blackfire\Player\Step\RequestStep;
use Blackfire\Player\Step\Step;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\VisitStep;
use Blackfire\Profile;
use Blackfire\Profile\Request as ProfileRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

class BlackfireExtensionTest extends TestCase
{
    public function testBeforeStepAppendsBuildUuidToScenario(): void
    {
        $step = new Scenario('scenario');
        $step->blackfire('"My Env"');

        $extension = $this->getBlackfireExtension();

        $stepContext = new StepContext();
        $stepContext->update($step, []);

        $scenarioSet = new ScenarioSet();
        $scenarioSet->getExtraBag()->set('blackfire_build_name', 'the build name');

        $scenarioContext = new ScenarioContext('"foo"', $scenarioSet);

        $extension->beforeStep($step, $stepContext, $scenarioContext);

        $this->assertSame('4444-3333-2222-1111', $step->getBlackfireBuildUuid());
    }

    #[DataProvider('beforeRequestProvider')]
    public function testBeforeRequest(Step $step, HttpRequest $request, array $defaultScenarioSetExtraValues, HttpRequest $expectedRequest, array|null $expectedCookies, callable $stepAssertions, callable|null $scenarioContextAssertions = null): void
    {
        $extension = $this->getBlackfireExtension();

        $stepContext = new StepContext();
        $stepContext->update($step, []);

        $scenarioSet = new ScenarioSet();
        $scenarioSet->getExtraBag()->set('blackfire_build_name', 'the build name');
        $build = new Build('21f93c3e-267c-47c9-a85b-df0c2f1d0b4f');
        $scenarioSet->getExtraBag()->set('blackfire_build:my env', $build);

        $scenarioContext = new ScenarioContext('"foo"', $scenarioSet);

        foreach ($defaultScenarioSetExtraValues as $k => $v) {
            $scenarioContext->setExtraValue($k, $v);
        }

        $extension->beforeStep(new RequestStep($request, $step), $stepContext, $scenarioContext);

        if (null !== $expectedCookies) {
            $this->assertArrayHasKey('cookie', $request->headers);
            $cookies = explode('; ', $request->headers['cookie'][0]);
            // ensure there's a __blackfire=NO_CACHE cookie
            // we'll compare the expected cookies against all the request cookies except this one (as it contains random values)
            $normalizedCookies = [];
            $noCacheCookieFound = false;
            foreach ($cookies as $cookie) {
                if (str_starts_with($cookie, '__blackfire=NO_CACHE')) {
                    $noCacheCookieFound = true;
                } else {
                    $normalizedCookies[] = $cookie;
                }
            }

            $this->assertTrue($noCacheCookieFound);
            $this->assertEquals($expectedCookies, $normalizedCookies);
            unset($request->headers['cookie']);
        }

        $this->assertEquals($expectedRequest, $request);

        $stepAssertions($step);

        if (null !== $scenarioContextAssertions) {
            $scenarioContextAssertions($scenarioContext);
        }
    }

    public static function beforeRequestProvider(): \Generator
    {
        $expectedRequest = new HttpRequest('GET', 'https://app-under-test.lan');
        $defaultScenarioSetExtraValues = [];
        yield 'no env drops the X-Blackfire-Query header' => [
            new VisitStep('https://app-under-test.lan'),
            new HttpRequest('GET', 'https://app-under-test.lan'),
            $defaultScenarioSetExtraValues,
            $expectedRequest,
            null,
            function (Step $step): void {
                self::assertNull($step->getBlackfireProfileUuid());
            },
        ];

        $step = new VisitStep('https://app-under-test.lan');
        $step->blackfire('"my env"');
        $expectedRequest = new HttpRequest('GET', 'https://app-under-test.lan', [
            BlackfireExtension::HEADER_BLACKFIRE_QUERY => ['1234'],
            BlackfireExtension::HEADER_BLACKFIRE_PROFILE_UUID => ['1111-2222-3333-4444'],
        ]);
        $defaultScenarioSetExtraValues = [];
        yield 'append the X-Blackfire-Query header when needed' => [
            $step,
            new HttpRequest('GET', 'https://app-under-test.lan'),
            $defaultScenarioSetExtraValues,
            $expectedRequest,
            [],
            function (Step $step): void {
                self::assertNull($step->getBlackfireProfileUuid());
            },
        ];

        $step = new VisitStep('https://app-under-test.lan');
        $step->blackfire('"my env"');
        $expectedRequest = new HttpRequest('GET', 'https://app-under-test.lan', [
            BlackfireExtension::HEADER_BLACKFIRE_QUERY => ['1234'],
            BlackfireExtension::HEADER_BLACKFIRE_PROFILE_UUID => ['1111-2222-3333-4444'],
        ]);
        $defaultScenarioSetExtraValues = [];
        yield 'updates the Cookie header when it already exists' => [
            $step,
            new HttpRequest('GET', 'https://app-under-test.lan', ['cookie' => ['my=cookie']]),
            $defaultScenarioSetExtraValues,
            $expectedRequest,
            ['my=cookie'],
            function (Step $step): void {
                self::assertNull($step->getBlackfireProfileUuid());
            },
        ];

        $step = new VisitStep('https://app-under-test.lan');
        $step->blackfire('"my env"');
        $expectedRequest = new HttpRequest('GET', 'https://app-under-test.lan', [
            BlackfireExtension::HEADER_BLACKFIRE_QUERY => ['1234&profile_title=%7B%22blackfire-metadata%22%3A%7B%22timers%22%3A%7B%22total%22%3A2%2C%22name_lookup%22%3A2%2C%22connect%22%3A2%2C%22pre_transfer%22%3A2%2C%22start_transfer%22%3A2%2C%22post_transfer%22%3A2%7D%7D%7D'],
            BlackfireExtension::HEADER_BLACKFIRE_PROFILE_UUID => ['1111-2222-3333-4444'],
        ]);
        $defaultScenarioSetExtraValues = [
            'blackfire_ref_step' => new VisitStep('https://app-under-test.lan'),
            'blackfire_ref_stats' => [
                'total' => 2,
                'name_lookup' => 2,
                'connect' => 2,
                'pre_transfer' => 2,
                'start_transfer' => 2,
                'post_transfer' => 2,
            ],
        ];
        yield 'configures X-Blackfire-Query with ref stats when they exists' => [
            $step,
            new HttpRequest('GET', 'https://app-under-test.lan', ['cookie' => ['my=cookie']]),
            $defaultScenarioSetExtraValues,
            $expectedRequest,
            ['my=cookie'],
            function (Step $step): void {
                self::assertNull($step->getBlackfireProfileUuid());
            },
            function (ScenarioContext $scenarioContext): void {
                self::assertNull($scenarioContext->getExtraValue('blackfire_ref_step'));
                self::assertNull($scenarioContext->getExtraValue('blackfire_ref_stats'));
            },
        ];
    }

    #[DataProvider('beforeRequestFailureProvider')]
    public function testBeforeRequestFailure(Step $step, HttpRequest $request, array $defaultScenarioSetExtraValues, string $exceptionClass, string $exceptionMessage): void
    {
        $extension = $this->getBlackfireExtension(null);

        $stepContext = new StepContext();
        $stepContext->update($step, []);

        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());

        foreach ($defaultScenarioSetExtraValues as $k => $v) {
            $scenarioContext->setExtraValue($k, $v);
        }

        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($exceptionMessage);

        $extension->beforeStep(new RequestStep($request, $step), $stepContext, $scenarioContext);
    }

    public static function beforeRequestFailureProvider(): \Generator
    {
        $defaultScenarioSetExtraValues = [];
        $step = new VisitStep('https://app-under-test.lan');
        $step->blackfire('true');
        yield 'throws when blackfire env is true but not defined at the ScenarioContext level' => [
            $step,
            new HttpRequest('GET', 'https://app-under-test.lan'),
            $defaultScenarioSetExtraValues,
            \LogicException::class,
            '--blackfire-env option must be set when using "blackfire: true" in a scenario.',
        ];
    }

    #[DataProvider('afterResponseFailureProvider')]
    public function testAfterResponseFailure(HttpResponse $response, array $defaultScenarioSetExtraValues, string $exceptionClass, string $exceptionMessage): void
    {
        $extension = $this->getBlackfireExtension();

        $step = new VisitStep('https://app-under-test.lan');
        $stepContext = new StepContext();
        $stepContext->update($step, []);
        $step->setStatus(BuildStatus::IN_PROGRESS);

        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $scenarioContext->setLastResponse($response);

        foreach ($defaultScenarioSetExtraValues as $k => $v) {
            $scenarioContext->setExtraValue($k, $v);
        }

        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($exceptionMessage);

        $extension->afterStep(new RequestStep(new HttpRequest('GET', '/'), $step), $stepContext, $scenarioContext);
    }

    #[DataProvider('afterResponseProvider')]
    public function testAfterResponse(HttpResponse $response, array $defaultScenarioSetExtraValues, callable $scenarioContextAssertions): void
    {
        $extension = $this->getBlackfireExtension();

        $step = new VisitStep('https://app-under-test.lan');
        $stepContext = new StepContext();
        $stepContext->update($step, []);
        $step->setStatus(BuildStatus::IN_PROGRESS);

        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $scenarioContext->setLastResponse($response);

        foreach ($defaultScenarioSetExtraValues as $k => $v) {
            $scenarioContext->setExtraValue($k, $v);
        }

        $extension->afterStep(new RequestStep(new HttpRequest('GET', '/'), $step), $stepContext, $scenarioContext);

        $scenarioContextAssertions($scenarioContext, $step);
    }

    public static function afterResponseProvider(): \Generator
    {
        $stats = [
            'total_time' => 0.04079,
            'namelookup_time' => 2.2E-5,
            'connect_time' => 2.2E-5,
            'pretransfer_time' => 6.5E-5,
            'starttransfer_time' => 0.040079,
        ];

        $request = new HttpRequest('GET', 'https://app-under-test.lan');
        $response = new HttpResponse($request, 200, [], 'My HTML response', $stats);

        $defaultScenarioSetExtraValues = [];

        yield 'do nothing without X-Blackfire-Profile-Uuid response header' => [
            $response,
            $defaultScenarioSetExtraValues,
            function (ScenarioContext $scenarioContext, Step $step): void {
                self::assertNull($step->getBlackfireProfileUuid());
                self::assertNull($scenarioContext->getExtraValue('blackfire_retry'));
                self::assertNull($scenarioContext->getExtraValue('blackfire_progress'));
            },
        ];

        $request = new HttpRequest('GET', 'https://app-under-test.lan', [
            BlackfireExtension::HEADER_BLACKFIRE_PROFILE_UUID => ['d2a963a1-3c1d-44b9-9e86-553f1e30c279'],
        ]);
        $response = new HttpResponse($request, 200, [
            BlackfireExtension::HEADER_BLACKFIRE_RESPONSE => ['continue=true&progress=30'],
        ], 'My HTML response', $stats);

        $defaultScenarioSetExtraValues = [];

        yield 'updates ScenarioContext if sampling should continue' => [
            $response,
            $defaultScenarioSetExtraValues,
            function (ScenarioContext $scenarioContext, Step $step): void {
                self::assertNull($step->getBlackfireProfileUuid());
                self::assertEquals(0, $scenarioContext->getExtraValue('blackfire_retry'));
                self::assertEquals(30, $scenarioContext->getExtraValue('blackfire_progress'));
            },
        ];

        $request = new HttpRequest('GET', 'https://app-under-test.lan', [
            BlackfireExtension::HEADER_BLACKFIRE_PROFILE_UUID => ['d2a963a1-3c1d-44b9-9e86-553f1e30c279'],
        ]);
        $response = new HttpResponse($request, 200, [
            BlackfireExtension::HEADER_BLACKFIRE_RESPONSE => ['continue=true&progress=30'],
        ], 'My HTML response', $stats);

        $defaultScenarioSetExtraValues = [];

        yield 'updates ScenarioContext current progress if response headers contains a progress value' => [
            $response,
            $defaultScenarioSetExtraValues,
            function (ScenarioContext $scenarioContext, Step $step): void {
                self::assertNull($step->getBlackfireProfileUuid());
                self::assertEquals(0, $scenarioContext->getExtraValue('blackfire_retry'));
                self::assertEquals(30, $scenarioContext->getExtraValue('blackfire_progress'));
            },
        ];

        $request = new HttpRequest('GET', 'https://app-under-test.lan', [
            BlackfireExtension::HEADER_BLACKFIRE_PROFILE_UUID => ['d2a963a1-3c1d-44b9-9e86-553f1e30c279'],
        ]);
        $response = new HttpResponse($request, 200, [
            BlackfireExtension::HEADER_BLACKFIRE_RESPONSE => [''],
        ], 'My HTML response', $stats);

        $defaultScenarioSetExtraValues = [];

        yield 'updates ScenarioContext current progress if response headers continue value isnt set' => [
            $response,
            $defaultScenarioSetExtraValues,
            function (ScenarioContext $scenarioContext, Step $step): void {
                self::assertSame('d2a963a1-3c1d-44b9-9e86-553f1e30c279', $step->getBlackfireProfileUuid());
                self::assertEquals(0, $scenarioContext->getExtraValue('blackfire_retry'));
                self::assertEquals(-1, $scenarioContext->getExtraValue('blackfire_progress'));
            },
        ];

        $request = new HttpRequest('GET', 'https://app-under-test.lan', [
            BlackfireExtension::HEADER_BLACKFIRE_PROFILE_UUID => ['d2a963a1-3c1d-44b9-9e86-553f1e30c279'],
        ]);
        $response = new HttpResponse($request, 200, [
            BlackfireExtension::HEADER_BLACKFIRE_RESPONSE => ['continue=false'],
        ], 'My HTML response', $stats);

        $defaultScenarioSetExtraValues = [];

        yield 'updates ScenarioContext current progress if response headers continue value equals false' => [
            $response,
            $defaultScenarioSetExtraValues,
            function (ScenarioContext $scenarioContext, Step $step): void {
                self::assertSame('d2a963a1-3c1d-44b9-9e86-553f1e30c279', $step->getBlackfireProfileUuid());
                self::assertEquals(0, $scenarioContext->getExtraValue('blackfire_retry'));
                self::assertEquals(-1, $scenarioContext->getExtraValue('blackfire_progress'));
            },
        ];

        $request = new HttpRequest('GET', 'https://app-under-test.lan', [
            BlackfireExtension::HEADER_BLACKFIRE_PROFILE_UUID => ['d2a963a1-3c1d-44b9-9e86-553f1e30c279'],
        ]);
        $response = new HttpResponse($request, 200, [
            BlackfireExtension::HEADER_BLACKFIRE_RESPONSE => ['continue=true&progress=40'],
        ], 'My HTML response', $stats);

        $defaultScenarioSetExtraValues = [
            'blackfire_progress' => 40,
        ];

        yield 'updates ScenarioContext current retry if no progress were made since previous request' => [
            $response,
            $defaultScenarioSetExtraValues,
            function (ScenarioContext $scenarioContext, Step $step): void {
                self::assertNull($step->getBlackfireProfileUuid());
                self::assertEquals(1, $scenarioContext->getExtraValue('blackfire_retry'));
                self::assertEquals(40, $scenarioContext->getExtraValue('blackfire_progress'));
            },
        ];
    }

    public static function afterResponseFailureProvider(): \Generator
    {
        $stats = [
            'total_time' => 0.04079,
            'namelookup_time' => 2.2E-5,
            'connect_time' => 2.2E-5,
            'pretransfer_time' => 6.5E-5,
            'starttransfer_time' => 0.040079,
        ];

        $request = new HttpRequest('GET', 'https://app-under-test.lan', [
            BlackfireExtension::HEADER_BLACKFIRE_PROFILE_UUID => ['d2a963a1-3c1d-44b9-9e86-553f1e30c279'],
        ]);
        $response = new HttpResponse($request, 200, [
            BlackfireExtension::HEADER_BLACKFIRE_RESPONSE => ['continue=true&progress=40'],
        ], 'My HTML response', $stats);

        $defaultScenarioSetExtraValues = [
            'blackfire_progress' => 40,
            'blackfire_retry' => 10,
        ];

        yield 'throws when the retry threshold got hit' => [
            $response,
            $defaultScenarioSetExtraValues,
            LogicException::class,
            'Profiling progress is inconsistent (progress is not increasing). That happens for instance when using a reverse proxy or an HTTP cache server such as Varnish. Please read https://docs.blackfire.io/up-and-running/reverse-proxies#reverse-proxies-and-cdns',
        ];

        $request = new HttpRequest('GET', 'https://app-under-test.lan', [
            BlackfireExtension::HEADER_BLACKFIRE_PROFILE_UUID => ['d2a963a1-3c1d-44b9-9e86-553f1e30c279'],
        ]);
        $response = new HttpResponse($request, 200, [
            BlackfireExtension::HEADER_BLACKFIRE_RESPONSE => ['continue=true&progress=80'],
        ], 'My HTML response', $stats);

        $defaultScenarioSetExtraValues = [
            'blackfire_progress' => 90,
            'blackfire_retry' => 0,
        ];

        yield 'throws when the progress decreases' => [
            $response,
            $defaultScenarioSetExtraValues,
            LogicException::class,
            "Profiling progress is inconsistent (progress is going backward). That happens for instance when the project's infrastructure is behind a load balancer. Please read https://docs.blackfire.io/up-and-running/reverse-proxies#configuration-load-balancer",
        ];
    }

    #[DataProvider('getPreviousStepsProvider')]
    public function testGetPreviousStepsGenerateEnoughWarmupAndReferenceSteps(Step $step, array $expectedSteps, HttpRequest $request): void
    {
        $extension = $this->getBlackfireExtension();

        $stepContext = new StepContext();
        $stepContext->update($step, []);

        $prependedSteps = iterator_to_array($extension->getPreviousSteps(new RequestStep($request, $step), $stepContext, new ScenarioContext('"foo"', new ScenarioSet())));

        $normalizedStepsOutput = array_map(static fn (AbstractStep $step): array => ['name' => $step->getName()], $prependedSteps);

        $this->assertEquals($expectedSteps, $normalizedStepsOutput);
    }

    public static function getPreviousStepsProvider(): \Generator
    {
        $visitStepWith5Warmups = new VisitStep('https://app.lan');
        $visitStepWith5Warmups->blackfire('true');
        $visitStepWith5Warmups->name('"Visit page"');
        $visitStepWith5Warmups->method('"GET"');
        $visitStepWith5Warmups->warmup('5');
        yield 'VisitStep with GET request and 5 warmups' => [
            $visitStepWith5Warmups,
            [
                [
                    'name' => '"[Warmup] Visit page"',
                ],
                [
                    'name' => '"[Warmup] Visit page"',
                ],
                [
                    'name' => '"[Warmup] Visit page"',
                ],
                [
                    'name' => '"[Warmup] Visit page"',
                ],
                [
                    'name' => '"[Warmup] Visit page"',
                ],
                [
                    'name' => '"[Reference] Visit page"',
                ],
            ],
            new HttpRequest('GET', 'https://app.lan'),
        ];

        $visitStepDefaultWarmup = new VisitStep('https://app.lan');
        $visitStepDefaultWarmup->blackfire('true');
        $visitStepDefaultWarmup->name('"Visit page"');
        $visitStepDefaultWarmup->method('"GET"');
        yield 'VisitStep with GET request and default warmup' => [
            $visitStepDefaultWarmup,
            [
                [
                    'name' => '"[Warmup] Visit page"',
                ],
                [
                    'name' => '"[Warmup] Visit page"',
                ],
                [
                    'name' => '"[Warmup] Visit page"',
                ],
                [
                    'name' => '"[Reference] Visit page"',
                ],
            ],
            new HttpRequest('GET', 'https://app.lan'),
        ];

        $visitStepHeadMethodWith5Warmups = new VisitStep('https://app.lan');
        $visitStepHeadMethodWith5Warmups->blackfire('true');
        $visitStepHeadMethodWith5Warmups->name('"Visit page"');
        $visitStepHeadMethodWith5Warmups->method('"HEAD"');
        $visitStepHeadMethodWith5Warmups->warmup('5');
        yield 'VisitStep with HEAD request and 5 warmups' => [
            $visitStepHeadMethodWith5Warmups,
            [
                [
                    'name' => '"[Warmup] Visit page"',
                ],
                [
                    'name' => '"[Warmup] Visit page"',
                ],
                [
                    'name' => '"[Warmup] Visit page"',
                ],
                [
                    'name' => '"[Warmup] Visit page"',
                ],
                [
                    'name' => '"[Warmup] Visit page"',
                ],
                [
                    'name' => '"[Reference] Visit page"',
                ],
            ],
            new HttpRequest('HEAD', 'https://app.lan'),
        ];

        $visitStepWith2WarmupsAndPostRequest = new VisitStep('https://app.lan');
        $visitStepWith2WarmupsAndPostRequest->blackfire('true');
        $visitStepWith2WarmupsAndPostRequest->name('"Visit page"');
        $visitStepWith2WarmupsAndPostRequest->method('"POST"');
        $visitStepWith2WarmupsAndPostRequest->warmup('2');
        // no warmup are expected here as it is a POST request without having set sample > 1
        yield 'VisitStep with POST request and 2 warmups' => [
            $visitStepWith2WarmupsAndPostRequest,
            [],
            new HttpRequest('POST', 'https://app.lan'),
        ];

        $visitStepWithoutBlackfire = new VisitStep('https://app.lan');
        $visitStepWithoutBlackfire->blackfire('false');
        $visitStepWithoutBlackfire->name('"Visit page"');
        $visitStepWithoutBlackfire->warmup('2');
        yield 'VisitStep without blackfire' => [
            $visitStepWithoutBlackfire,
            [],
            new HttpRequest('POST', 'https://app.lan'),
        ];
    }

    private function getBlackfireExtension(string|null $defaultEnv = 'My env'): BlackfireExtension
    {
        $blackfireSdkClient = new BlackfireSdkAdapter($this->createBlackfireClient());
        $language = new ExpressionLanguage(null, [new Provider()]);

        return new BlackfireExtension(
            $language,
            new BlackfireEnvResolver($defaultEnv, $language),
            new BuildApi($blackfireSdkClient, new MockHttpClient()),
            $blackfireSdkClient
        );
    }

    #[DataProvider('getNextStepsProvider')]
    public function testGetNextSteps(Step $step, HttpResponse $response, array $expectedYieldedSteps): void
    {
        $extension = $this->getBlackfireExtension();

        $stepContext = new StepContext();
        $stepContext->update($step, []);
        $scenarioContext = new ScenarioContext('"foo"', new ScenarioSet());
        $scenarioContext->setLastResponse($response);

        $stepsOutput = iterator_to_array($extension->getNextSteps($step, $stepContext, $scenarioContext));

        $this->assertCount(\count($expectedYieldedSteps), $stepsOutput);

        // we cast the output into a php array so that we can perform assertions on the output without worrying about the steps UUIDs
        $serializedStepsOutput = Json::decode(Json::encode($stepsOutput));
        $serializedExpectedSteps = Json::decode(Json::encode($expectedYieldedSteps));

        foreach ($serializedStepsOutput as &$serializedOutputStep) {
            unset($serializedOutputStep['uuid']);
        }

        foreach ($serializedExpectedSteps as &$serializedExpectedStep) {
            unset($serializedExpectedStep['uuid']);
        }

        $this->assertJsonStringEqualsJsonString(Json::encode($serializedExpectedSteps), Json::encode($serializedStepsOutput));
    }

    public static function getNextStepsProvider(): \Generator
    {
        $stats = [
            'total_time' => 0.04079,
            'namelookup_time' => 2.2E-5,
            'connect_time' => 2.2E-5,
            'pretransfer_time' => 6.5E-5,
            'starttransfer_time' => 0.040079,
        ];

        $request = new HttpRequest('GET', 'https://app-under-test.lan');
        $response = new HttpResponse($request, 200, [], 'what a beautiful response body', []);
        yield 'yields nothing when no X-Blackfire-Profile-Uuid on request' => [
            new VisitStep('https://app-under-test.lan'),
            $response,
            [],
        ];

        $request = new HttpRequest('GET', 'https://app-under-test.lan', [BlackfireExtension::HEADER_BLACKFIRE_PROFILE_UUID => ['683910c2-9c3c-496d-a5d6-7cee0ee20d38']]);
        $response = new HttpResponse($request, 200, [], 'what a beautiful response body', $stats);
        yield 'yields nothing when no X-Blackfire-Response on response' => [
            new VisitStep('https://app-under-test.lan'),
            $response,
            [],
        ];

        $request = new HttpRequest('GET', 'https://app-under-test.lan', [BlackfireExtension::HEADER_BLACKFIRE_PROFILE_UUID => ['683910c2-9c3c-496d-a5d6-7cee0ee20d38']]);
        $response = new HttpResponse($request, 200, [BlackfireExtension::HEADER_BLACKFIRE_RESPONSE => ['continue=false']], 'what a beautiful response body', $stats);
        yield 'yields nothing when no sample is required' => [
            new VisitStep('https://app-under-test.lan'),
            $response,
            [],
        ];

        $request = new HttpRequest('GET', 'https://app-under-test.lan', [BlackfireExtension::HEADER_BLACKFIRE_PROFILE_UUID => ['683910c2-9c3c-496d-a5d6-7cee0ee20d38']]);
        $response = new HttpResponse($request, 200, [BlackfireExtension::HEADER_BLACKFIRE_RESPONSE => ['continue=true&progress=40']], 'what a beautiful response body', $stats);
        $step = new VisitStep('https://app-under-test.lan');
        $step->blackfire('"foo"');
        yield 'yields a ReloadStep when another sample is required' => [
            $step,
            $response,
            [
                (new ReloadStep())
                    ->name("'Reloading for Blackfire'")
                    ->blackfire('"foo"'),
            ],
        ];
    }

    private function createBlackfireClient(): MockObject
    {
        $blackfireConfig = new ClientConfiguration();

        $profileRequest = $this->createMock(ProfileRequest::class);
        $profileRequest->method('getToken')->willReturn('1234');
        $profileRequest->method('getUuid')->willReturn('1111-2222-3333-4444');

        $profile = $this->createMock(Profile::class);
        $profile->method('isErrored')->willReturn(false);
        $profile->method('isSuccessful')->willReturn(true);

        $build = $this->createMock(SdkBuild::class);
        $build->method('getUuid')->willReturn('4444-3333-2222-1111');

        $blackfire = $this->createMock(Client::class);
        $blackfire->method('getConfiguration')->willReturn($blackfireConfig);
        $blackfire->method('createRequest')->willReturn($profileRequest);
        $blackfire->method('getProfile')->willReturn($profile);
        $blackfire->method('startBuild')->willReturn($build);

        return $blackfire;
    }
}
