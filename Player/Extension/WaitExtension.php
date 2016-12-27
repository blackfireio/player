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
use Blackfire\Player\Exception\InvalidArgumentException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\Psr7\ExpressionSyntaxError;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\ConfigurableStep;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class WaitExtension extends AbstractExtension
{
    private $language;

    public function __construct(ExpressionLanguage $language)
    {
        $this->language = $language;
    }

    public function leaveStep(AbstractStep $step, RequestInterface $request, ResponseInterface $response, Context $context)
    {
        if (!$step instanceof ConfigurableStep) {
            return $response;
        }

        if (!$wait = $context->getStepContext()->getWait()) {
            return $response;
        }

        try {
            usleep(1000 * $this->language->evaluate($wait, $context->getVariableValues(true)));
        } catch (ExpressionSyntaxError $e) {
            throw new InvalidArgumentException(sprintf('Wait syntax error in "%s": %s', $wait, $e->getMessage()));
        }

        return $response;
    }
}
