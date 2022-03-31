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

/**
 * @internal
 */
class BkfValidator
{
    public function validate($input, array $variables = [], $allowMisingVariables = false)
    {
        return $this->doValidate($input, false, $variables, $allowMisingVariables);
    }

    public function validateFile($path, array $variables = [], $allowMisingVariables = false)
    {
        return $this->doValidate($path, true, $variables, $allowMisingVariables);
    }

    private function doValidate($input, $isFilePath, array $variables = [], $allowMisingVariables = false)
    {
        $parser = new Parser($variables, $allowMisingVariables);

        try {
            if ($isFilePath) {
                $parser->load($input);
            } else {
                $parser->parse($input);
            }
        } catch (\Exception $e) {
            return $this->handleError($e);
        }

        $result = new ValidationResult();
        $result->setMissingVariables($parser->getMissingVariables());

        return $result;
    }

    private function handleError(\Exception $e)
    {
        return new ValidationResult(false, [$e->getMessage()]);
    }
}
