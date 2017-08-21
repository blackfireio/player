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
 */
class ReloadStep extends Step
{
    public function configureFromStep(AbstractStep $step)
    {
        if ($step instanceof ConfigurableStep) {
            $this
                ->samples($step->getSamples())
                ->blackfire($step->getBlackfire())
                ->wait($step->getWait())
                ->followRedirects($step->isFollowingRedirects())
            ;

            if ($step->isJson()) {
                $this->json();
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

            foreach ($step->getVariables() as $name => $expression) {
                $this->set($name, $expression);
            }
        }
    }
}
