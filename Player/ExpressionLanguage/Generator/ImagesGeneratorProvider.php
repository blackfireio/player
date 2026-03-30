<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\ExpressionLanguage\Generator;

use Faker\Provider\Base;

/**
 * Provider for the Faker generator.
 *
 * This file is a copy of a dependency maltyxx/images-generator that has been removed
 *
 * @author maltyxx
 */
class ImagesGeneratorProvider extends Base
{
    /**
     * Generate a new image to disk and return its location
     * Requires gd (default in most PHP setup).
     *
     * @param string|null $dir             Path of the generated file, if null will use the system temp dir
     * @param int         $width           Width of the picture in pixels
     * @param int         $height          Height of the picture in pixels
     * @param string      $format          Image format, jpg or png. Default as png
     * @param bool        $fullPath        Return full pathfile if true
     * @param string|null $text            text to generate on the picture, default no text, if true given will output width and height
     * @param string|null $backgroundColor Background color in hexadecimal format (eg. #7f7f7f), default to black
     * @param string|null $textColor       Text color in hexadecimal format, default to white
     *
     * @example '/path/to/dir/13b73edae8443990be1aa8f1a483bc27.jpg'
     */
    public static function imageGenerator(
        string|null $dir = null,
        int $width = 640,
        int $height = 480,
        string $format = 'png',
        bool $fullPath = true,
        string|null $text = null,
        string|null $backgroundColor = null,
        string|null $textColor = null,
    ): false|string {
        $dir ??= sys_get_temp_dir(); // GNU/Linux / OS X / Windows compatible
        // Validate directory path
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new \InvalidArgumentException(\sprintf('Cannot write to directory "%s"', $dir));
        }

        // Generate a random filename. Use the server address so that a file
        // generated at the same time on a different server won't have a collision.
        $name = md5(uniqid(empty($_SERVER['SERVER_ADDR']) ? '' : $_SERVER['SERVER_ADDR'], true));
        $filename = $name.'.'.$format;
        $filepath = $dir.\DIRECTORY_SEPARATOR.$filename;

        if (\function_exists('imagecreate')) {
            $image = imagecreate($width, $height);
            if ($backgroundColor) {
                if (str_starts_with($backgroundColor, '#')) {
                    $rgb = str_split(substr($backgroundColor, 1), 2);
                } else {
                    $rgb = str_split($backgroundColor, 2);
                }
                imagecolorallocate($image, hexdec($rgb[0]), hexdec($rgb[1]), hexdec($rgb[2]));
            } else {
                imagecolorallocate($image, 0, 0, 0);
            }

            if (true === $text) {
                $text = $width.'x'.$height;
            }

            if (null !== $text) {
                if ($textColor) {
                    $rgb = str_starts_with($textColor, '#') ? str_split(substr($textColor, 1), 2) : str_split($textColor, 2);
                    $text_color = imagecolorallocate($image, hexdec($rgb[0]), hexdec($rgb[1]), hexdec($rgb[2]));
                } else {
                    $text_color = imagecolorallocate($image, 255, 255, 255);
                }

                $fontSize = 200;
                $textBoundingBox = imagettfbbox($fontSize, 0, __DIR__.'/font/Roboto-Regular.ttf', $text);
                // decrease the default font size until it fits nicely within the image - Code adapted from https://github.com/img-src/placeholder
                while (((($width - ($textBoundingBox[2] - $textBoundingBox[0])) < 10) || (($height - ($textBoundingBox[1] - $textBoundingBox[7])) < 10)) && ($fontSize > 1)) {
                    --$fontSize;
                    $textBoundingBox = imagettfbbox($fontSize, 0, __DIR__.'/font/Roboto-Regular.ttf', $text);
                }

                imagettftext(
                    $image,
                    $fontSize,
                    0,
                    (int) (($width / 2) - (($textBoundingBox[2] - $textBoundingBox[0]) / 2)),
                    (int) (($height / 2) + (($textBoundingBox[1] - $textBoundingBox[7]) / 2)),
                    $text_color,
                    __DIR__.'/font/Roboto-Regular.ttf',
                    $text
                );
            }

            switch (strtolower($format)) {
                case 'jpg':
                case 'jpeg':
                default:
                    $success = imagejpeg($image, $filepath);
                    break;
                case 'png':
                    $success = imagepng($image, $filepath);
            }

            if (!$success) {
                // could not create the file - fail silently.
                return false;
            }

            $success = imagedestroy($image);
        } else {
            throw new \RuntimeException('GD is not available on this PHP installation. Impossible to generate image.');
        }

        if (!$success) {
            // could not save the file - fail silently.
            return false;
        }

        return $fullPath ? $filepath : $filename;
    }
}
