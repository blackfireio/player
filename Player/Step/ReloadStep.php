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

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class ReloadStep extends Step implements StepInitiatorInterface
{
    use StepInitiatorTrait;

    public function __construct(string|null $file = null, int|null $line = null, Step|null $initiator = null)
    {
        $this->setInitiator($initiator);
        parent::__construct($file, $line);
    }

    public function configureFromStep(AbstractStep $step): void
    {
        if ($step instanceof ConfigurableStep) {
            $this
                ->blackfire($step->getBlackfire())
                ->wait($step->getWait())
                ->json($step->isJson())
                ->followRedirects($step->isFollowingRedirects())
            ;

            foreach ($step->getHeaders() as $header) {
                $this->header($header);
            }
        }

        if ($step instanceof Step) {
            $this->setDumpValuesName($step->getDumpValuesName());

            $this->resetAssertions();
            foreach ($step->getAssertions() as $assertion) {
                $this->assert($assertion);
            }

            $this->resetExpectations();
            foreach ($step->getExpectations() as $expectation) {
                $this->expect($expectation);
            }

            foreach ($step->getVariables() as $name => $variable) {
                $this->set($name, $variable);
            }
        }
    }
}
