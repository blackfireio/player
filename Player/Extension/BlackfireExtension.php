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

use Blackfire\Build;
use Blackfire\Client as BlackfireClient;
use Blackfire\ClientConfiguration as BlackfireClientConfiguration;
use Blackfire\Player\Context;
use Blackfire\Player\Exception\ExpectationErrorException;
use Blackfire\Player\Exception\ExpectationFailureException;
use Blackfire\Player\Exception\SyntaxErrorException;
use Blackfire\Player\ExpressionLanguage\ExpressionLanguage;
use Blackfire\Player\Psr7\CrawlerFactory;
use Blackfire\Player\Result;
use Blackfire\Player\Scenario;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\ConfigurableStep;
use Blackfire\Player\Step\ReloadStep;
use Blackfire\Player\Step\Step;
use Blackfire\Profile\Configuration as ProfileConfiguration;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class BlackfireExtension extends AbstractExtension
{
    private $blackfire;
    private $language;

    public function __construct(ExpressionLanguage $language, $stream = null)
    {
        $this->language = $language;
        $this->stream = $stream ?: STDOUT;
        $this->blackfire = new BlackfireClient(new BlackfireClientConfiguration());
    }

    public function enterStep(AbstractStep $step, RequestInterface $request, Context $context)
    {
        if (!$step instanceof ConfigurableStep) {
            return $request;
        }

        $env = $context->getStepContext()->getBlackfireEnv();
        $env = null === $env ? false : $this->language->evaluate($env, $context->getVariableValues(true));
        if (false === $env) {
            return $request->withoutHeader('X-Blackfire-Query');
        }

        $this->setEnv($env);

        if ($request->hasHeader('X-Blackfire-Query')) {
            return $request;
        }

        if (!$context->getExtraBag()->has('blackfire_build')) {
            $context->getExtraBag()->set('blackfire_build', $this->createBuild($context->getName()));
        }

        $build = $context->getExtraBag()->get('blackfire_build');
        $config = $this->createProfileConfig($step, $context, $build);
        $profileRequest = $this->blackfire->createRequest($config);

        return $request
            ->withHeader('X-Blackfire-Query', $profileRequest->getToken())
            ->withHeader('X-Blackfire-Profile-Uuid', $profileRequest->getUuid())
        ;
    }

    public function leaveStep(AbstractStep $step, RequestInterface $request, ResponseInterface $response, Context $context)
    {
        if (!$uuid = $request->getHeaderLine('X-Blackfire-Profile-Uuid')) {
            return $response;
        }

        if (!$response->hasHeader('X-Blackfire-Response')) {
            throw new \LogicException('Unable to profile the current step.');
        }

        $crawler = CrawlerFactory::create($response, $request->getUri());
        if (null !== $crawler && !$step->getName()) {
            if (count($c = $crawler->filter('title'))) {
                $this->blackfire->updateProfile($uuid, $c->first()->text());
            }
        }

        $this->assertProfile($request, $response);

        return $response;
    }

    public function getNextStep(AbstractStep $step, RequestInterface $request, ResponseInterface $response, Context $context)
    {
        // if X-Blackfire-Response is set by someone else, don't do anything
        if (!$request->getHeaderLine('X-Blackfire-Profile-Uuid')) {
            return;
        }

        if (!$response->hasHeader('X-Blackfire-Response')) {
            return;
        }

        parse_str($response->getHeaderLine('X-Blackfire-Response'), $values);
        if (!isset($values['continue']) || 'true' !== $values['continue']) {
            return;
        }

        $step = new ReloadStep();
        $step->name("'Reloading for Blackfire'");

        return $step;
    }

    public function leaveScenario(Scenario $scenario, Result $result, Context $context)
    {
        $extra = $context->getExtraBag();
        if (!$extra->has('blackfire_build')) {
            return;
        }

        $build = $extra->get('blackfire_build');
        $extra->remove('blackfire_build');

        // did we profile something?
        // if not, don't finish the build as it won't work with 0 profiles
        if ($build->getJobCount()) {
            $extra->set('blackfire_report', $this->blackfire->endBuild($build));
        }

        fwrite($this->stream, sprintf("\033[44;37m \033[49;39m Blackfire Report at \033[43;30m %s \033[49;39m\n", $build->getUrl()));
    }

    private function createBuild($title)
    {
        if (!$env = $this->blackfire->getConfiguration()->getEnv()) {
            throw new SyntaxErrorException('You must set the environment you want to work with on the Blackfire client configuration.');
        }

        $options = [
            'title' => $title,
            'trigger_name' => 'Blackfire Player',
        ];

        if (isset($_SERVER['BLACKFIRE_EXTERNAL_ID'])) {
            $options['external_id'] = $_SERVER['BLACKFIRE_EXTERNAL_ID'];
        }

        if (isset($_SERVER['BLACKFIRE_EXTERNAL_PARENT_ID'])) {
            $options['external_parent_id'] = $_SERVER['BLACKFIRE_EXTERNAL_PARENT_ID'];
        }

        return $this->blackfire->createBuild($env, $options);
    }

    private function createProfileConfig(AbstractStep $step, Context $context, Build $build = null)
    {
        $config = new ProfileConfiguration();
        if (null !== $build) {
            $config->setBuild($build);
        }

        $config->setSamples($this->language->evaluate($context->getStepContext()->getSamples(), $context->getVariableValues(true)));
        $config->setTitle($step->getName());

        if ($step instanceof Step) {
            foreach ($step->getAssertions() as $assertion) {
                $config->assert($assertion);
            }
        }

        return $config;
    }

    private function assertProfile(RequestInterface $request, ResponseInterface $response)
    {
        $profile = $this->blackfire->getProfile($request->getHeaderLine('X-Blackfire-Profile-Uuid'));

        if ($profile->isErrored()) {
            throw new ExpectationErrorException('Assertion syntax error.');
        } elseif (!$profile->isSuccessful()) {
            $failures = [];
            foreach ($profile->getTests() as $test) {
                foreach ($test->getFailures() as $failure) {
                    $failures[] = $failure;
                }
            }

            throw new ExpectationFailureException(sprintf("Assertions failed:\n  %s", implode("\n  ", $failures)));
        }
    }

    private function setEnv($env)
    {
        $current = $this->blackfire->getConfiguration()->getEnv();
        if ($current && $env !== $current) {
            throw new SyntaxErrorException(sprintf('Blackfire is already configured for the "%s" environment, cannot change it to "%s".', $current, $env));
        }

        $this->blackfire->getConfiguration()->setEnv($env);
    }
}
