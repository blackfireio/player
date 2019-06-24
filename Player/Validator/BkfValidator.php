<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Validator;

use Blackfire\Player\Parser;

class BkfValidator
{
    public function validate($input)
    {
        return $this->doValidate($input, false);
    }

    public function validateFile($path)
    {
        return $this->doValidate($path, true);
    }

    private function doValidate($input, $isFilePath)
    {
        $parser = new Parser();

        try {
            if ($isFilePath) {
                $parser->load($input);
            } else {
                $parser->parse($input);
            }
        } catch (\Exception $e) {
            return $this->handleError($e);
        }

        return new ValidationResult();
    }

    private function handleError(\Exception $e)
    {
        return new ValidationResult(false, [$e->getMessage()]);
    }
}
