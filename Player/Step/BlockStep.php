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

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class BlockStep extends ConfigurableStep
{
    #[Ignore]
    private AbstractStep|null $blockStep = null;
    /** @var string[] */
    private array $variables = [];
    #[Ignore]
    private string|null $endpoint = null;

    public function setBlockStep(AbstractStep $blockStep): void
    {
        $this->blockStep = $blockStep;
    }

    public function __clone()
    {
        parent::__clone();

        if ($this->blockStep) {
            $this->blockStep = clone $this->blockStep;
        }
    }

    public function __toString()
    {
        $str = \sprintf("└ %s%s\n", static::class, $this->getName() ? \sprintf(' %s', $this->getName()) : '');
        $str .= $this->blockToString($this->blockStep);

        return $str;
    }

    #[SerializedName('steps')]
    public function getBlockStep(): AbstractStep|null
    {
        return $this->blockStep;
    }

    public function getSteps(): iterable
    {
        if (!$this->getBlockStep()) {
            return;
        }

        $next = $this->getBlockStep();

        do {
            yield $next;
        } while ($next = $next->getNext());
    }

    public function endpoint(string $endpoint): self
    {
        $this->endpoint = $endpoint;
        $this->set('endpoint', $endpoint);

        return $this;
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->variables);
    }

    public function set(string $key, string $value): self
    {
        $this->variables[$key] = $value;

        return $this;
    }

    public function getEndpoint(): string|null
    {
        return $this->endpoint;
    }

    /**
     * @return string[]
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    protected function blockToString(AbstractStep|null $step): string
    {
        if (!$step) {
            return '';
        }
        $str = '';
        $pipe = null !== $this->getNext();
        $next = $step;
        do {
            $lines = array_filter(explode("\n", (string) $next));
            foreach ($lines as $line) {
                $str .= \sprintf("%s %s\n", $pipe ? '|' : ' ', $line);
            }
        } while ($next = $next->getNext());

        return $str;
    }
}
