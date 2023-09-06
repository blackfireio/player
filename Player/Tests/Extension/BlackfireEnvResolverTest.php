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

use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ExpressionLanguage\Provider;
use Blackfire\Player\Extension\BlackfireEnvResolver;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\VisitStep;
use PHPUnit\Framework\TestCase;

class BlackfireEnvResolverTest extends TestCase
{
    /**
     * @dataProvider resolveAnEnvironmentProvider
     */
    public function testResolveAnEnvironment(?string $defaultEnv, ?string $stepBlackfire, ?string $scenarioSetEnvironment, string|bool $expectedResult)
    {
        [
            $resolver,
            $stepContext,
            $scenarioContext,
            $step,
        ] = $this->arrange($defaultEnv, $stepBlackfire, $scenarioSetEnvironment);

        $output = $resolver->resolve($stepContext, $scenarioContext, $step);

        $this->assertEquals($expectedResult, $output);
    }

    public function resolveAnEnvironmentProvider()
    {
        yield 'resolves the step environment without default env' => [
            null,
            '"my environment"',
            '"global environment"',
            'my environment',
        ];

        yield 'resolves the step environment with default env' => [
            '"default env"',
            '"my environment"',
            '"global environment"',
            'my environment',
        ];

        yield 'resolves false when the blackfire step is false' => [
            '"default env"',
            'false',
            '"global environment"',
            false,
        ];

        yield 'resolves true when the blackfire step is true' => [
            '"default env"',
            'true',
            '"global environment"',
            true,
        ];

        yield 'resolves false when no env were found' => [
            null,
            null,
            null,
            false,
        ];
    }

    /**
     * @dataProvider precedenceProvider
     */
    public function testResolveAnEnvironmentPrecedence(?string $defaultEnv, ?string $stepBlackfire, ?string $scenarioSetEnvironment, string|bool $expectedResult)
    {
        [
            $resolver,
            $stepContext,
            $scenarioContext,
            $step,
        ] = $this->arrange($defaultEnv, $stepBlackfire, $scenarioSetEnvironment);

        $output = $resolver->resolve($stepContext, $scenarioContext, $step);

        $this->assertEquals($expectedResult, $output);
    }

    public function precedenceProvider()
    {
        // as stepContextes are created from the parent, if we have a non-null blackfire on the parent, we'll have a blackfire on the current step.
        yield 'resolves the blackfire-env property takes priority on the CLI when no blackfire step is defined' => [
            '"default env"',
            '"scenario env"',
            '"global env"',
            'scenario env',
        ];

        yield 'resolves the global environment when no blackfire step is defined' => [
            '"default env"',
            '"default env"',
            null,
            'default env',
        ];
    }

    /**
     * @dataProvider errorsProvider()
     */
    public function testResolveThrowsAnError(?string $defaultEnv, ?string $stepBlackfire, ?string $scenarioSetEnvironment)
    {
        [
            $resolver,
            $stepContext,
            $scenarioContext,
            $step,
        ] = $this->arrange($defaultEnv, $stepBlackfire, $scenarioSetEnvironment);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('--blackfire-env option must be set when using "blackfire: true" in a scenario.');

        $resolver->resolve($stepContext, $scenarioContext, $step);
    }

    public function errorsProvider()
    {
        yield 'no env were found but step resolves true' => [
            null,
            'true',
            null,
            true,
        ];
    }

    /**
     * @dataProvider deprecationsProvider
     */
    public function testDeprecationWarning(?string $defaultEnv, ?string $stepBlackfire, ?string $scenarioSetEnvironment, string|bool $expectedResult, array $expectedDeprecations)
    {
        [
            $resolver,
            $stepContext,
            $scenarioContext,
            $step,
        ] = $this->arrange($defaultEnv, $stepBlackfire, $scenarioSetEnvironment);

        $output = $resolver->resolve($stepContext, $scenarioContext, $step);

        $this->assertEquals($expectedDeprecations, $step->getDeprecations());
        $this->assertEquals($expectedResult, $output);
    }

    public function deprecationsProvider()
    {
        yield 'shows deprecation when blackfire resolves an environment name' => [
            'blackfire dev',
            '"blackfire dev"',
            null,
            'blackfire dev',
            [
                'Resolving an environment at the scenario level using the "blackfire" property is deprecated. Please use `--blackfire-env` instead.',
            ],
        ];

        yield 'no deprecation if it resolves boolean' => [
            'blackfire dev',
            'true',
            null,
            'blackfire dev',
            [],
        ];
    }

    private function arrange(?string $defaultEnv, ?string $stepBlackfire, ?string $scenarioSetEnvironment): array
    {
        $language = new ExpressionLanguage(null, [new Provider()]);
        $resolver = new BlackfireEnvResolver($defaultEnv, $language);

        $step = new VisitStep('https://app.bkf');
        if ($stepBlackfire) {
            $step->blackfire($stepBlackfire);
        }
        $stepContext = new StepContext();

        $stepContext->update($step, []);

        $scenarioSet = new ScenarioSet();
        $scenarioSet->setBlackfireEnvironment($scenarioSetEnvironment);
        $scenarioContext = new ScenarioContext('A scenario', $scenarioSet);

        return [
            $resolver,
            $stepContext,
            $scenarioContext,
            $step,
        ];
    }
}
