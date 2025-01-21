<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Reporter;

use Blackfire\Player\Build\Build;
use Blackfire\Player\BuildApi;
use Blackfire\Player\Enum\BuildStatus;
use Blackfire\Player\Exception\ApiCallException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ExpressionLanguage\Provider as LanguageProvider;
use Blackfire\Player\Parser;
use Blackfire\Player\Reporter\JsonViewReporter;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Serializer\ScenarioSetSerializer;
use Blackfire\Player\Tests\Adapter\StubbedSdkAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class JsonViewReporterTest extends TestCase
{
    /**
     * @dataProvider reportErrorsProvider()
     */
    public function testReportThrowsOnlyWhenFailingToSendLastJsonView(ScenarioSet $scenarioSet, bool $exceptionExpected)
    {
        $scenarioSetSerializer = new ScenarioSetSerializer();

        $httpClient = new MockHttpClient([
            new MockResponse('...', [
                'http_code' => 500,
            ]),
        ]);

        $buildApi = new BuildApi(new StubbedSdkAdapter('Blackfire Test'), $httpClient);
        $reporter = new JsonViewReporter($scenarioSetSerializer, $buildApi, new NullOutput());

        if (true === $exceptionExpected) {
            $this->expectException(ApiCallException::class);
        }

        $this->assertNull($reporter->report($scenarioSet));
    }

    public static function reportErrorsProvider()
    {
        $parser = new Parser(new ExpressionLanguage(null, [new LanguageProvider()]));
        $scenarioSetBase = <<<'EOF'
scenario Test 1
    set env "prod"
    endpoint 'http://toto.com'

    # A comment
    visit url('/blog/')
        expect "prod" == env
EOF;
        $build = new Build('4b4fee4b-af1b-460b-8db2-4ab8edb5b62c');
        $scenarioSet = $parser->parse($scenarioSetBase);
        $scenarioSet->getExtraBag()->set(\sprintf('blackfire_build:%s', 'Blackfire Test'), $build);
        $scenarioSet->setStatus(BuildStatus::IN_PROGRESS);
        yield 'error 500 on in-progress build should silent error' => [
            $scenarioSet,
            false,
        ];

        $build = new Build('cc085ed3-3b9a-4889-932d-abae6d5dad30');
        $scenarioSet = $parser->parse($scenarioSetBase);
        $scenarioSet->getExtraBag()->set(\sprintf('blackfire_build:%s', 'Blackfire Test'), $build);
        $scenarioSet->setStatus(BuildStatus::DONE);
        yield 'error 500 on done build should throw' => [
            $scenarioSet,
            true,
        ];
    }
}
