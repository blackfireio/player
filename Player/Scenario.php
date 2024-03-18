<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player;

use Blackfire\Player\Step\GroupStep;
use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class Scenario extends GroupStep
{
    #[Ignore]
    protected string|null $blackfireBuildUuid = null;

    public function getType(): string|null
    {
        return null;
    }

    public function getBlackfireBuildUuid(): string|null
    {
        return $this->blackfireBuildUuid;
    }

    public function setBlackfireBuildUuid(string $blackfireBuildUuid): void
    {
        $this->blackfireBuildUuid = $blackfireBuildUuid;
    }
}
