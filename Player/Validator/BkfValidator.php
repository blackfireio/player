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

use Blackfire\Player\ParserFactory;

/**
 * @internal
 */
class BkfValidator
{
    public function __construct(
        private readonly ParserFactory $parserFactory,
    ) {
    }

    public function validate(string $input, array $variables = [], bool $allowMissingVariables = false): ValidationResult
    {
        return $this->doValidate($input, false, $variables, $allowMissingVariables);
    }

    public function validateFile(string $path, array $variables = [], bool $allowMissingVariables = false): ValidationResult
    {
        return $this->doValidate($path, true, $variables, $allowMissingVariables);
    }

    private function doValidate(string $input, bool $isFilePath, array $variables = [], bool $allowMissingVariables = false): ValidationResult
    {
        $parser = $this->parserFactory->createParser($variables, $allowMissingVariables);

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

    private function handleError(\Throwable $e): ValidationResult
    {
        return new ValidationResult(false, [$e->getMessage()]);
    }
}
