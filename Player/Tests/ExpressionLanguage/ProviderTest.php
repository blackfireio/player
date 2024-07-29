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

use Blackfire\Player\Exception\InvalidArgumentException;
use Blackfire\Player\Exception\SecurityException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\ExpressionLanguage\Provider;
use Blackfire\Player\ExpressionLanguage\UploadFile;
use Blackfire\Player\Extension\TmpDirExtension;
use Blackfire\Player\ValueBag;
use Faker\Generator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ProviderTest extends TestCase
{
    public function testItHasFunctions()
    {
        $provider = new Provider();

        $language = new ExpressionLanguage(null, [$provider]);

        $res = $language->evaluate('trim("   hello  ")');
        $this->assertEquals('hello', $res);

        $res = $language->evaluate('file("../fixtures/yaml/.blackfire.yaml", "name")', ['_working_dir' => __DIR__.'/']);
        $this->assertInstanceOf(UploadFile::class, $res);
    }

    public function testSandboxModeFileRelativeFile()
    {
        $provider = new Provider(null, true);
        $language = new ExpressionLanguage(null, [$provider]);

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('The "file" provider does not support relative file paths in the sandbox mode (use the "fake()" function instead).');
        $language->evaluate('file("file", "name")', ['_working_dir' => __DIR__.'/']);
    }

    public function testSandboxModeFileAbsoluteFile()
    {
        $provider = new Provider(null, true);
        $language = new ExpressionLanguage(null, [$provider]);
        $tmpDir = \sprintf('%s/blackfire-tmp-dir/%s/%s', sys_get_temp_dir(), date('y-m-d-H-m-s'), bin2hex(random_bytes(5)));
        $extra = new ValueBag();
        $extra->set(TmpDirExtension::EXTRA_VALUE_KEY, $tmpDir);
        $fs = new Filesystem();
        $fs->mkdir($tmpDir);

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('The "file" provider does not support absolute file paths in the sandbox mode (use the "fake()" function instead).');
        try {
            $language->evaluate('file("/file", "name")', ['_extra' => $extra, '_working_dir' => __DIR__.'/']);
        } finally {
            $fs->remove($tmpDir);
        }
    }

    public function testSandboxModeFakerImageProvider()
    {
        $faker = new Generator();
        $faker->addProvider(new SafeFakerImageProvider($faker));

        $provider = new Provider($faker, true);
        $language = new ExpressionLanguage(null, [$provider]);
        $tmpDir = \sprintf('%s/blackfire-tmp-dir/%s/%s', sys_get_temp_dir(), date('y-m-d-H-m-s'), bin2hex(random_bytes(5)));
        $extra = new ValueBag();
        $extra->set(TmpDirExtension::EXTRA_VALUE_KEY, $tmpDir);
        $fs = new Filesystem();
        $fs->mkdir($tmpDir);

        try {
            $res = $language->evaluate('fake("image")', ['_extra' => $extra, '_working_dir' => __DIR__.'/']);
            $this->assertStringStartsWith($tmpDir, $res);
        } finally {
            $fs->remove($tmpDir);
        }
    }

    public function testSandboxModeFakerFileProvider()
    {
        $provider = new Provider(null, true);
        $language = new ExpressionLanguage(null, [$provider]);

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('The "file" faker provider is not supported in sandbox mode.');
        $language->evaluate('fake("file", "a")');
    }

    public function testNonExistentFileThrowsAnException()
    {
        $provider = new Provider(null, false);
        $language = new ExpressionLanguage(null, [$provider]);

        $this->expectException(InvalidArgumentException::class);
        $file = __DIR__.'/file';
        $this->expectExceptionMessage(\sprintf('File "%s" does not exist or is not readable.', $file));
        $language->evaluate('file("file")', ['_working_dir' => __DIR__.'/']);
    }
}
