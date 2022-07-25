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

use Faker\Provider\Image;

/**
 * Workaround for http://lorempixel.com/ unavailability.
 * This is the service used by the native Image Provider.
 */
class SafeFakerImageProvider extends Image
{
    public static function imageUrl($width = 640, $height = 480, $category = null, $randomize = true, $word = null, $gray = false, $format = 'png')
    {
        // Should be an image, but whatever...
        return 'https://blackfire.io/api/v1/';
    }
}
