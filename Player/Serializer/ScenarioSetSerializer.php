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

use Blackfire\Player\Json;
use Blackfire\Player\ScenarioSet;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @internal
 */
class ScenarioSetSerializer
{
    private readonly SerializerInterface $serializer;

    public function __construct()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $nameConverter = new MetadataAwareNameConverter($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());

        $this->serializer = new Serializer([new ObjectNormalizer($classMetadataFactory, $nameConverter)], [new JsonEncoder()]);
    }

    public function serialize(ScenarioSet $scenarioSet)
    {
        return Json::encode($this->normalize($scenarioSet), \JSON_PRETTY_PRINT);
    }

    public function normalize(ScenarioSet $scenarioSet)
    {
        $data = [
            'version' => microtime(true),
            'name' => $this->removeQuote($scenarioSet->getName()),
            'variables' => $this->removeQuote($scenarioSet->getVariables()),
            'endpoint' => $this->removeQuote($scenarioSet->getEndpoint()),
            'blackfire_environment' => $scenarioSet->getBlackfireEnvironment(),
            'scenarios' => $this->serializer->normalize($scenarioSet, 'json'),
        ];

        foreach ($data['scenarios'] as $key => $scenario) {
            $data['scenarios'][$key]['name'] = $this->removeQuote($scenario['name']);
            $data['scenarios'][$key]['variables'] = $this->removeQuote($scenario['variables']);

            $data['scenarios'][$key] = array_filter($data['scenarios'][$key], static fn ($c) => null !== $c && [] !== $c);
            foreach ($scenario['steps'] as $kStep => $step) {
                $data['scenarios'][$key]['steps'][$kStep] = $this->cleanStep($step);
            }

            if (isset($scenario['variables']['endpoint']) && empty($scenario['variables']['endpoint'])) {
                unset($data['scenarios'][$key]['variables']['endpoint']);
            }

            foreach (($data['scenarios'][$key]['variables'] ?? []) as $k => $v) {
                if (($data['variables'][$k] ?? null) === $v) {
                    unset($data['scenarios'][$key]['variables'][$k]);
                }
            }

            if (empty($data['scenarios'][$key]['variables'])) {
                unset($data['scenarios'][$key]['variables']);
            }
        }

        return $data;
    }

    private function cleanStep(array $step)
    {
        $step['name'] = $this->removeQuote($step['name']);
        $step = array_filter($step, static fn ($c) => null !== $c && [] !== $c);

        foreach (['if_step', 'else_step', 'loop_step'] as $type) {
            if (isset($step[$type])) {
                $step[$type] = $this->cleanStep($step[$type]);
            }
        }

        if (isset($step['steps'])) {
            foreach ($step['steps'] as $k => $s) {
                $step['steps'][$k] = $this->cleanStep($s);
            }
        }

        return $step;
    }

    private function removeQuote($string)
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
