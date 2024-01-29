<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests;

use Symfony\Component\VarDumper\Cloner\Cursor;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class VarDumper extends CliDumper
{
    public function enterHash(Cursor $cursor, $type, $class, $hasChild): void
    {
        if (Cursor::HASH_INDEXED === $type || Cursor::HASH_ASSOC === $type) {
            $class = 0;
        }
        parent::enterHash($cursor, $type, $class, $hasChild);
    }

    protected function dumpKey(Cursor $cursor): void
    {
        if (Cursor::HASH_INDEXED !== $cursor->hashType) {
            parent::dumpKey($cursor);
        } elseif (null !== $cursor->hashKey && $cursor->hardRefTo) {
            $this->line .= $this->style('ref', '&'.($cursor->hardRefCount ? $cursor->hardRefTo : ''), ['count' => $cursor->hardRefCount]).' ';
        }
    }
}
