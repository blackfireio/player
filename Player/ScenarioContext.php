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

use Blackfire\Player\Http\CrawlerFactory;
use Blackfire\Player\Http\Response;
use Blackfire\Player\Step\StepContext;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @internal
 */
class ScenarioContext
{
    private Response|null $lastResponse = null;
    private Crawler|null $crawler = null;
    private ValueBag $valueBag;
    private ValueBag $extraBag;

    public function __construct(
        private readonly ?string $name,
        private ?ScenarioSet $scenarioSet,
    ) {
        $this->valueBag = new ValueBag();
        $this->extraBag = new ValueBag();
    }

    public function hasPreviousResponse(): bool
    {
        return null !== $this->lastResponse;
    }

    public function setLastResponse(Response $lastResponse): void
    {
        $this->lastResponse = $lastResponse;
        $this->crawler = CrawlerFactory::create($lastResponse, $lastResponse->request->uri);
    }

    public function getLastResponse(): Response
    {
        return $this->lastResponse ?? throw new \LogicException('There is no previous request yet.');
    }

    public function getVariableValues(StepContext $stepContext, bool $trim): array
    {
        $values = $this->valueBag->all($trim);

        foreach ($stepContext->getVariables() as $key => $value) {
            if (!\array_key_exists($key, $values)) {
                $values[$key] = $trim && \is_string($value) ? trim($value) : $value;
            }
        }

        $values['_crawler'] = $this->crawler;
        $values['_response'] = $this->lastResponse;
        $values['_extra'] = $this->extraBag;
        $values['_working_dir'] = $stepContext->getWorkingDir();

        return $values;
    }

    public function setVariableValue(string $name, mixed $value): void
    {
        $this->valueBag->set($name, $value);
    }

    public function getValues(): array
    {
        return $this->valueBag->all(false);
    }

    public function getExtraValue(string $name, mixed $default = null): mixed
    {
        if (!$this->extraBag->has($name)) {
            return $default;
        }

        return $this->extraBag->get($name);
    }

    public function setExtraValue(string $name, mixed $value): void
    {
        $this->extraBag->set($name, $value);
    }

    public function removeExtraValue(string $name): void
    {
        $this->extraBag->remove($name);
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getScenarioSet(): ScenarioSet|null
    {
        return $this->scenarioSet;
    }
}
