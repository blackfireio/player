<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Serializer;

use Blackfire\Player\Build\Build;
use Blackfire\Player\Enum\BuildStatus;
use Blackfire\Player\Json;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioSet;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
class ScenarioSetSerializer
{
    private readonly NormalizerInterface $normalizer;

    public function __construct()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());

        $this->normalizer = new Serializer([new ObjectNormalizer($classMetadataFactory, $nameConverter)], []);
    }

    public function serialize(ScenarioSet $scenarioSet, Build $build): string
    {
        return Json::encode($this->normalize($scenarioSet, $build), \JSON_PRETTY_PRINT);
    }

    public function serializeForJsonView(ScenarioSet $scenarioSet, Build $build): string
    {
        $data = $this->normalize($scenarioSet, $build);

        unset($data['name'], $data['endpoint'], $data['blackfire_environment']);
        foreach ($data['scenarios'] as &$scenario) {
            $this->cleanStepForJsonView($scenario);
        }

        return Json::encode($data, \JSON_PRETTY_PRINT);
    }

    public function normalize(ScenarioSet $scenarioSet, Build|null $build = null): array
    {
        if ($build) {
            $filteredScenarios = array_values(
                array_filter(
                    $scenarioSet->getScenarios(),
                    static fn (Scenario $scenario): bool => $scenario->getBlackfireBuildUuid() === $build->uuid || null === $scenario->getBlackfireBuildUuid()
                )
            );
        } else {
            $filteredScenarios = $scenarioSet;
        }

        $data = [
            'version' => $scenarioSet->computeNextVersion(),
            'name' => $this->removeQuote($scenarioSet->getName()),
            'variables' => $this->removeQuote($scenarioSet->getVariables()),
            'endpoint' => $this->removeQuote($scenarioSet->getEndpoint()),
            'blackfire_environment' => $scenarioSet->getBlackfireEnvironment(),
            'status' => $scenarioSet->getStatus()->value,
            'scenarios' => $this->normalizer->normalize($filteredScenarios, 'json', [AbstractObjectNormalizer::SKIP_NULL_VALUES => true]),
        ];

        $allScenariosAreDone = true;
        foreach ($data['scenarios'] as &$scenario) {
            $this->cleanStep($scenario);
            if ('done' !== $scenario['status']) {
                $allScenariosAreDone = false;
            }

            // steps property is required
            $scenario['steps'] ??= [];

            $scenario['variables'] = $this->removeQuote($scenario['variables']);
            if (empty($scenario['variables']['endpoint'])) {
                unset($scenario['variables']['endpoint']);
            }

            $scenario['variables'] = array_diff_assoc($scenario['variables'], $data['variables']);
        }

        if ($allScenariosAreDone) {
            $data['status'] = 'done';
            $scenarioSet->setStatus(BuildStatus::DONE);
        }

        return $data;
    }

    private function cleanStep(array &$step): void
    {
        unset($step['iid']);
        $step['name'] = $this->removeQuote($step['name'] ?? null);
        $step = array_filter($step, static fn (mixed $c): bool => null !== $c && [] !== $c);

        foreach (['if_step', 'else_step', 'loop_step', 'while_step'] as $type) {
            if (isset($step[$type])) {
                $this->cleanStep($step[$type]);
            }
        }

        if (!isset($step['steps']) && !isset($step['generated_steps'])) {
            return;
        }

        // Merge `steps` and `generated_steps` while preserving the orders
        $steps = [];
        foreach ($step['generated_steps'] ?? [] as $s) {
            $id = $s['iid'];
            $steps[$id] = $s;
        }
        foreach ($step['steps'] ?? [] as $s) {
            $id = $s['iid'];
            if (!isset($steps[$id])) {
                $steps[$id] = $s;
            }
        }

        $step['steps'] = array_values($steps);
        unset($step['generated_steps']);

        array_walk($step['steps'], $this->cleanStep(...));
    }

    private function cleanStepForJsonView(array &$step): void
    {
        $step = array_intersect_key($step, [
            'name' => true,
            'blackfire_profile_uuid' => true,
            'variables' => true,
            'status' => true,
            'steps' => true,
            'type' => true,
            'uuid' => true,
            'failing_expectations' => true,
            'failing_assertions' => true,
            'errors' => true,
            'deprecations' => true,
            'initiator_uuid' => true,
            'created_at' => true,
            'finished_at' => true,
        ]);

        if (isset($step['steps'])) {
            array_walk($step['steps'], $this->cleanStepForJsonView(...));
        }
    }

    private function removeQuote(mixed $string): mixed
    {
        if (\is_array($string)) {
            foreach ($string as $k => $v) {
                $string[$k] = $this->removeQuote($v);
            }

            return $string;
        }

        if (!\is_string($string)) {
            return $string;
        }

        if (\strlen($string) < 2) {
            return $string;
        }

        if ((str_starts_with($string, '\'') && str_ends_with($string, '\'')) || (str_starts_with($string, '"') && str_ends_with($string, '"'))) {
            return substr($string, 1, -1);
        }

        return $string;
    }
}
