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
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\ConfigurableStep;
use Blackfire\Player\Step\FollowStep;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class FollowExtension extends AbstractExtension
{
    private $language;

    public function __construct(ExpressionLanguage $language)
    {
        $this->language = $language;
    }

    public function getNextStep(AbstractStep $step, RequestInterface $request, ResponseInterface $response, Context $context)
    {
        if (!$this->language->evaluate($context->getStepContext()->isFollowingRedirects(), $context->getVariableValues(true))) {
            return;
        }

        if ('3' !== substr($response->getStatusCode(), 0, 1) || !$response->hasHeader('Location')) {
            return;
        }

        $follow = new FollowStep();

        if ($step instanceof ConfigurableStep) {
            $follow->blackfire($step->getBlackfire());
        }
        $follow->followRedirects(true);
        $follow->name(sprintf("'Auto-following redirect to %s'", $response->getHeaderLine('Location')));

        return $follow;
    }
}
