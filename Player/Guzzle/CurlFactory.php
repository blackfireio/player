<?php

namespace Blackfire\Player\Guzzle;

use GuzzleHttp\Handler\CurlFactory as BaseCurlFactory;
use Psr\Http\Message\RequestInterface;

class CurlFactory extends BaseCurlFactory
{
    public function create(RequestInterface $request, array $options)
    {
        $easy = parent::create($request, $options);

        if (isset($options['player_context']) && $resolvedIp = $options['player_context']->getResolvedIp()) {
            $uri = $request->getUri();
            $scheme = $uri->getScheme() ?? 'http';
            $port = ['http' => 80, 'https' => 443][$scheme] ?? 80;
            $port = $uri->getPort() ?? $port;

            $resolveString = sprintf('%s:%d:%s', $uri->getHost(), $port, $resolvedIp);
            curl_setopt($easy->handle, CURLOPT_RESOLVE, [$resolveString]);
        }

        return $easy;
    }
}
