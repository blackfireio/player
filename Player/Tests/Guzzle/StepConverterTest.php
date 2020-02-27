<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\Tests\Guzzle;

use Blackfire\Player\Context;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\Guzzle\StepConverter;
use Blackfire\Player\Step\FollowStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\VisitStep;
use Blackfire\Player\ValueBag;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class StepConverterTest extends TestCase
{
    public function testFollowStepResetBlackfireQuery()
    {
        $stepConverter = new StepConverter(new ExpressionLanguage(), $this->createContext(new VisitStep('/admin')));

        $step = new FollowStep();

        $request = new Request('GET', '/admin');
        $request = $request
            ->withHeader('X-Blackfire-Query', 'foo')
            ->withHeader('X-Blackfire-Profile-Uuid', 'bar')
        ;

        $response = new Response(301, ['location' => '/login']);

        $nextRequest = $stepConverter->createRequest($step, $request, $response);

        $this->assertEquals('', $nextRequest->getHeaderLine('X-Blackfire-Query'));
        $this->assertEquals('', $nextRequest->getHeaderLine('X-Blackfire-Profile-Uuid'));
    }

    protected function createContext($step)
    {
        $stepContext = new StepContext();
        $stepContext->update($step, []);

        $contextStack = new \SplStack();
        $contextStack->push($stepContext);

        $context = new Context('"Context name"', new ValueBag());
        $context->setContextStack($contextStack);

        return $context;
    }
}
