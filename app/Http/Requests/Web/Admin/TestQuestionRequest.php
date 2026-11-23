<?php

namespace App\Http\Requests\Web\Admin;

use App\Models\TestQuestion;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Question du test en ligne : QCM (réponses une par ligne, « * » devant la bonne) ou sujet de speaking. */
class TestQuestionRequest extends ContentRequest
{
    protected function routeKey(): string
    {
        return 'question';
    }

    protected function modelClass(): string
    {
        return TestQuestion::class;
    }

    protected function booleanFields(): array
    {
        return ['is_active'];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'section' => ['required', Rule::in(array_keys(TestQuestion::SECTIONS))],
            'difficulty' => ['required', Rule::in(array_keys(TestQuestion::DIFFICULTIES))],
            'question' => ['required', 'string', 'max:2000'],
            'choices_text' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('section') === 'speaking') {
                return;
            }
            [$choices, $answer] = TestQuestion::parseChoices($this->input('choices_text'));
            if (count($choices) < 2) {
                $validator->errors()->add('choices_text', 'Écrivez au moins deux réponses, une par ligne.');
            } elseif ($answer === null) {
                $validator->errors()->add('choices_text', 'Mettez une étoile * devant la bonne réponse.');
            }
        }];
    }

    /** Données prêtes pour le modèle. @return array<string, mixed> */
    public function questionData(): array
    {
        $data = $this->validated();
        [$choices, $answer] = $data['section'] === 'speaking' ? [[], 0] : TestQuestion::parseChoices($data['choices_text'] ?? '');
        unset($data['choices_text']);

        return $data + ['choices' => $choices, 'answer' => $answer ?? 0];
    }

    public function attributes(): array
    {
        return ['section' => 'partie', 'difficulty' => 'difficulté', 'question' => 'question', 'choices_text' => 'réponses'];
    }
}
