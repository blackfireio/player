<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Extension;

use Blackfire\Player\Enum\BuildStatus;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ExpressionLanguage\Provider;
use Blackfire\Player\Extension\FollowExtension;
use Blackfire\Player\Http\Request;
use Blackfire\Player\Http\Response;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\FollowStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\VisitStep;
use Blackfire\Player\Tests\Caster\ResetStepUuidDumpTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class FollowExtensionTest extends TestCase
{
    use ResetStepUuidDumpTrait;
    use VarDumperTestTrait;

    protected function setUp(): void
    {
        $this->resetStepUuidOnDump();
    }

    /**
     * @dataProvider provideGetNextStepsCases
     */
    public function testGetNextSteps(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext, array $expectedNextSteps)
    {
        $language = new ExpressionLanguage(null, [new Provider()]);
        $extension = new FollowExtension($language);

        $nextSteps = $extension->getNextSteps($step, $stepContext, $scenarioContext);
        $nextStepsAsArray = iterator_to_array($nextSteps);
        $this->assertDumpEquals($this->getDump($expectedNextSteps), $nextStepsAsArray);
    }

    public static function provideGetNextStepsCases()
    {
        $step = new VisitStep('https://app.lan');
        $stepContext = new StepContext();
        $stepContext->update($step, []);
        yield 'wont follow redirect' => [
            $step,
            $stepContext,
            new ScenarioContext('test-get-next-step', new ScenarioSet()),
            [],
        ];

        $step = new VisitStep('https://app.lan');
        $step->followRedirects('true');
        $stepContext = new StepContext();
        $stepContext->update($step, []);
        $scenarioSet = new ScenarioContext('test-get-next-step', new ScenarioSet());
        $scenarioSet->setLastResponse(new Response(new Request('GET', 'https://app.lan'), 200, [], 'the response', []));
        yield 'wont follow redirect without a previous response with correct HTTP status code and Location header' => [
            $step,
            $stepContext,
            $scenarioSet,
            [],
        ];

        $step = new VisitStep('https://app.lan');
        $step->followRedirects('true');
        $stepContext = new StepContext();
        $stepContext->update($step, []);
        $scenarioSet = new ScenarioContext('test-get-next-step', new ScenarioSet());
        $scenarioSet->setLastResponse(new Response(new Request('GET', 'https://app.lan'), 302, ['location' => ['https://redirect-to.local']], 'the response', []));
        $expectedFollowStep = new FollowStep(null, null, $step);
        $expectedFollowStep->followRedirects('true');
        $expectedFollowStep->setStatus(BuildStatus::TODO);
        $expectedFollowStep->name("'Auto-following redirect to https://redirect-to.local'");
        yield 'yields a FollowStep with initiator' => [
            $step,
            $stepContext,
            $scenarioSet,
            [$expectedFollowStep],
        ];
    }
}
