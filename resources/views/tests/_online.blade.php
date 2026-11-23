{{-- Test passé EN LIGNE : score, réponses aux QCM (juste / faux), enregistrements de l'oral à écouter. $test,
     $recordingUrl (closure : id du sujet → adresse de l'enregistrement). --}}
@php
    $questions = app(\App\Services\Tests\OnlineTestService::class)->questions($test);
    $given = $test->online_answers ?? [];
@endphp
<div class="card border-info">
    <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-laptop me-2'></i>TEST EN LIGNE — {{ $test->score ?? 'en cours' }}</h6></div>
    <div class="card-body">
        @foreach ($questions->filter->isSpeaking() as $question)
            <div class="mb-3 p-2 rounded bg-light">
                <div class="small fw-semibold"><i class='bx bx-microphone'></i> Oral : {{ $question->question }}</div>
                @if (isset($given[$question->id]['recording']))
                    <audio controls preload="none" class="w-100 mt-1" src="{{ $recordingUrl($question->id) }}"></audio>
                @else
                    <div class="small text-muted">Pas d'enregistrement.</div>
                @endif
            </div>
        @endforeach
        <details>
            <summary class="small text-primary" style="cursor:pointer">Réponses aux questions ({{ $questions->reject->isSpeaking()->count() }})</summary>
            <ol class="small mt-2 mb-0">
                @foreach ($questions->reject->isSpeaking() as $question)
                    @php $answer = $given[$question->id] ?? null; @endphp
                    <li class="mb-1">
                        {{ $question->question }}<br>
                        <span class="{{ ($answer['correct'] ?? false) ? 'text-success' : 'text-danger' }}">
                            <i class='bx {{ ($answer['correct'] ?? false) ? 'bx-check' : 'bx-x' }}'></i>
                            {{ isset($answer['choice']) && $answer['choice'] !== null ? ($question->choices[$answer['choice']] ?? '—') : 'sans réponse' }}
                        </span>
                        @unless ($answer['correct'] ?? false)<span class="text-muted"> · bonne réponse : {{ $question->choices[$question->answer] ?? '—' }}</span>@endunless
                    </li>
                @endforeach
            </ol>
        </details>
    </div>
</div>
