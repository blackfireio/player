<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Player\Tests\Loader;

use Blackfire\Player\Loader\ChainLoader;
use Blackfire\Player\Loader\LoaderInterface;

class ChainLoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider resourcesProvider
     */
    public function testSupports($resource, $expected)
    {
        $loaderZZ = $this->getMockBuilder(LoaderInterface::class)->getMock();
        $loaderZZ->method('supports')->will($this->returnCallback(function ($resource) {
            return 'ZZ' === pathinfo($resource, PATHINFO_EXTENSION);
        }));

        $loaderYY = $this->getMockBuilder(LoaderInterface::class)->getMock();
        $loaderYY->method('supports')->will($this->returnCallback(function ($resource) {
            return 'YY' === pathinfo($resource, PATHINFO_EXTENSION);
        }));

        $loader = new ChainLoader([
            $loaderZZ,
            $loaderYY,
        ]);

        $this->assertEquals($expected, $loader->supports($resource));
    }

    public function resourcesProvider()
    {
        yield ['test.AA', false];
        yield ['test.ZZ', true];
        yield ['test.YY', true];
    }
}
