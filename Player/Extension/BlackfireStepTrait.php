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

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 */
trait BlackfireStepTrait
{
    private $assertions = [];
    private $samples = 1;
    private $blackfire = null;
    private $isConfigured = false;

    public function samples($samples)
    {
        $this->samples = $samples;
        $this->isConfigured = true;

        return $this;
    }

    public function assert($assertion)
    {
        $this->assertions[] = $assertion;
        $this->isConfigured = true;

        return $this;
    }

    /**
     * @param bool|null $enabled null for auto-configuration
     */
    public function blackfire($enabled = true)
    {
        $this->blackfire = null === $enabled ? null : (bool) $enabled;

        return $this;
    }

    /**
     * @return bool
     */
    public function isBlackfireEnabled()
    {
        if (null !== $this->blackfire) {
            return $this->blackfire;
        }

        // auto-enabled only for the last step
        // or when blackfire configuration is set
        return !$this->getNext() || $this->isConfigured;
    }

    public function getAssertions()
    {
        return $this->assertions;
    }

    public function getSamples()
    {
        return $this->samples;
    }
}
