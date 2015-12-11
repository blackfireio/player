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

use Blackfire\Player\Scenario;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
interface LoaderInterface
{
    /**
     * @return Scenario
     */
    public function load($data);
}
