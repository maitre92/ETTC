@extends('layouts.site')

{{-- Test de niveau en ligne : une question à la fois (test-en-ligne.js : progression, compte à rebours, enregistrement au
     micro) ; sans JavaScript, toutes les questions s'affichent et l'oral se dépose en fichier. Les bonnes réponses ne
     sont jamais dans la page. $test, $questions, $deadline. --}}
@section('content')
    @include('site.partials.page-hero', ['title' => 'Test de niveau en ligne', 'crumb' => 'Test en ligne', 'intro' => 'Répondez à chaque question. Le temps restant s\'affiche en haut ; à la fin, cliquez sur « Envoyer mes réponses ».'])
    <section class="ettc-section">
        <div class="container" style="max-width: 820px">
            <form method="POST" action="{{ route('site.tests.online.submit', $test->online_token) }}" enctype="multipart/form-data" class="online-test" data-online-test data-deadline="{{ $deadline->toIso8601String() }}">
                @csrf
                <div class="online-test-bar">
                    <span><i class='bx bx-user' aria-hidden="true"></i> {{ $test->name }}</span>
                    <span data-progress>Question 1 / {{ $questions->count() }}</span>
                    <span class="online-test-timer" data-timer><i class='bx bx-time-five' aria-hidden="true"></i> {{ $deadline->diffInMinutes(now(), true) }} min</span>
                </div>
                @foreach ($questions as $question)
                    <fieldset class="online-q" data-q>
                        <legend class="small text-muted mb-1">Question {{ $loop->iteration }} · {{ \App\Models\TestQuestion::SECTIONS[$question->section] ?? '' }}</legend>
                        <p class="online-q-text">{!! nl2br(e($question->question)) !!}</p>
                        @if ($question->isSpeaking())
                            <div class="online-rec" data-recorder>
                                <p class="small mb-2">Enregistrez votre réponse au micro (1 à 2 minutes). Vous pouvez réécouter et recommencer.</p>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-danger" data-rec-start hidden><i class='bx bx-microphone' aria-hidden="true"></i> Enregistrer</button>
                                    <button type="button" class="btn btn-dark" data-rec-stop hidden><i class='bx bx-stop' aria-hidden="true"></i> Arrêter</button>
                                    <span class="small" data-rec-status aria-live="polite"></span>
                                </div>
                                <audio controls data-rec-audio hidden class="mt-2 w-100"></audio>
                                <label class="form-label small mt-2" for="sp-{{ $question->id }}" data-rec-fallback>Ou déposez un enregistrement (téléphone) :</label>
                                <input type="file" class="form-control" id="sp-{{ $question->id }}" name="speaking[{{ $question->id }}]" accept="audio/*" capture data-rec-input>
                            </div>
                        @else
                            @foreach ($question->choices as $index => $choice)
                                <label class="online-choice">
                                    <input type="radio" name="answers[{{ $question->id }}]" value="{{ $index }}">
                                    <span>{{ $choice }}</span>
                                </label>
                            @endforeach
                        @endif
                    </fieldset>
                @endforeach
                <div class="online-test-nav">
                    <button type="button" class="ettc-btn ettc-btn-light" data-prev hidden><i class='bx bx-left-arrow-alt' aria-hidden="true"></i> Précédente</button>
                    <button type="button" class="ettc-btn ettc-btn-blue" data-next hidden>Suivante <i class='bx bx-right-arrow-alt' aria-hidden="true"></i></button>
                    <button type="submit" class="ettc-btn ettc-btn-orange" data-submit><i class='bx bx-send' aria-hidden="true"></i> Envoyer mes réponses</button>
                </div>
            </form>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('assets/site/js/test-en-ligne.js') }}?v={{ filemtime(public_path('assets/site/js/test-en-ligne.js')) }}"></script>
@endpush
