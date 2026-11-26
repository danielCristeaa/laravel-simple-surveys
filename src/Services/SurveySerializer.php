<?php

declare(strict_types=1);

namespace DanielCristea\SimpleSurveys\Services;

use DanielCristea\SimpleSurveys\Question;
use DanielCristea\SimpleSurveys\QuestionCondition;
use DanielCristea\SimpleSurveys\Survey;
use DanielCristea\SimpleSurveys\SurveyAppearance;
use DanielCristea\SimpleSurveys\WidgetIcon;

final class SurveySerializer
{
    public function __construct(
        private readonly SurveyTranslator $translator,
    ) {}

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
     *         icon: array{type: 'preset'|'asset'|'none', preset: string|null, url: string|null},
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
     *         max_length: int|null,
     *         conditions: array<int, array{question: string, operator: string, value: string|int|float|bool|array<int, string|int|float|bool>}>
     *     }>
     * }
     */
    public function toArray(Survey $survey, ?SurveyAppearance $appearance = null, ?string $locale = null): array
    {
        $questions = [];

        foreach ($survey->getQuestions() as $question) {
            $questions[] = $this->serializeQuestion($survey, $question, $locale);
        }

        $fallbackIcon = $survey->getIcon() ?? WidgetIcon::fromConfig();

        return [
            'key' => $survey->key(),
            'title' => $this->translator->title($survey, $locale),
            'banner_text' => $this->translator->bannerText($survey, $locale),
            'description' => $this->translator->description($survey, $locale),
            'submit_label' => $this->translator->submitLabel($survey, $locale),
            'thank_you_message' => $this->translator->thankYouMessage($survey, $locale),
            'dismissible' => $survey->isDismissible(),
            'reprompt_after_dismiss' => $survey->getRepromptAfter() !== null,
            'appearance' => ($appearance ?? new SurveyAppearance)->toArray(defaultIcon: $fallbackIcon),
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
     *     max_length: int|null,
     *     conditions: array<int, array{question: string, operator: string, value: string|int|float|bool|array<int, string|int|float|bool>}>
     * }
     */
    private function serializeQuestion(Survey $survey, Question $question, ?string $locale = null): array
    {
        return [
            'key' => $question->key(),
            'type' => $question->type()->value,
            'label' => $this->translator->questionLabel($survey, $question, $locale),
            'help_text' => $this->translator->questionHelpText($survey, $question, $locale),
            'placeholder' => $this->translator->questionPlaceholder($survey, $question, $locale),
            'required' => $question->isRequired(),
            'options' => $this->translator->questionOptions($survey, $question, $locale),
            'min' => $question->getMin(),
            'max' => $question->getMax(),
            'step' => $question->getStep(),
            'max_length' => $question->getMaxLength(),
            'conditions' => array_map(
                static fn (QuestionCondition $condition): array => $condition->toArray(),
                $question->getConditions(),
            ),
        ];
    }
}
