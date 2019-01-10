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
use Blackfire\Player\Results;
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\AbstractStep;
use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class TracerExtension extends AbstractExtension
{
    private $output;
    private $dir;
    private $fs;
    private $currentDir;
    private $scenarioIndex;
    private $stepCount;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
        $this->dir = sprintf('%s/blackfire-player-trace/%s/%s', sys_get_temp_dir(), date('y-m-d-H-m-s'), mt_rand());

        $this->fs = new Filesystem();

        $this->fs->remove($this->dir);
    }

    public function enterScenarioSet(ScenarioSet $scenarios, $concurrency)
    {
        $this->stepCount = 0;
    }

    public function enterScenario(Scenario $scenario, Context $context)
    {
        if (!$key = $scenario->getKey()) {
            $key = ++$this->scenarioIndex;
        }
        $this->currentDir = $this->dir.'/'.$key;
        $target = sprintf('%s/scenario.txt', $this->currentDir);
        $this->fs->mkdir(\dirname($target));

        file_put_contents($target, (string) $scenario);
    }

    public function enterStep(AbstractStep $step, RequestInterface $request, Context $context)
    {
        ++$this->stepCount;

        $target = $this->getDirectory();

        file_put_contents($target.'/request.txt', Psr7\str($request));
        file_put_contents($target.'/step.txt', (string) $step);

        return $request;
    }

    public function leaveStep(AbstractStep $step, RequestInterface $request, ResponseInterface $response, Context $context)
    {
        $target = $this->getDirectory();

        file_put_contents($target.'/response.txt', Psr7\str($response));
        file_put_contents($target.'/variables.json', json_encode($this->getVariables($context), JSON_PRETTY_PRINT));

        return $response;
    }

    public function abortStep(AbstractStep $step, RequestInterface $request, \Exception $exception, Context $context)
    {
        $target = $this->getDirectory();
        file_put_contents($target.'/variables.json', json_encode($this->getVariables($context), JSON_PRETTY_PRINT));

        $response = $context->getResponse();
        if (!$response) {
            return;
        }

        file_put_contents($target.'/response.txt', Psr7\str($response));
    }

    public function leaveScenarioSet(ScenarioSet $scenarios, Results $results)
    {
        $this->output->writeln(sprintf('<comment>Traces under %s</>', $this->dir));
    }

    private function getVariables(Context $context)
    {
        $variables = $context->getVariableValues();
        unset($variables['_crawler']);
        unset($variables['_response']);

        return $variables;
    }

    private function getDirectory()
    {
        $target = sprintf('%s/%d', $this->currentDir, $this->stepCount);
        $this->fs->mkdir($target);

        return $target;
    }
}
