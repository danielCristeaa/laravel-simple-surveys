<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\SurveyAppearance;

final class SurveySerializer
{
    /**
     * @return array{
     *     key: string,
     *     title: string,
     *     banner_text: string,
     *     description: string|null,
     *     submit_label: string,
     *     thank_you_message: string,
     *     dismissible: bool,
     *     reprompt_after_dismiss: bool,
     *     appearance: array{
     *         position: string,
     *         theme: string,
     *         styles: array<string, string>
     *     },
     *     urls: array{impression: string, open: string, submit: string, dismiss: string},
     *     questions: array<int, array{
     *         key: string,
     *         type: string,
     *         label: string,
     *         help_text: string|null,
     *         placeholder: string|null,
     *         required: bool,
     *         options: array<string, string>,
     *         min: int|float|null,
     *         max: int|float|null,
     *         step: int|float|null,
     *         max_length: int|null
     *     }>
     * }
     */
    public function toArray(Survey $survey, ?SurveyAppearance $appearance = null): array
    {
        $questions = [];

        foreach ($survey->getQuestions() as $question) {
            $questions[] = $this->serializeQuestion($question);
        }

        return [
            'key' => $survey->key(),
            'title' => $survey->getTitle(),
            'banner_text' => $survey->getBannerText(),
            'description' => $survey->getDescription(),
            'submit_label' => $survey->getSubmitLabel(),
            'thank_you_message' => $survey->getThankYouMessage(),
            'dismissible' => $survey->isDismissible(),
            'reprompt_after_dismiss' => $survey->getRepromptAfter() !== null,
            'appearance' => ($appearance ?? new SurveyAppearance)->toArray(),
            'urls' => [
                'impression' => route('simple-surveys.impression', $survey->key()),
                'open' => route('simple-surveys.open', $survey->key()),
                'submit' => route('simple-surveys.responses.store', $survey->key()),
                'dismiss' => route('simple-surveys.dismiss', $survey->key()),
            ],
            'questions' => $questions,
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     type: string,
     *     label: string,
     *     help_text: string|null,
     *     placeholder: string|null,
     *     required: bool,
     *     options: array<string, string>,
     *     min: int|float|null,
     *     max: int|float|null,
     *     step: int|float|null,
     *     max_length: int|null
     * }
     */
    private function serializeQuestion(Question $question): array
    {
        return [
            'key' => $question->key(),
            'type' => $question->type()->value,
            'label' => $question->label(),
            'help_text' => $question->getHelpText(),
            'placeholder' => $question->getPlaceholder(),
            'required' => $question->isRequired(),
            'options' => $question->getOptions(),
            'min' => $question->getMin(),
            'max' => $question->getMax(),
            'step' => $question->getStep(),
            'max_length' => $question->getMaxLength(),
        ];
    }
}
