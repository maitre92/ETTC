<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Question du test de niveau en ligne (demande du centre, 2026-11-23) : QCM (grammaire, vocabulaire, compréhension —
 * corrigé automatiquement) ou sujet d'expression orale (speaking — le candidat s'enregistre, un formateur écoute).
 */
#[Fillable(['section', 'difficulty', 'question', 'choices', 'answer', 'is_active'])]
class TestQuestion extends Model
{
    use LogsActivity, SoftDeletes;

    public const SECTIONS = ['grammaire' => 'Grammaire', 'vocabulaire' => 'Vocabulaire', 'comprehension' => 'Compréhension', 'speaking' => 'Expression orale (speaking)'];

    public const DIFFICULTIES = [1 => 'Facile', 2 => 'Moyen', 3 => 'Difficile'];

    protected function casts(): array
    {
        return ['choices' => 'array', 'answer' => 'integer', 'difficulty' => 'integer', 'is_active' => 'boolean'];
    }

    public function activityNoun(): string
    {
        return 'Question du test';
    }

    public function activityLabel(): string
    {
        return Str::limit((string) $this->question, 60);
    }

    public function isSpeaking(): bool
    {
        return $this->section === 'speaking';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Réponses, une par ligne, « * » devant la bonne (formulaire). */
    public function getChoicesTextAttribute(): string
    {
        return collect($this->choices ?? [])->map(fn ($c, $i) => ($i === $this->answer ? '*' : '').$c)->implode("\n");
    }

    /** Lit « une réponse par ligne, * devant la bonne » : [réponses, index de la bonne]. @return array{0: list<string>, 1: ?int} */
    public static function parseChoices(?string $text): array
    {
        $choices = [];
        $answer = null;
        foreach (preg_split('/\R/', (string) $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '*')) {
                $answer = count($choices);
                $line = trim(substr($line, 1));
            }
            $choices[] = $line;
        }

        return [$choices, $answer];
    }
}
