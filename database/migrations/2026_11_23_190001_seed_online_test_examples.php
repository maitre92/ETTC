<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Test de niveau en ligne (2026-11-23) : questions d'EXEMPLE (à relire et adapter par les formateurs) si la banque est
| vide, et scores minimaux des niveaux (répartis de 0 à 75 % dans l'ordre des niveaux) s'ils ne sont pas réglés.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('test_questions')->count() === 0) {
            $now = now();
            $rows = [];
            foreach (self::questions() as [$section, $difficulty, $question, $choices, $answer]) {
                $rows[] = ['section' => $section, 'difficulty' => $difficulty, 'question' => $question, 'choices' => json_encode($choices, JSON_UNESCAPED_UNICODE), 'answer' => $answer, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
            }
            DB::table('test_questions')->insert($rows);
        }

        $niveaux = DB::table('niveaux')->whereNull('deleted_at')->orderBy('sort_order')->orderBy('id')->get(['id', 'min_score']);
        if ($niveaux->isNotEmpty() && $niveaux->every(fn ($n) => $n->min_score === null)) {
            $count = $niveaux->count();
            foreach ($niveaux->values() as $i => $niveau) {
                DB::table('niveaux')->where('id', $niveau->id)->update(['min_score' => $count === 1 ? 0 : (int) round($i * 75 / ($count - 1))]);
            }
        }
    }

    public function down(): void
    {
        // Les questions restent : elles ont pu être modifiées.
    }

    /** @return list<array{0: string, 1: int, 2: string, 3: list<string>, 4: int}> */
    private static function questions(): array
    {
        return [
            ['grammaire', 1, 'She ___ to school every day.', ['go', 'goes', 'going', 'gone'], 1],
            ['grammaire', 1, 'I ___ a student.', ['am', 'is', 'are', 'be'], 0],
            ['grammaire', 1, 'They ___ football on Sundays.', ['plays', 'playing', 'play', 'to play'], 2],
            ['grammaire', 1, 'There ___ two books on the table.', ['is', 'are', 'be', 'am'], 1],
            ['grammaire', 1, '___ you speak English?', ['Do', 'Does', 'Are', 'Is'], 0],
            ['grammaire', 2, 'Yesterday, we ___ to the market.', ['go', 'goes', 'went', 'gone'], 2],
            ['grammaire', 2, 'He has lived in Ségou ___ 2015.', ['for', 'since', 'during', 'ago'], 1],
            ['grammaire', 2, 'If it rains, we ___ at home.', ['stay', 'will stay', 'stayed', 'would stay'], 1],
            ['grammaire', 2, 'This book is ___ than that one.', ['interesting', 'more interesting', 'most interesting', 'interestinger'], 1],
            ['grammaire', 2, 'I was reading when the phone ___.', ['rings', 'rang', 'was ringing', 'has rung'], 1],
            ['grammaire', 3, 'If I ___ rich, I would travel around the world.', ['am', 'was', 'were', 'will be'], 2],
            ['grammaire', 3, 'The letter ___ yesterday.', ['was sent', 'sent', 'has sent', 'is sending'], 0],
            ['grammaire', 3, 'By next year, she ___ her degree.', ['finishes', 'will have finished', 'has finished', 'finished'], 1],
            ['grammaire', 3, 'He suggested ___ a taxi.', ['to take', 'taking', 'take', 'took'], 1],
            ['vocabulaire', 1, 'The opposite of "big" is…', ['small', 'tall', 'long', 'fast'], 0],
            ['vocabulaire', 1, 'Which one is a fruit?', ['carrot', 'mango', 'onion', 'rice'], 1],
            ['vocabulaire', 1, 'We sleep in the…', ['kitchen', 'bedroom', 'garden', 'office'], 1],
            ['vocabulaire', 2, '"To look for" means…', ['to watch', 'to search', 'to find', 'to see'], 1],
            ['vocabulaire', 2, 'A person who teaches is a…', ['teacher', 'doctor', 'driver', 'farmer'], 0],
            ['vocabulaire', 2, 'I am very ___ : I want to drink water.', ['hungry', 'thirsty', 'tired', 'angry'], 1],
            ['vocabulaire', 3, '"Reliable" is closest in meaning to…', ['dependable', 'expensive', 'careless', 'noisy'], 0],
            ['vocabulaire', 3, 'Choose the correct word: "He ___ me to come early."', ['said', 'told', 'spoke', 'talked'], 1],
            ['comprehension', 1, '"Awa gets up at 6. She has breakfast and walks to school." — How does Awa go to school?', ['By bus', 'On foot', 'By car', 'By bike'], 1],
            ['comprehension', 2, '"The shop opens at 8 a.m. and closes at 6 p.m., except on Sundays." — When is the shop closed?', ['Every evening at 8', 'On Sundays', 'On Mondays', 'In the morning'], 1],
            ['comprehension', 2, '"Moussa wanted to buy a phone, but it was too expensive, so he decided to save money." — Why didn\'t Moussa buy the phone?', ['It was broken', 'It was too expensive', 'He did not like it', 'The shop was closed'], 1],
            ['comprehension', 3, '"Although the training was difficult, most participants said it had improved their confidence." — What did participants think?', ['It was useless', 'It helped their confidence', 'It was too easy', 'It was too short'], 1],
            ['speaking', 1, 'Introduce yourself: your name, where you live, your job or studies, and why you want to learn English. (about 1 minute)', [], 0],
            ['speaking', 2, 'Describe your typical day, from the morning to the evening. (about 1 minute)', [], 0],
            ['speaking', 3, 'Talk about a place you would like to visit and explain why. (about 1 minute)', [], 0],
        ];
    }
};
