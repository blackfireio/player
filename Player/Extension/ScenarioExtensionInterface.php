<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Extension;

use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\ScenarioResult;

/**
 * @internal
 */
interface ScenarioExtensionInterface
{
    public function beforeScenario(Scenario $scenario, ScenarioContext $scenarioContext): void;

    public function afterScenario(Scenario $scenario, ScenarioContext $scenarioContext, ScenarioResult $scenarioResult): void;
}
