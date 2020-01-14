<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\ExpressionLanguage;

use Blackfire\Player\Exception\InvalidArgumentException;

/**
 * @author Luc Vieillescazes <luc.vieillescazes@blackfire.io>
 */
class UploadFile
{
    private $filename;
    private $name;

    public function __construct($filename, $name)
    {
        if (!is_readable($filename)) {
            throw new InvalidArgumentException(sprintf('File "%s" does not exist or is not readable.', $filename));
        }

        $this->filename = $filename;
        $this->name = $name;
    }

    public function __toString()
    {
        return file_get_contents($this->filename);
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function getName()
    {
        return $this->name;
    }

    public static function isAbsolutePath($file)
    {
        return strspn($file, '/\\', 0, 1)
            || (\strlen($file) > 3 && ctype_alpha($file[0])
                && ':' === $file[1]
                && strspn($file, '/\\', 2, 1)
            )
            || null !== parse_url($file, PHP_URL_SCHEME)
        ;
    }
}
