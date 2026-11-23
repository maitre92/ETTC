@extends('layouts.site')

{{-- Résultat du test en ligne : score, niveau provisoire, oral à écouter par un formateur, « M'inscrire ». --}}
@section('content')
    @include('site.partials.page-hero', ['title' => 'Votre résultat', 'crumb' => 'Résultat du test', 'intro' => 'Merci d\'avoir passé le test de niveau en ligne.'])
    <section class="ettc-section">
        <div class="container" style="max-width: 680px">
            <div class="form-success text-center">
                <span class="form-success-icon"><i class='bx bx-check' aria-hidden="true"></i></span>
                <p class="mb-1">Votre score</p>
                <p class="display-6 fw-bold text-ink mb-2">{{ $test->score }}</p>
                <p class="h4 mb-3">Niveau conseillé : <strong>{{ $test->niveau?->name ?? 'à déterminer' }}</strong></p>
                @if ($hasOral)
                    <p class="mb-3"><i class='bx bx-microphone' aria-hidden="true"></i> Votre expression orale va être écoutée par un formateur : il confirmera votre niveau{{ $test->email ? ' (vous serez prévenu par e-mail)' : '' }}.</p>
                @endif
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    @if ($test->niveau)
                        <a href="{{ route('site.inscription.create', ['niveau' => $test->niveau->slug]) }}" class="ettc-btn ettc-btn-orange"><i class='bx bx-user-plus' aria-hidden="true"></i> M'inscrire au niveau {{ $test->niveau->name }}</a>
                    @endif
                    @if ($whatsapp = $site->whatsappLink('Bonjour, j\'ai passé le test de niveau en ligne ('.$test->number.') : '.$test->score.', niveau '.($test->niveau?->name ?? '—').'.'))
                        <a href="{{ $whatsapp }}" class="btn btn-whatsapp" target="_blank" rel="noopener"><i class='bx bxl-whatsapp' aria-hidden="true"></i> En parler sur WhatsApp</a>
                    @endif
                </div>
                <p class="small text-muted mt-3 mb-0">Référence du test : {{ $test->number }}</p>
            </div>
        </div>
    </section>
@endsection
