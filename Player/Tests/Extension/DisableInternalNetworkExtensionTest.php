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

use Blackfire\Player\Exception\SecurityException;
use Blackfire\Player\Extension\DisableInternalNetworkExtension;
use Blackfire\Player\Http\Request as HttpRequest;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\RequestStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\VisitStep;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\DnsMock;

#[Group('dns-sensitive')]
class DisableInternalNetworkExtensionTest extends TestCase
{
    #[DataProvider('provideInvalidUri')]
    public function testBeforeRequestWithInvalidUri(string $uri, string $exceptionMessage): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage($exceptionMessage);

        DnsMock::withMockedHosts([
            'hack-local.com' => [['type' => 'A', 'ip' => '192.168.3.4']],
        ]);

        $extension = new DisableInternalNetworkExtension();
        $request = new HttpRequest('GET', $uri);

        $visitStep = new VisitStep($uri);

        $extension->beforeStep(new RequestStep($request, $visitStep), new StepContext(), $this->createMock(ScenarioContext::class));
    }

    public static function provideInvalidUri(): \Generator
    {
        yield ['http://127.0.0.1', 'Forbidden host IP'];
        yield ['http://10.12.8.5/index.php', 'Forbidden host IP'];
        yield ['https://hack-local.com/index.php', 'The host "hack-local.com" resolves to a forbidden IP'];
        yield ['http://notresolvable.com/', 'Could not resolve host: notresolvable.com'];
    }

    #[DataProvider('provideValidUri')]
    public function testBeforeRequestWithValidUri(string $uri): void
    {
        $extension = new DisableInternalNetworkExtension();
        $request = new HttpRequest('GET', $uri);

        $visitStep = new VisitStep($uri);

        $extension->beforeStep(new RequestStep($request, $visitStep), new StepContext(), $this->createMock(ScenarioContext::class));

        $this->expectNotToPerformAssertions();
    }

    public static function provideValidUri(): \Generator
    {
        yield ['http://54.75.240.245'];
        yield ['http://34.232.230.241/index.php'];
    }
}

DnsMock::register(DisableInternalNetworkExtension::class);
