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
 * @author Xavier Leune <xavier@ccmbenchmark.com>
 *
 * Courtesy of Xavier Leune work
 *
 * @see https://github.com/xavierleune/demo-forum-php/blob/master/src/Extractor/UrlCrawler6.php
 *
 * @internal
 */
final class DisableInternalNetworkExtension implements StepExtensionInterface
{
    public function beforeStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
        if (!$step instanceof RequestStep) {
            return;
        }

        $request = $step->getRequest();
        $host = parse_url($request->uri, \PHP_URL_HOST);
        if (!$host) {
            throw new \InvalidArgumentException(sprintf('Unable to parse host from uri "%s"', $request->uri));
        }

        if ($this->isIpAddress($host)) {
            $this->assertsIsPublicIp($host);
        } elseif (!isset($request->options['resolve'][$host])) {
            // Force resolved IP (because the DNS could return another IP when cURL will resolve the hostname)
            $request->options['resolve'] = [$host => $this->resolveHost($host)];
        }

        foreach ($request->options['resolve'] ?? [] as $resolveHost => $resolveIp) {
            $this->assertsIsPublicIp($resolveIp, sprintf('The host "%s" resolves to a forbidden IP', $resolveHost));
        }
    }

    public function afterStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
    }

    private function isIpAddress(string $host): bool
    {
        return preg_match('/^((2[0-4]|1\d|[1-9])?\d|25[0-5])(\.(?1)){3}\z/', $host)
            || preg_match('/^(((?=(?>.*?(::))(?!.+\3)))\3?|([\dA-F]{1,4}(\3|:(?!$)|$)|\2))(?4){5}((?4){2}|((2[0-4]|1\d|[1-9])?\d|25[0-5])(\.(?7)){3})\z/i', $host);
    }

    private function assertsIsPublicIp(string $ip, string $context = 'Forbidden host IP'): void
    {
        if (false === filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE)) {
            throw new SecurityException(sprintf('%s %s', $context, $ip));
        }
    }

    private function resolveHost(string $host): string
    {
        if (false === filter_var($host, \FILTER_VALIDATE_DOMAIN)) {
            throw new SecurityException(sprintf('Invalid host name %s', $host));
        }
        $resolvedIp = gethostbyname($host);

        if ($resolvedIp === $host) {
            throw new SecurityException(sprintf('Could not resolve host: %s', $host));
        }

        return $resolvedIp;
    }
}
