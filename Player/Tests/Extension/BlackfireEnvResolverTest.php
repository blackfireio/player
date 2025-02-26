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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BlackfireEnvResolverTest extends TestCase
{
    #[DataProvider('resolveAnEnvironmentProvider')]
    public function testResolveAnEnvironment(string|null $defaultEnv, string|null $stepBlackfire, string|null $scenarioSetEnvironment, string|bool $expectedResult): void
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

    public static function resolveAnEnvironmentProvider(): \Generator
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

    #[DataProvider('precedenceProvider')]
    public function testResolveAnEnvironmentPrecedence(string|null $defaultEnv, string|null $stepBlackfire, string|null $scenarioSetEnvironment, string|bool $expectedResult): void
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

    public static function precedenceProvider(): \Generator
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

    #[DataProvider('errorsProvider')]
    public function testResolveThrowsAnError(string|null $defaultEnv, string|null $stepBlackfire, string|null $scenarioSetEnvironment): void
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

    public static function errorsProvider(): \Generator
    {
        yield 'no env were found but step resolves true' => [
            null,
            'true',
            null,
            true,
        ];
    }

    #[DataProvider('deprecationsProvider')]
    public function testDeprecationWarning(string|null $defaultEnv, string|null $stepBlackfire, string|null $scenarioSetEnvironment, string|bool $expectedResult, array $expectedDeprecations): void
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

    public static function deprecationsProvider(): \Generator
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

    private function arrange(string|null $defaultEnv, string|null $stepBlackfire, string|null $scenarioSetEnvironment): array
    {
        $language = new ExpressionLanguage(null, [new Provider()]);
        $resolver = new BlackfireEnvResolver($defaultEnv, $language);

        $step = new VisitStep('https://app.dev.bkf');
        if (null !== $stepBlackfire) {
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
