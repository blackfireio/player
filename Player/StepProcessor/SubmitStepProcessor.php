<?php

/*
 * This file is part of the Blackfire Player package.
 *
 * (c) Fabien Potencier <fabien@blackfire.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Blackfire\Player\StepProcessor;

use Blackfire\Player\Exception\CrawlException;
use Blackfire\Player\Exception\LogicException;
use Blackfire\Player\ExpressionLanguage\UploadFile;
use Blackfire\Player\Http\Request;
use Blackfire\Player\ScenarioContext;
use Blackfire\Player\Step\AbstractStep;
use Blackfire\Player\Step\RequestStep;
use Blackfire\Player\Step\StepContext;
use Blackfire\Player\Step\SubmitStep;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

/**
 * @internal
 */
class SubmitStepProcessor implements StepProcessorInterface
{
    public function __construct(
        private readonly ExpressionEvaluator $expressionEvaluator,
        private readonly UriResolver $uriResolver,
    ) {
    }

    public function supports(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): bool
    {
        return $step instanceof SubmitStep;
    }

    /**
     * @param SubmitStep $step
     */
    public function process(AbstractStep $step, StepContext $stepContext, ScenarioContext $scenarioContext): iterable
    {
        if (!$this->supports($step, $stepContext, $scenarioContext)) {
            throw new \LogicException(\sprintf('Cannot handle steps of type "%s".', get_debug_type($step)));
        }

        if (!$scenarioContext->hasPreviousResponse()) {
            throw new CrawlException('Cannot submit a form without a previous request.');
        }

        $selector = $step->getSelector();
        $form = $this->expressionEvaluator->evaluateExpression($selector, $stepContext, $scenarioContext);

        if (!\count($form)) {
            throw new CrawlException(\sprintf('Unable to submit form as button "%s" does not exist.', $selector));
        }

        /** @var string[][] $headers */
        $headers = [];

        $form = $form->form();
        if (null === $body = $step->getBody()) {
            $formValues = $this->expressionEvaluator->evaluateValues($step->getParameters(), $stepContext, $scenarioContext);
            $headers = $this->expressionEvaluator->evaluateHeaders($stepContext, $scenarioContext);
            $form->setValues($formValues);

            if ($files = $form->getFiles()) {
                $values = $form->getValues();
                foreach ($files as $name => $formData) {
                    if (isset($formValues[$name])) {
                        $formValue = $formValues[$name];
                        if (!$formValue instanceof UploadFile) {
                            throw new LogicException(\sprintf('The form field "%s" is of type "file" but you did not use the "file()" function.', $name));
                        }
                        $values[$name] = DataPart::fromPath($formValue->getFilename(), $formValue->getName());
                    }
                }

                $formData = new FormDataPart($values);
                $formHeaders = $formData->getPreparedHeaders()->all();
                foreach ($formHeaders as $formHeader) {
                    $headers[strtolower($formHeader->getName())] = [$formHeader->getBodyAsString()];
                }

                $body = static fn () => yield from $formData->bodyToIterable();
            } else {
                if ($this->expressionEvaluator->evaluateExpression($stepContext->isJson(), $stepContext, $scenarioContext)) {
                    $headers['content-type'] = [Request::CONTENT_TYPE_JSON];
                } else {
                    $headers['content-type'] = [Request::CONTENT_TYPE_FORM];
                }
                $body = $form->getValues();
            }
        } else {
            $headers['content-type'] = [Request::CONTENT_TYPE_RAW];
            $body = $this->expressionEvaluator->evaluateExpression($body, $stepContext, $scenarioContext);
        }

        yield new RequestStep(
            new Request(
                $form->getMethod(),
                $this->uriResolver->resolveUri($this->expressionEvaluator->evaluateExpression($stepContext->getEndpoint(), $stepContext, $scenarioContext), $form->getUri()),
                $headers,
                $body
            ),
            $step,
        );
    }
}
