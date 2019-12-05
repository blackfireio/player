<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\ExpressionLanguage;

use Blackfire\Player\Exception\SecurityException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ExpressionLanguage\Provider;
use Blackfire\Player\ExpressionLanguage\UploadFile;
use PHPUnit\Framework\TestCase;

class ProviderTest extends TestCase
{
    public function testItHasFunctions()
    {
        $provider = new Provider();

        $language = new ExpressionLanguage(null, [$provider]);

        $res = $language->evaluate('trim("   hello  ")');
        $this->assertEquals('hello', $res);

        $res = $language->evaluate('file("file", "name")');
        $this->assertInstanceOf(UploadFile::class, $res);
    }

    public function testWeCanDisableFunction()
    {
        $provider = new Provider(null, ['file']);

        $language = new ExpressionLanguage(null, [$provider]);

        $res = $language->evaluate('trim("   hello  ")');
        $this->assertEquals('hello', $res);

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Function "file" is not available in builds.');
        $res = $language->evaluate('file("file", "name")');
    }
}
