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

use Blackfire\Player\ScenarioSet;
use Blackfire\Player\ScenarioSetResult;

/**
 * @internal
 */
interface ScenarioSetExtensionInterface
{
    public function beforeScenarioSet(ScenarioSet $scenarios, int $concurrency): void;

    public function afterScenarioSet(ScenarioSet $scenarios, int $concurrency, ScenarioSetResult $scenarioSetResult): void;
}
