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

use Blackfire\Player\Exception\ExpressionSyntaxErrorException;
use Blackfire\Player\Exception\InvalidArgumentException;
use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\Exception\SyntaxErrorException;
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
        } catch (SyntaxErrorException $e) {
            return $this->handleError($e);
        } catch (ExpressionSyntaxErrorException $e) {
            return $this->handleError($e);
        } catch (InvalidArgumentException $e) {
            return $this->handleError($e);
        } catch (LogicException $e) {
            return $this->handleError($e);
        }

        return new ValidationResult();
    }

    private function handleError(\Throwable $e)
    {
        return new ValidationResult(false, [$e->getMessage()]);
    }
}
