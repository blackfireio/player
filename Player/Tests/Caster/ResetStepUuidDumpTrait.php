<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Caster;

use Blackfire\Player\Step\AbstractStep;
use Symfony\Component\VarDumper\Cloner\Stub;

trait ResetStepUuidDumpTrait
{
    public function resetStepUuidOnDump()
    {
        $casters = [
            AbstractStep::class => static function (AbstractStep $object, array $array, Stub $stub, bool $isNested, int $filter = 0): array {
                $array["\x00Blackfire\Player\Step\AbstractStep\x00uuid"] = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

                return $array;
            },
        ];

        $this->setUpVarDumper($casters);
    }
}
