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

use Blackfire\Player\Psr7\CrawlerFactory;
use Blackfire\Player\Step\StepContext;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class Context
{
    private ValueBag $valueBag;
    private ValueBag $extraBag;
    private ?\Generator $generator = null;
    private $contextStack;
    private $response;
    private ?Crawler $crawler = null;
    private $requestStats;
    private $resolvedIp;

    public function __construct(
        private readonly ?string $name,
        private readonly ?ValueBag $scenarioSetBag = null,
    ) {
        $this->valueBag = new ValueBag();
        $this->extraBag = new ValueBag();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getValueBag(): ValueBag
    {
        return $this->valueBag;
    }

    public function getExtraBag(): ValueBag
    {
        return $this->extraBag;
    }

    public function getStepContext(): StepContext
    {
        if (!$this->contextStack || $this->contextStack->isEmpty()) {
            throw new \RuntimeException('The context stack is not defined yet.');
        }

        return $this->contextStack->top();
    }

    public function setRequestResponse(RequestInterface $request, ResponseInterface $response): void
    {
        $this->response = $response;

        $this->crawler = null;
        if (null !== $request && null !== $response) {
            $this->crawler = CrawlerFactory::create($response, $request->getUri());
        }
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setRequestStats($requestStats): void
    {
        $this->requestStats = $requestStats;
    }

    public function getRequestStats()
    {
        return $this->requestStats;
    }

    public function getScenarioSetBag(): ?ValueBag
    {
        return $this->scenarioSetBag;
    }

    public function getResolvedIp()
    {
        return $this->resolvedIp;
    }

    public function setResolvedIp($resolvedIp): void
    {
        $this->resolvedIp = $resolvedIp;
    }

    /**
     * @internal
     */
    public function setGenerator(\Generator $generator): void
    {
        $this->generator = $generator;
    }

    /**
     * @internal
     */
    public function setContextStack(\SplStack $contextStack): void
    {
        $this->contextStack = $contextStack;
    }

    /**
     * @internal
     */
    public function getGenerator(): \Generator
    {
        return $this->generator;
    }

    /**
     * @internal
     */
    public function getVariableValues($trim = false): array
    {
        $values = $this->valueBag->all($trim);

        foreach ($this->getStepContext()->getVariables() as $key => $value) {
            if (!\array_key_exists($key, $values)) {
                $values[$key] = $trim && \is_string($value) ? trim($value) : $value;
            }
        }

        $values['_crawler'] = $this->crawler;
        $values['_response'] = $this->response;
        $values['_extra'] = $this->getExtraBag();
        $values['_working_dir'] = $this->getStepContext()->getWorkingDir();

        return $values;
    }
}
