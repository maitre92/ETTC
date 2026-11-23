<?php

namespace App\Services\Bulk\Definitions;

use App\Models\TestQuestion;
use App\Models\User;
use App\Services\Bulk\BulkDefinition;
use Illuminate\Database\Eloquent\Model;

/** Questionnaire importé d'un fichier (Excel, CSV) ou saisi en grille : une question par ligne. */
class TestQuestionBulk extends BulkDefinition
{
    public function module(): string
    {
        return 'questions';
    }

    public function label(): string
    {
        return 'questions du test de niveau';
    }

    public function icon(): string
    {
        return 'bx bx-list-check';
    }

    public function indexRoute(): string
    {
        return 'questions.index';
    }

    public function permission(): string
    {
        return 'tests.configure';
    }

    public function columns(): array
    {
        return [
            'section' => ['label' => 'Partie', 'required' => true, 'type' => 'select', 'options' => TestQuestion::SECTIONS, 'width' => 18],
            'question' => ['label' => 'Question', 'required' => true, 'rules' => ['string', 'max:2000'], 'width' => 50],
            'a' => ['label' => 'Réponse A', 'rules' => ['nullable', 'string', 'max:300'], 'width' => 20],
            'b' => ['label' => 'Réponse B', 'rules' => ['nullable', 'string', 'max:300'], 'width' => 20],
            'c' => ['label' => 'Réponse C', 'rules' => ['nullable', 'string', 'max:300'], 'width' => 20],
            'd' => ['label' => 'Réponse D', 'rules' => ['nullable', 'string', 'max:300'], 'width' => 20],
            'answer' => ['label' => 'Bonne réponse', 'type' => 'select', 'options' => ['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D'], 'width' => 12],
            'difficulty' => ['label' => 'Difficulté', 'type' => 'select', 'options' => ['1' => 'Facile', '2' => 'Moyen', '3' => 'Difficile'], 'width' => 12],
        ];
    }

    public function instructions(): array
    {
        return [
            'Une question par ligne. QCM : au moins deux réponses (A, B…) et la lettre de la bonne réponse.',
            'Expression orale (speaking) : écrivez seulement le sujet dans « Question » ; le candidat s\'enregistre et un formateur écoute.',
        ];
    }

    public function extraRules(): array
    {
        return ['answer' => [function (string $attribute, mixed $value, \Closure $fail, $validator) {
            $row = $validator->getData()['rows'][explode('.', $attribute)[1]] ?? [];
            if (($row['section'] ?? '') !== 'speaking' && (! $value || blank($row[strtolower((string) $value)] ?? null))) {
                $fail('Indiquez la lettre de la bonne réponse (et remplissez cette réponse).');
            }
        }]];
    }

    public function create(array $row, User $user): Model
    {
        $choices = collect(['a', 'b', 'c', 'd'])->map(fn ($k) => trim((string) ($row[$k] ?? '')))->filter(fn ($c) => $c !== '')->values()->all();
        $speaking = $row['section'] === 'speaking';

        return TestQuestion::create([
            'section' => $row['section'], 'question' => $row['question'], 'difficulty' => (int) ($row['difficulty'] ?? 1) ?: 1,
            'choices' => $speaking ? [] : $choices, 'answer' => $speaking ? 0 : max(0, ord(strtoupper((string) $row['answer'])) - 65), 'is_active' => true,
        ]);
    }
}
