<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Loader;

use Blackfire\Player\Exception\LoaderException;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\VisitStep;
use Symfony\Component\Yaml\Yaml;

class DotBlackfireYamlLoader implements LoaderInterface
{
    public function supports($resource)
    {
        return file_exists($resource) && '.blackfire.yml' === basename($resource);
    }

    public function load($resource)
    {
        $content = Yaml::parse(file_get_contents($resource));
        if (!isset($content['scenarios']) || !is_array($content['scenarios'])) {
            throw new LoaderException('No scenario found.');
        }

        $scenarioSet = new ScenarioSet();
        foreach ($content['scenarios'] as $key => $config) {
            if (!is_array($config)) {
                throw new LoaderException(sprintf('Scenario "%s" body must be an array.', $key));
            }

            $scenario = new Scenario($key, $resource);
            $scenario->name($this->escapeValue($key));

            foreach ($config as $definition) {
                if (is_string($definition)) {
                    $uri = $definition;
                } elseif (is_array($definition) && isset($definition['path'])) {
                    $uri = $definition['path'];
                } else {
                    throw new LoaderException(sprintf('Scenario "%s" must contains a "path".', $key));
                }

                $step = new VisitStep(sprintf('url(\'%s\')', $uri), $resource);

                if (is_array($definition)) {
                    if (isset($definition['method'])) {
                        $step->method($this->escapeValue($definition['method']));
                    }

                    if (isset($definition['samples'])) {
                        $step->samples($definition['samples']);
                    }

                    if (isset($definition['headers'])) {
                        foreach ($definition['headers'] as $header => $value) {
                            $step->header($this->escapeValue($header.': '.$value));
                        }
                    }

                    if (isset($definition['warmup'])) {
                        if (true === $definition['warmup'] || 'auto' === $definition['warmup']) {
                            $step->warmup('true');
                        } elseif (false === $definition['warmup']) {
                            $step->warmup('false');
                        }
                    }
                }

                $scenario->setBlockStep($step);
            }

            $scenarioSet->add($scenario);
        }

        return $scenarioSet;
    }

    private function escapeValue($value)
    {
        return sprintf("'%s'", $value);
    }
}
