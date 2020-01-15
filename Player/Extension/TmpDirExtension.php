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

use Blackfire\Player\Context;
use Blackfire\Player\Result;
use Blackfire\Player\Scenario;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class TmpDirExtension extends AbstractExtension
{
    private $fs;

    public function __construct()
    {
        $this->fs = new Filesystem();
    }

    public function enterScenario(Scenario $scenario, Context $context)
    {
        $tmpDir = sprintf('%s/blackfire-tmp-dir/%s/%s', sys_get_temp_dir(), date('y-m-d-H-m-s'), mt_rand());
        $this->fs->mkdir($tmpDir);
        $context->getExtraBag()->set('tmp_dir', $tmpDir);
    }

    public function leaveScenario(Scenario $scenario, Result $result, Context $context)
    {
        $extra = $context->getExtraBag();
        if ($extra->has('tmp_dir')) {
            $this->fs->remove($extra->get('tmp_dir'));
        }
    }
}
