<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Extension;

use Blackfire\Player\Context;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\Psr7\ResponseChecker;
use Blackfire\Player\Psr7\ResponseExtractor;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\Step;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class TestsExtension extends AbstractExtension
{
    private $responseChecker;
    private $responseExtractor;

    public function __construct(ExpressionLanguage $language)
    {
        $this->responseChecker = new ResponseChecker($language);
        $this->responseExtractor = new ResponseExtractor($language);
    }

    public function leaveStep(AbstractStep $step, RequestInterface $request, ResponseInterface $response, Context $context)
    {
        if (!$step instanceof Step) {
            return $response;
        }

        $this->responseChecker->check($step->getExpectations(), $context, $request, $response);
        $this->responseExtractor->extract($step->getVariables(), $context, $request, $response);

        return $response;
    }
}
