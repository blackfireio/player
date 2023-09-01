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

use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\StepContext;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @internal
 */
class InteractiveStepByStepExtension implements StepExtensionInterface
{
    public function __construct(
        private readonly HelperInterface $helper,
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
    ) {
        if ($input->getOption('concurrency') > 1) {
            throw new \InvalidArgumentException('Cannot use the step option with concurrency mode > 1');
        }
    }

    public function beforeStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
        $question = new ConfirmationQuestion(sprintf('Going to execute %s(%s). Press [Enter] to continue...', $step->getName(), $step->getType()), true);

        $this->helper->ask($this->input, $this->output, $question);
    }

    public function afterStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
    }
}
