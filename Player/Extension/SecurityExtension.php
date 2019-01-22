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
use Blackfire\Player\Exception\SecurityException;
use Blackfire\Player\Step\AbstractStep;
use Psr\Http\Message\RequestInterface;

/**
 * @author Luc Vieillescazes <luc.vieillescazes@blackfire.io>
 *
 * @internal
 */
final class SecurityExtension extends AbstractExtension
{
    public function enterStep(AbstractStep $step, RequestInterface $request, Context $context)
    {
        $scheme = $request->getUri()->getScheme();

        // Other protocols are disabled by Guzzle anyway if cURL is recent enough
        if (!\in_array($scheme, ['http', 'https'])) {
            throw new SecurityException(sprintf('Invalid protocol ("%s").', $scheme));
        }

        return $request;
    }
}
