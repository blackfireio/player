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
use Blackfire\Player\Scenario;
use Blackfire\Player\ScenarioSet;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Results;
use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class TracerExtension extends AbstractExtension
{
    private $dir;
    private $stream;
    private $currentDir;
    private $scenarioIndex;
    private $stepCount;

    public function __construct($dir, $stream = null)
    {
        $this->dir = rtrim($dir, '/\\');
        $this->stream = $stream ?: STDOUT;
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
        $this->createDir(dirname($target));

        file_put_contents($target, (string) $scenario);
    }

    public function enterStep(AbstractStep $step, RequestInterface $request, Context $context)
    {
        ++$this->stepCount;

        $target = sprintf('%s/%d', $this->currentDir, $this->stepCount);
        $this->createDir($target);

        file_put_contents($target.'/request.txt', Psr7\str($request));
        file_put_contents($target.'/step.txt', (string) $step);

        return $request;
    }

    public function leaveStep(AbstractStep $step, RequestInterface $request, ResponseInterface $response, Context $context)
    {
        $target = sprintf('%s/%d/response.txt', $this->currentDir, $this->stepCount);
        $this->createDir(dirname($target));

        file_put_contents($target, Psr7\str($response));

        return $response;
    }

    public function abortStep(AbstractStep $step, RequestInterface $request, \Exception $exception, Context $context)
    {
        fwrite($this->stream, sprintf("\033[44;37m \033[49;39m Traces under \033[43;30m %s/%d \033[49;39m\n", $this->currentDir, $this->stepCount));
    }

    public function leaveScenarioSet(ScenarioSet $scenarios, Results $results)
    {
        if (!$results->isErrored()) {
            $fs = new Filesystem();
            $fs->remove($this->dir);
        }
    }

    private function createDir($dir)
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }
}
