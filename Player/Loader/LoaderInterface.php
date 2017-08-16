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
use Blackfire\Player\ScenarioSet;

interface LoaderInterface
{
    /**
     * @param mixed $resource
     *
     * @return bool
     */
    public function supports($resource);

    /**
     * @param mixed $resource
     *
     * @return ScenarioSet
     *
     * @throws LoaderException
     */
    public function load($resource);
}
