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

namespace Blackfire\Player\Tests\Serializer;

use Blackfire\Player\Build\Build;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ExpressionLanguage\Provider as LanguageProvider;
use Blackfire\Player\Json;
use Blackfire\Player\Parser;
use Blackfire\Player\Scenario;
use Blackfire\Player\Serializer\ScenarioSetSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScenarioSetSerializerTest extends TestCase
{
    #[DataProvider('provideScenarioAndSerializations')]
    public function testSerialization(string $scenarioFile, string $expectedFile): void
    {
        $parser = new Parser(new ExpressionLanguage(null, [new LanguageProvider()]));
        $scenarioSet = $parser->parse(file_get_contents($scenarioFile));

        $serializer = new ScenarioSetSerializer();

        $build = new Build('1111-2222-3333-4444');
        $normalized = $serializer->normalize($scenarioSet, $build);
        unset($normalized['version']);

        $normalizedScenarioSet = $this->filterStepUuids($normalized);
        $serialized = Json::encode($normalizedScenarioSet, \JSON_PRETTY_PRINT);

        if (getenv('UPDATE_FIXTURES')) {
            file_put_contents($expectedFile, $serialized);
        }

        $this->assertJsonStringEqualsJsonFile($expectedFile, $serialized);
    }

    public static function provideScenarioAndSerializations(): \Generator
    {
        yield [__DIR__.'/fixtures/test1.bkf', __DIR__.'/fixtures/test1.json'];
        yield [__DIR__.'/fixtures/test2.bkf', __DIR__.'/fixtures/test2.json'];
        yield [__DIR__.'/fixtures/test3.bkf', __DIR__.'/fixtures/test3.json'];
        yield [__DIR__.'/fixtures/test4.bkf', __DIR__.'/fixtures/test4.json'];
    }

    #[DataProvider('provideScenarioAndSerializationsAndBuilds')]
    public function testSerializationWhileProcessing(string $scenarioFile, string $expectedFile, callable $scenarioSetDecorator, Build $build): void
    {
        $parser = new Parser(new ExpressionLanguage(null, [new LanguageProvider()]));
        $scenarioSet = $parser->parse(file_get_contents($scenarioFile));

        $serializer = new ScenarioSetSerializer();

        $scenarioSetDecorator($scenarioSet);

        $normalized = $serializer->normalize($scenarioSet, $build);
        unset($normalized['version']);
        $normalizedScenarioSet = $this->filterStepUuids($normalized);

        $serialized = Json::encode($normalizedScenarioSet, \JSON_PRETTY_PRINT);

        if (getenv('UPDATE_FIXTURES')) {
            file_put_contents($expectedFile, $serialized);
        }

        $this->assertJsonStringEqualsJsonFile($expectedFile, $serialized);
    }

    public static function provideScenarioAndSerializationsAndBuilds(): \Generator
    {
        yield 'scenario env arent evaluated yet should appear' => [
            __DIR__.'/fixtures/test5.bkf',
            __DIR__.'/fixtures/test5_1.json',
            function ($scenarioSet): void {
            },
            new Build('1111-2222-3333-4444'),
        ];

        yield 'scenarios whose env belong to the build should appear' => [
            __DIR__.'/fixtures/test5.bkf',
            __DIR__.'/fixtures/test5_1.json',
            function ($scenarioSet): void {
                $scenarios = iterator_to_array($scenarioSet);
                $scenarios[0]->setBlackfireBuildUuid('1111-2222-3333-4444');
            },
            new Build('1111-2222-3333-4444'),
        ];

        yield 'scenario belonging to another build are hidden' => [
            __DIR__.'/fixtures/test5.bkf',
            __DIR__.'/fixtures/test5_2.json',
            function ($scenarioSet): void {
                $scenarios = iterator_to_array($scenarioSet);
                $scenarios[0]->setBlackfireBuildUuid('1111-2222-3333-4444');
                $scenarios[1]->setBlackfireBuildUuid('9999-9999-9999-9999');
            },
            new Build('1111-2222-3333-4444'),
        ];
    }

    #[DataProvider('provideSerializeForJsonView')]
    public function testSerializeForJsonView(string $scenarioFile, string $expectedFile, callable $scenarioSetDecorator, Build $build): void
    {
        $parser = new Parser(new ExpressionLanguage(null, [new LanguageProvider()]));
        $scenarioSet = $parser->parse(file_get_contents($scenarioFile));

        $serializer = new ScenarioSetSerializer();

        $scenarioSetDecorator($scenarioSet);

        $normalized = Json::decode($serializer->serializeForJsonView($scenarioSet, $build));
        unset($normalized['version']);
        $normalizedScenarioSet = $this->filterStepUuids($normalized);

        $serialized = Json::encode($normalizedScenarioSet, \JSON_PRETTY_PRINT);

        if (getenv('UPDATE_FIXTURES')) {
            file_put_contents($expectedFile, $serialized);
        }

        $this->assertJsonStringEqualsJsonFile($expectedFile, $serialized);
    }

    public static function provideSerializeForJsonView(): \Generator
    {
        yield 'scenario with steps having failures and exceptions' => [
            __DIR__.'/fixtures/serialized_for_jsonview_with_failures_and_exceptions.bkf',
            __DIR__.'/fixtures/serialized_for_jsonview_with_failures_and_exceptions.json',
            function ($scenarioSet): void {
                /** @var Scenario[] $scenarios */
                $scenarios = iterator_to_array($scenarioSet);
                $firstScenarioSteps = $scenarios[0]->getSteps();
                foreach ($firstScenarioSteps as $firstScenarioStep) {
                    $firstScenarioStep->addFailingExpectation('An expectation failed', [
                        [
                            'expression' => '1 == 2',
                            'result' => false,
                        ],
                        [
                            'expression' => 'body()',
                            'result' => '<!DOCTYPE html><html lang="en"><meta charset="utf-8"/><head><title>Competitions endpoint</title></head><body>This is the body</body></html>',
                        ],
                    ]);
                    $firstScenarioStep->addFailingAssertion('An assertion failed'); // should be ignored
                }

                $secondScenarioSteps = $scenarios[1]->getSteps();
                foreach ($secondScenarioSteps as $secondScenarioStep) {
                    $secondScenarioStep->addError('Something exploded');
                }
            },
            new Build('1111-2222-3333-4444'),
        ];
    }

    /**
     * Ensures that every jsonview step has an UUID before removing it.
     */
    private function filterStepUuids($jsonView): array
    {
        $newJsonView = [...$jsonView];
        foreach ($newJsonView['scenarios'] as $k => $scenario) {
            $newJsonView['scenarios'][$k] = $this->filterStepUuid($scenario);
        }

        return $newJsonView;
    }

    private function filterStepUuid(array $step): array
    {
        $this->assertArrayHasKey('uuid', $step);
        unset($step['uuid']);

        $blockStepsProps = ['if_step', 'else_step', 'loop_step', 'while_step'];
        foreach ($blockStepsProps as $block) {
            if (isset($step[$block])) {
                $step[$block] = $this->filterStepUuid($step[$block]);
            }
        }

        if (isset($step['steps'])) {
            foreach ($step['steps'] as $k => $childStep) {
                $step['steps'][$k] = $this->filterStepUuid($childStep);
            }
        }

        return $step;
    }
}
