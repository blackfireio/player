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
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Fabien Potencier <fabien@blackfire.io>
 *
 * @internal
 */
final class BlackfireExtension extends AbstractExtension
{
    private $language;
    private $defaultEnv;
    private $output;
    private $blackfire;

    public function __construct(ExpressionLanguage $language, $defaultEnv, OutputInterface $output, BlackfireClient $blackfire = null)
    {
        $this->language = $language;
        $this->defaultEnv = $defaultEnv;
        $this->output = $output;
        $this->blackfire = $blackfire ?: new BlackfireClient(new BlackfireClientConfiguration());
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
        if (true === $env) {
            if (null === $this->defaultEnv) {
                throw new \LogicException('--blackfire-env option must be set when using "blackfire: true" in a scenario.');
            }

            $env = $this->defaultEnv;
        }

        $this->setEnv($env);
        if ($request->hasHeader('X-Blackfire-Query')) {
            return $request;
        }

        // Warmup the endpoint before profiling
        if ($this->shouldBeWarmup($step, $request, $context)) {
            $step->next($this->createWarmupSteps($step));

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

        // Profile needs more samples
        if ($this->continueSampling($response)) {
            return $response;
        }

        // Request is over. Read the profile
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

        if (!$this->continueSampling($response)) {
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

        $this->output->writeln(sprintf('Blackfire Report at <comment>%s</>', $build->getUrl()));
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

    private function continueSampling(ResponseInterface $response)
    {
        parse_str($response->getHeaderLine('X-Blackfire-Response'), $values);

        return isset($values['continue']) && 'true' === $values['continue'];
    }

    private function shouldBeWarmup(ConfigurableStep $step, RequestInterface $request, Context $context)
    {
        $value = $this->language->evaluate($context->getStepContext()->getWarmup(), $context->getVariableValues(true));
        if (is_bool($value)) {
            return $value;
        }

        if ('auto' !== $value) {
            return false;
        }

        $samples = (int) $this->language->evaluate($context->getStepContext()->getSamples(), $context->getVariableValues(true));

        return in_array($request->getMethod(), ['GET', 'HEAD']) || $samples > 1;
    }

    private function createWarmupSteps(ConfigurableStep $step)
    {
        $name = null;
        if ($step->getName()) {
            $name = $this->language->evaluate($step->getName());
        }

        $nextStep = $step->getNext();
        for ($i = 0; $i < 3; ++$i) {
            $reload = (new ReloadStep())
                ->warmup('false')
            ;

            if (0 === $i) {
                // The real request to profile
                $reload
                    ->name($name ? sprintf('"%s"', $name) : null)
                    ->configureFromStep($step)
                ;
            } else {
                // Warmup requests
                $reload
                    ->name($name ? sprintf('"[Warmup] %s"', $name) : null)
                    ->blackfire('false')
                ;
            }

            if (null !== $nextStep) {
                $reload->next($nextStep);
            }

            $nextStep = $reload;
        }

        // Update the step for the current request
        // We don't want assertions or expectations on this step as it is the first warmup request
        $step->name($name ? sprintf('"[Warmup] %s"', $name) : null);
        if ($step instanceof Step) {
            $step
                ->resetAssertions()
                ->resetExpectations()
            ;
        }

        return $nextStep;
    }
}
