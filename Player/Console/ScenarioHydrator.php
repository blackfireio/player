<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Console;

use Blackfire\Player\ParserFactory;
use Blackfire\Player\ScenarioSet;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
class ScenarioHydrator
{
    public function __construct(
        private readonly ParserFactory $parserFactory,
    ) {
    }

    /**
     * @return string[]
     */
    public function getVariables(InputInterface $input): array
    {
        $variables = [];

        if ($input->getOption('endpoint')) {
            $variables['endpoint'] = $this->escapeValue($input->getOption('endpoint'));
        }

        if ($input->getOption('blackfire-env')) {
            $variables['blackfire-env'] = $this->escapeValue($input->getOption('blackfire-env'));
        }

        foreach ($input->getOption('variable') as $variable) {
            [$key, $value] = explode('=', (string) $variable, 2);
            $variables[$key] = $this->escapeValue($value);
        }

        return $variables;
    }

    public function hydrate(InputInterface $input): ScenarioSet
    {
        $parser = $this->parserFactory->createParser($this->getVariables($input));

        $scenarios = $parser->load($input->getArgument('file'));

        foreach ($parser->getGlobalVariables() as $key => $value) {
            $scenarios->setVariable($key, $value);
        }

        // FIXME: should be set on the ScenarioSet directly
        // but for this, we need an beforeStep() for the ScenarioSet, which we don't have yet
        foreach ($scenarios as $scenario) {
            if (null !== $input->getOption('endpoint') && !$scenario->getEndpoint()) {
                $scenario->endpoint($this->escapeValue($input->getOption('endpoint')));
            }

            foreach ($parser->getGlobalVariables() as $key => $value) {
                $scenario->set($key, $value);
            }
        }

        if (null !== $input->getOption('blackfire-env')) {
            $scenarios->setBlackfireEnvironment($input->getOption('blackfire-env'));
        }

        return $scenarios;
    }

    private function escapeValue(string $value): string
    {
        return var_export($value, true);
    }
}
