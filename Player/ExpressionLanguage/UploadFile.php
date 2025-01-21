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
 *
 * @internal
 */
class UploadFile implements \Stringable
{
    public function __construct(
        private readonly string $filename,
        private readonly string $name,
    ) {
        if (!is_readable($filename)) {
            throw new InvalidArgumentException(\sprintf('File "%s" does not exist or is not readable.', $filename));
        }
    }

    public function __toString(): string
    {
        return (string) file_get_contents($this->filename);
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public static function isAbsolutePath(string $file): bool
    {
        return strspn($file, '/\\', 0, 1)
            || (\strlen($file) > 3 && ctype_alpha($file[0])
                && ':' === $file[1]
                && strspn($file, '/\\', 2, 1)
            )
            || null !== parse_url($file, \PHP_URL_SCHEME)
        ;
    }
}
