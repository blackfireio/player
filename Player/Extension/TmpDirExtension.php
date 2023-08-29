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
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class TmpDirExtension implements ScenarioExtensionInterface
{
    public const EXTRA_VALUE_KEY = 'tmp_dir';

    public function __construct(
        private readonly Filesystem $fs,
    ) {
    }

    public function beforeScenario(Scenario $scenario, ScenarioContext $scenarioContext): void
    {
        $tmpDir = sprintf('%s/blackfire-tmp-dir/%s/%s', sys_get_temp_dir(), date('y-m-d-H-m-s'), bin2hex(random_bytes(5)));
        $this->fs->mkdir($tmpDir);
        $scenarioContext->setExtraValue(self::EXTRA_VALUE_KEY, $tmpDir);
    }

    public function afterScenario(Scenario $scenario, ScenarioContext $scenarioContext, ScenarioResult $scenarioResult): void
    {
        if (null !== $dir = $scenarioContext->getExtraValue(self::EXTRA_VALUE_KEY)) {
            $this->fs->remove($dir);
        }
    }
}
