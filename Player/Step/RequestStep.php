<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Step;

use Blackfire\Player\Http\Request;
use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * @internal
 */
class RequestStep extends ConfigurableStep implements \Stringable, StepInitiatorInterface
{
    use StepInitiatorTrait;

    public function __construct(
        #[Ignore]
        private readonly Request $request,
        Step $initiator,
    ) {
        parent::__construct();

        $this->setInitiator($initiator);
        $this->followRedirects('false');
    }

    public function __toString(): string
    {
        return \sprintf("└ %s: %s %s\n", static::class, $this->request->method, $this->request->uri);
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
