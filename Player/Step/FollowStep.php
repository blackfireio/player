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

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class FollowStep extends Step implements StepInitiatorInterface
{
    use StepInitiatorTrait;

    public function __construct(
        private readonly string|null $file = null,
        private readonly int|null $line = null,
        Step|null $initiator = null,
    ) {
        parent::__construct($file, $line);

        $this->setInitiator($initiator);
    }
}
