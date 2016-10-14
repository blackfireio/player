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

use Psr\Http\Message\RequestInterface;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
interface RunnerInterface
{
    public function getMaxConcurrency();

    public function send($clientId, RequestInterface $request, Context $context);

    public function end($clientId);
}
