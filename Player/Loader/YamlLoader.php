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
use Symfony\Component\Yaml\Parser as YamlParser;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
class YamlLoader extends ArrayLoader
{
    private $parser;

    public function __construct()
    {
        $this->parser = new YamlParser();
    }

    public function load($yaml)
    {
        $data = $this->parser->parse($yaml);

        if (isset($data['scenario'])) {
            $data = $data['scenario'];
        } elseif (isset($data['scenarios'])) {
            $data = $data['scenarios'];
        } else {
            throw new LoaderException(sprintf('YAML scenarios must be defined under the "scenario" or "scenario" key.'));
        }

        return parent::load($data);
    }
}
