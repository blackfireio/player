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
 * @author Xavier Leune <xavier@ccmbenchmark.com>
 *
 * Courtesy of Xavier Leune work
 *
 * @see https://github.com/xavierleune/demo-forum-php/blob/master/src/Extractor/UrlCrawler6.php
 *
 * @internal
 */
final class DisableInternalNetworkExtension extends AbstractExtension
{
    public function enterStep(AbstractStep $step, RequestInterface $request, Context $context): RequestInterface
    {
        $host = $request->getUri()->getHost();

        // Looks like an ip
        if (
            preg_match('/^((2[0-4]|1\d|[1-9])?\d|25[0-5])(\.(?1)){3}\z/', $host)
            || preg_match('/^(((?=(?>.*?(::))(?!.+\3)))\3?|([\dA-F]{1,4}(\3|:(?!$)|$)|\2))(?4){5}((?4){2}|((2[0-4]|1\d|[1-9])?\d|25[0-5])(\.(?7)){3})\z/i', $host)
        ) {
            if (false === filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE)) {
                throw new SecurityException('Forbidden host IP.');
            }
        } else {
            if (false === filter_var($host, \FILTER_VALIDATE_DOMAIN)) {
                throw new SecurityException('Invalid host name.');
            }
            $ip = gethostbyname($host);

            if ($ip === $host) {
                throw new SecurityException(sprintf('Could not resolve host: %s.', $host));
            }

            if (false === filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE)) {
                throw new SecurityException(sprintf('The host "%s" resolves to a forbidden IP.', $host));
            }

            // Force IP
            $context->setResolvedIp($ip);
        }

        return $request;
    }
}
