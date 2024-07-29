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

use Blackfire\Player\Exception\SecurityException;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\RequestStep;
use Blackfire\Player\Step\StepContext;

/**
 * @author Luc Vieillescazes <luc.vieillescazes@blackfire.io>
 *
 * @internal
 */
final class SecurityExtension implements StepExtensionInterface
{
    public function beforeStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
        if (!$step instanceof RequestStep) {
            return;
        }

        $request = $step->getRequest();
        $scheme = parse_url($request->uri, \PHP_URL_SCHEME);
        if (!\in_array($scheme, ['http', 'https'], true)) {
            throw new SecurityException(\sprintf('Invalid protocol ("%s").', $scheme));
        }
    }

    public function afterStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
    }
}
