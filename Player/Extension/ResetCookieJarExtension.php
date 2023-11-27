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

use Blackfire\Player\Http\CookieJar;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\ScenarioResult;

class ResetCookieJarExtension implements ScenarioExtensionInterface
{
    public function __construct(
        private CookieJar $cookieJar,
    ) {
    }

    public function beforeScenario(Scenario $scenario, ScenarioContext $scenarioContext): void
    {
        $this->cookieJar->clear();
    }

    public function afterScenario(Scenario $scenario, ScenarioContext $scenarioContext, ScenarioResult $scenarioResult): void
    {
    }
}
