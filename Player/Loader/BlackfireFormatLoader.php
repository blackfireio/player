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
use Blackfire\Player\Parser;

class BlackfireFormatLoader implements LoaderInterface
{
    private $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    public function supports($resource)
    {
        return file_exists($resource) && 'bkf' === pathinfo($resource, PATHINFO_EXTENSION);
    }

    public function load($resource)
    {
        try {
            return $this->parser->load($resource);
        } catch (\Exception $e) {
            throw new LoaderException(sprintf('Unable to load resource "%s".', $resource), 0, $e);
        }
    }
}
