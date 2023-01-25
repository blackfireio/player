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

use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
class BlockStep extends ConfigurableStep
{
    #[Ignore]
    private ?AbstractStep $blockStep = null;
    private array $variables = [];
    #[Ignore]
    private ?string $endpoint = null;

    public function setBlockStep(AbstractStep $blockStep)
    {
        $this->blockStep = $blockStep;
    }

    public function __clone()
    {
        if ($this->blockStep) {
            $this->blockStep = clone $this->blockStep;
        }
    }

    public function __toString()
    {
        $str = sprintf("└ %s%s\n", static::class, $this->getName() ? sprintf(' %s', $this->getName()) : '');
        $str .= $this->blockToString($this->blockStep);

        return $str;
    }

    #[SerializedName('steps')]
    public function getBlockStep()
    {
        return $this->blockStep;
    }

    public function getSteps()
    {
        if (!$this->getBlockStep()) {
            return;
        }

        $next = $this->getBlockStep();

        do {
            yield $next;
        } while ($next = $next->getNext());
    }

    public function endpoint(?string $endpoint)
    {
        $this->endpoint = $endpoint;
        $this->set('endpoint', $endpoint);

        return $this;
    }

    public function has($key)
    {
        return \array_key_exists($key, $this->variables);
    }

    public function set($key, $value)
    {
        $this->variables[$key] = $value;

        return $this;
    }

    public function getEndpoint()
    {
        return $this->endpoint;
    }

    public function getVariables()
    {
        return $this->variables;
    }

    protected function blockToString(AbstractStep $step)
    {
        $str = '';
        $pipe = null !== $this->getNext();
        $next = $step;
        do {
            $lines = array_filter(explode("\n", (string) $next));
            foreach ($lines as $line) {
                $str .= sprintf("%s %s\n", $pipe ? '|' : ' ', $line);
            }
        } while ($next = $next->getNext());

        return $str;
    }
}
