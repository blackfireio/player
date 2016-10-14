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

use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Context;
use Blackfire\Player\Result;
use Blackfire\Player\Results;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
abstract class AbstractExtension implements ExtensionInterface
{
    public function enterScenarioSet(ScenarioSet $scenarios, $concurrency)
    {
    }

    public function enterScenario(Scenario $scenario, Context $context)
    {
    }

    public function enterStep(AbstractStep $step, RequestInterface $request, Context $context)
    {
        return $request;
    }

    public function leaveStep(AbstractStep $step, RequestInterface $request, ResponseInterface $response, Context $context)
    {
        return $response;
    }

    public function abortStep(AbstractStep $step, RequestInterface $request, \Exception $exception, Context $context)
    {
    }

    public function getNextStep(AbstractStep $step, RequestInterface $request, ResponseInterface $response, Context $context)
    {
    }

    public function leaveScenario(Scenario $scenario, Result $result, Context $context)
    {
    }

    public function leaveScenarioSet(ScenarioSet $scenarios, Results $results)
    {
    }
}
