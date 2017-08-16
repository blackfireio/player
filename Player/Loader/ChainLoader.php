<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Loader;

use Blackfire\Player\Exception\LoaderException;

class ChainLoader implements LoaderInterface
{
    /**
     * @var LoaderInterface[]
     */
    private $loaders;

    public function __construct(array $loaders)
    {
        $this->loaders = $loaders;
    }

    public function supports($resource)
    {
        foreach ($this->loaders as $loader) {
            if ($loader->supports($resource)) {
                return true;
            }
        }

        return false;
    }

    public function load($resource)
    {
        foreach ($this->loaders as $loader) {
            if (!$loader->supports($resource)) {
                continue;
            }

            return $loader->load($resource);
        }

        throw new LoaderException(sprintf('Unable to load resource "%s".', $resource));
    }
}
