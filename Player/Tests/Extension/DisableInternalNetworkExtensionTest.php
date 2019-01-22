<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Extension;

use Blackfire\Player\Context;
use Blackfire\Player\Extension\DisableInternalNetworkExtension;
use Blackfire\Player\Step\AbstractStep;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\DnsMock;

/**
 * @group dns-sensitive
 */
class DisableInternalNetworkExtensionTest extends TestCase
{
    /**
     * @dataProvider provideInvalidUri
     *
     * @expectedException \Blackfire\Player\Exception\SecurityException
     */
    public function testInvalidUri($uri, $exceptionMessage)
    {
        $this->expectExceptionMessage($exceptionMessage);

        DnsMock::withMockedHosts([
            'hack-local.com' => [['type' => 'A', 'ip' => '192.168.3.4']],
        ]);

        $extension = new DisableInternalNetworkExtension();
        $request = new Request('GET', $uri);

        $extension->enterStep($this->createMock(AbstractStep::class), $request, $this->createMock(Context::class));
    }

    public function provideInvalidUri()
    {
        yield ['http://127.0.0.1', 'Forbidden host IP'];
        yield ['http://10.12.8.5/index.php', 'Forbidden host IP'];
        yield ['https://hack-local.com/index.php', 'The host "hack-local.com" resolves to a forbidden IP'];
        yield ['http://notresolvable.com/', 'Could not resolve host: notresolvable.com'];
    }

    /**
     * @dataProvider provideValidUri
     */
    public function testValidUri($uri)
    {
        $extension = new DisableInternalNetworkExtension();
        $request = new Request('GET', $uri);

        $res = $extension->enterStep($this->createMock(AbstractStep::class), $request, $this->createMock(Context::class));

        $this->assertSame($request, $res);
    }

    public function provideValidUri()
    {
        yield ['http://54.75.240.245'];
        yield ['http://34.232.230.241/index.php'];
    }

    public function testValidateFixedIP()
    {
        DnsMock::withMockedHosts([
            'hack-local.com' => [
                ['type' => 'A', 'ip' => '54.75.240.245'],
                ['type' => 'A', 'ip' => '192.168.3.4'],
            ],
        ]);

        $extension = new DisableInternalNetworkExtension();
        $request = new Request('GET', 'https://hack-local.com/index.php');

        $res = $extension->enterStep($this->createMock(AbstractStep::class), $request, $this->createMock(Context::class));

        $this->assertNotSame($request, $res);

        $this->assertEquals('https://54.75.240.245/index.php', (string) $res->getUri());
        $this->assertEquals('hack-local.com', (string) $res->getHeaderLine('Host'));
    }
}
