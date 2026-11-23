@extends('layouts.site')

{{-- Questionnaire du test de niveau (test à distance) : télécharger, remplir, renvoyer ses réponses (photo, PDF, Word). --}}
@section('content')
    @include('site.partials.page-hero', ['title' => 'Votre questionnaire', 'crumb' => 'Questionnaire', 'intro' => 'Téléchargez le questionnaire, répondez-y, puis envoyez-nous vos réponses ici.'])
    <section class="ettc-section">
        <div class="container" style="max-width: 680px">
            @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            <div class="detail-panel mb-4">
                <p class="mb-1">Test de niveau {{ $test->number }} — <strong>{{ $test->name }}</strong></p>
                @if ($test->scheduled_at)<p class="small text-muted mb-3">Prévu le {{ $test->scheduled_at->translatedFormat('l j F Y à H\hi') }}</p>@endif
                <a href="{{ route('site.tests.paper.download', ['test' => $test->id, 'key' => $key]) }}" class="ettc-btn ettc-btn-blue"><i class='bx bx-download' aria-hidden="true"></i> 1. Télécharger le questionnaire</a>
            </div>
            <form method="POST" action="{{ route('site.tests.paper.answers', ['test' => $test->id, 'key' => $key]) }}" enctype="multipart/form-data" class="detail-panel">
                @csrf
                <h2 class="h5">2. Envoyer mes réponses</h2>
                <p class="small text-muted">Une photo nette de vos feuilles, un PDF ou le document Word rempli (10 Mo au plus).</p>
                <input type="file" class="form-control @error('answers') is-invalid @enderror" name="answers" accept=".pdf,.doc,.docx,.odt,image/*" required aria-label="Fichier de mes réponses">
                @error('answers')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @if ($test->answers_at)<p class="small text-success mt-2 mb-0"><i class='bx bx-check' aria-hidden="true"></i> Réponses déjà reçues le {{ $test->answers_at->format('d/m/Y à H:i') }} ; un nouvel envoi les remplace.</p>@endif
                <button type="submit" class="btn btn-accent mt-3"><i class='bx bx-send' aria-hidden="true"></i> Envoyer mes réponses</button>
            </form>
        </div>
    </section>
@endsection
