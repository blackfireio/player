<?php

namespace Blackfire\Player\Console;

use Blackfire\Player\Parser;
use Blackfire\Player\ScenarioSet;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
class ScenarioHydrator
{
    public function getVariables(InputInterface $input)
    {
        $variables = [];

        if ($input->getOption('endpoint')) {
            $variables['endpoint'] = $this->escapeValue($input->getOption('endpoint'));
        }

        foreach ($input->getOption('variable') as $variable) {
            list($key, $value) = explode('=', $variable, 2);
            $variables[$key] = $this->escapeValue($value);
        }

        return $variables;
    }

    public function hydrate(InputInterface $input)
    {
        $parser = new Parser($this->getVariables($input));

        /** @var ScenarioSet $scenarios */
        $scenarios = $parser->load($input->getArgument('file'));

        // FIXME: should be set on the ScenarioSet directly
        // but for this, we need an enterStep() for the ScenarioSet, which we don't have yet
        foreach ($scenarios as $scenario) {
            if (null !== $input->getOption('endpoint') && !$scenario->getEndpoint()) {
                $scenario->endpoint($this->escapeValue($input->getOption('endpoint')));
            }

            if (null !== $input->getOption('blackfire-env') && null === $scenario->getBlackfire()) {
                $scenario->blackfire($this->escapeValue($input->getOption('blackfire-env')));
            }

            foreach ($parser->getGlobalVariables() as $key => $value) {
                $scenario->set($key, $value);
            }
        }

        return $scenarios;
    }

    private function escapeValue($value)
    {
        return sprintf("'%s'", $value);
    }
}
