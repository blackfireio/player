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

use Blackfire\Player\Adapter\BlackfireSdkAdapterInterface;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\RequestStep;
use Blackfire\Player\Step\StepContext;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
readonly class BlackfireReportExtension implements StepExtensionInterface, ExceptionExtensionInterface
{
    private bool $debug;

    public function __construct(
        private BlackfireSdkAdapterInterface $blackfire,
        private OutputInterface $output,
    ) {
        $this->debug = $output->isVerbose();
    }

    public function failStep(AbstractStep $step, \Throwable $exception): void
    {
        if (null === $uuid = $step->getBlackfireProfileUuid()) {
            if (!$step instanceof RequestStep) {
                return;
            }
            if (null === $uuid = $step->getInitiator()?->getBlackfireProfileUuid()) {
                return;
            }
        }

        $this->displayProfileReport($uuid);
    }

    public function beforeStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
    }

    public function afterStep(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): void
    {
        if (null === $uuid = $step->getBlackfireProfileUuid()) {
            return;
        }

        $this->displayProfileReport($uuid);
    }

    private function displayProfileReport(string $uuid): void
    {
        $profile = $this->blackfire->getProfile($uuid);

        $this->output->writeln(\sprintf('<bg=cyan> </>   └ profile %s = %s', $uuid, $profile->getUrl()));

        $this->displayProfileReportTest($profile->getTests(), 'Tests', 'red');
        $this->displayProfileReportTest($profile->getRecommendations(), 'Recommendations', 'yellow');
    }

    private function displayProfileReportTest(array $tests, string $header, string $colorFailure): void
    {
        $titleDisplayed = false;
        foreach ($tests as $test) {
            if ($test->isSuccessful() && !$this->debug) {
                continue;
            }
            if (!$titleDisplayed) {
                $this->output->writeln(\sprintf('<bg=cyan> </>       %s:', $header));
                $titleDisplayed = true;
            }
            if ($test->isSuccessful()) {
                $this->output->writeln(\sprintf('<bg=cyan> </><bg=green> </>        %s: %s', $test->getName(), $test->getState()));
            } else {
                $this->output->writeln(\sprintf('<bg=cyan> </><bg=%s> </>        %s: %s', $colorFailure, $test->getName(), $test->getState()));
            }
        }
    }
}
