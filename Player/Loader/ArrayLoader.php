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

use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Exception\LoaderException;
use Blackfire\Player\Exception\LogicException;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class ArrayLoader implements LoaderInterface
{
    public function load($data)
    {
        // data can be a scenario or an array of scenarios
        if (is_array($data) && isset($data['steps'])) {
            $data = [$data];
        } elseif (!is_array($data)) {
            throw new LoaderException(sprintf('Unable to load unrecognized scenarios.'));
        }

        $references = [];
        $scenarios = new ScenarioSet();
        foreach ($data as $config) {
            $scenario = $this->loadScenario($config, $references);
            if ($scenario->getKey()) {
                $references[$scenario->getKey()] = $scenario;
            } else {
                $scenarios->add($scenario);
            }
        }

        return $scenarios;
    }

    private function loadScenario($data, array $references)
    {
        $title = null;
        if (isset($data['options']['title'])) {
            $title = $data['options']['title'];
        }

        $scenario = new Scenario($title);

        if (isset($data['options'])) {
            if (isset($data['options']['auth'])) {
                $auth = $data['options']['auth'];
                if (is_array($auth)) {
                    $scenario->auth($auth[0], $auth[1]);
                } else {
                    $scenario->auth($auth);
                }
            }

            if (isset($data['options']['key'])) {
                $scenario->key($data['options']['key']);
            }

            if (isset($data['options']['delay'])) {
                $scenario->delay($data['options']['delay']);
            }

            if (isset($data['options']['endpoint'])) {
                $scenario->endpoint($data['options']['endpoint']);
            }

            if (isset($data['options']['variables'])) {
                foreach ($data['options']['variables'] as $key => $value) {
                    $scenario->value($key, $value);
                }
            }

            if (isset($data['options']['headers'])) {
                foreach ($data['options']['headers'] as $key => $value) {
                    $scenario->header($key, $value);
                }
            }
        }

        $first = true;
        foreach ($data['steps'] as $config) {
            if ($first) {
                if (!isset($config['visit']) && !isset($config['add'])) {
                    throw new LogicException('visit must be called as a first step.');
                }

                $step = $scenario;
                $first = false;
            }

            if (isset($config['visit'])) {
                $step = $step->visit($config['visit'], isset($config['method']) ? $config['method'] : 'GET', isset($config['params']) ? $config['params'] : []);
            } elseif (isset($config['click'])) {
                $step = $step->click($config['click']);
            } elseif (isset($config['submit'])) {
                $step = $step->submit($config['submit'], isset($config['params']) ? $config['params'] : []);
            } elseif (isset($config['follow'])) {
                $step = $step->follow();
            } elseif (isset($config['reload'])) {
                $step = $step->reload();
            } elseif (isset($config['add'])) {
                $key = $config['add'];
                if (!isset($references[$key])) {
                    throw new LogicException(sprintf('Scenario "%s" does not exist.', $key));
                }

                $step = $step->add($references[$key]);

                continue;
            } else {
                throw new LogicException(sprintf('Step "%s" must define a "visit", "click", "submit", "follow", "reload", or "add" item.', $title));
            }

            if (isset($config['title'])) {
                $step->title($config['title']);
            }

            if (isset($config['expect'])) {
                $this->ensureConfigurationPropertyIsArray($config, 'expect');
                foreach ($config['expect'] as $expectation) {
                    $step->expect($expectation);
                }
            }

            if (isset($config['delay'])) {
                $step->delay($config['delay']);
            }

            if (isset($config['assert'])) {
                $this->ensureConfigurationPropertyIsArray($config, 'assert');
                foreach ($config['assert'] as $assertion) {
                    $step->assert($assertion);
                }
            }

            if (isset($config['extract'])) {
                $this->ensureConfigurationPropertyIsArray($config, 'extract');
                foreach ($config['extract'] as $name => $cfg) {
                    if (is_array($cfg)) {
                        $step->extract($name, $cfg[0], $cfg[1]);
                    } else {
                        $step->extract($name, $cfg);
                    }
                }
            }

            if (isset($config['samples'])) {
                $step->samples($config['samples']);
            }

            if (isset($config['blackfire'])) {
                $step->blackfire($config['blackfire']);
            }

            if (isset($config['json']) && $config['json']) {
                $step->json();
            }

            if (isset($config['headers'])) {
                $this->ensureConfigurationPropertyIsArray($config, 'headers');
                foreach ($config['headers'] as $key => $value) {
                    $step->header($key, $value);
                }
            }
        }

        return $scenario;
    }

    private function ensureConfigurationPropertyIsArray($config, $property)
    {
        if (!is_array($config[$property])) {
            throw new \InvalidArgumentException(sprintf("'%s' must be an array", $property));
        }
    }
}
