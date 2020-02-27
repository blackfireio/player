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
    public function validate($input, array $variables = [])
    {
        return $this->doValidate($input, false, $variables);
    }

    public function validateFile($path, array $variables = [])
    {
        return $this->doValidate($path, true, $variables);
    }

    private function doValidate($input, $isFilePath, array $variables = [])
    {
        $parser = new Parser($variables);

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
