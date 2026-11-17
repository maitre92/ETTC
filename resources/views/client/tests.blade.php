@extends('layouts.site')

{{-- Tests de niveau de l'apprenant (cahier §4) : statut, date et lieu ou lien (test gratuit), niveau attribué
     avec la fiche du niveau (formules et tarifs) pour choisir sa formation. --}}
@section('content')
    @component('client._layout', ['title' => 'Mes tests de niveau'])
        <div class="detail-panel">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h5 mb-0">Mes tests</h2>
                <a href="{{ route('site.tests.show') }}#reserver" class="btn btn-accent btn-sm">Réserver un test</a>
            </div>
            @if ($tests->isEmpty())
                <p class="text-muted mb-0">Aucun test de niveau pour l'instant. Le test est la première étape avant votre formation.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th scope="col">Référence</th><th scope="col">Test</th><th scope="col">Résultat</th></tr></thead>
                        <tbody>
                            @foreach ($tests as $test)
                                <tr>
                                    <td class="fw-semibold">{{ $test->number }}<br><span class="tag {{ $test->status->isOpen() ? 'tag-info' : ($test->status === \App\Enums\TestStatus::Passe ? 'tag-success' : 'tag-muted') }}">{{ $test->status->label() }}</span></td>
                                    <td>
                                        @if ($test->scheduled_at && $test->status !== \App\Enums\TestStatus::Annule)
                                            {{ $test->scheduled_at->translatedFormat('l j F Y \à H\hi') }}<br><small class="text-muted">{{ $test->test_mode->label() }}@if ($test->location) · {{ $test->location }}@endif</small>
                                        @else
                                            <span class="text-muted">{{ $test->status === \App\Enums\TestStatus::Annule ? 'Annulé' : 'Date à fixer avec le centre' }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($test->niveau)
                                            <strong>{{ $test->niveau->name }}</strong>
                                            @if ($test->niveau->is_published)<br><a href="{{ route('site.formations.show', $test->niveau->slug) }}">Voir ma formation et les tarifs</a>@endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $tests->links() }}</div>
            @endif
        </div>
    @endcomponent
@endsection
