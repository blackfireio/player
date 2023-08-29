<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Step;

use Symfony\Component\Serializer\Annotation\Ignore;

trait StepInitiatorTrait
{
    #[Ignore]
    private readonly ?Step $initiator;

    public function getInitiator(): ?Step
    {
        return $this->initiator;
    }

    public function setInitiator(null|Step $step): void
    {
        if ($step instanceof StepInitiatorInterface) {
            $this->initiator = $step->getInitiator() ?? $step;
        } else {
            $this->initiator = $step;
        }
    }

    public function getInitiatorUuid(): string|null
    {
        return $this->initiator?->getUuid();
    }
}
