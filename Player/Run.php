<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class Run
{
    private $requestIterator;
    private $scenario;
    private $context;
    private $clientId;

    public function __construct(\Iterator $requestIterator, Scenario $scenario, Context $context)
    {
        $this->requestIterator = $requestIterator;
        $this->scenario = $scenario;
        $this->context = $context;
    }

    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    public function getIterator()
    {
        return $this->requestIterator;
    }

    public function getScenario()
    {
        return $this->scenario;
    }

    public function getContext()
    {
        return $this->context;
    }
}
