@extends('layouts.site')

{{-- Calendrier de l'apprenant (cahier §4, §3.6) : prochaines séances (bouton « Rejoindre » actif 15 min avant le début),
     informations pratiques de ses groupes, supports pédagogiques. --}}
@section('content')
    @component('client._layout', ['title' => 'Mon calendrier'])
        @if ($annonces->isNotEmpty())
            <div class="detail-panel mb-4">
                <h2 class="h5 mb-3"><i class='bx bx-megaphone' aria-hidden="true"></i> Annonces du centre</h2>
                @foreach ($annonces as $annonce)
                    <div class="py-2 border-bottom">
                        <strong>{{ $annonce->title }}</strong> <small class="text-muted">· {{ $annonce->created_at->format('d/m/Y') }}@if ($annonce->groupe) · {{ $annonce->groupe->name }}@endif</small>
                        <div>{!! nl2br(e($annonce->body)) !!}</div>
                    </div>
                @endforeach
            </div>
        @endif
        @if ($groupes->isEmpty())
            <div class="detail-panel">
                <p class="mb-0 text-muted">Votre calendrier apparaîtra ici dès que le centre vous aura placé dans un groupe (après votre inscription et votre paiement).</p>
            </div>
        @else
            @php $next = $upcoming->firstWhere('is_cancelled', false); @endphp
            @if ($next)
                <div class="detail-panel mb-4 border-start border-4 border-warning">
                    <p class="small text-muted mb-1">Prochaine séance</p>
                    <h2 class="h4 mb-1">{{ ucfirst($next->when()) }}</h2>
                    <p class="mb-3">{{ $next->groupe->name }}@if ($next->title) — {{ $next->title }}@endif @if ($next->groupe->teacherName())· avec {{ $next->groupe->teacherName() }}@endif</p>
                    @if ($next->joinUrl())
                        @if ($next->isJoinable())
                            <a href="{{ $next->joinUrl() }}" class="btn btn-accent btn-lg" target="_blank" rel="noopener"><i class='bx bx-video' aria-hidden="true"></i> Rejoindre la séance</a>
                        @else
                            <span class="btn btn-outline-secondary btn-lg disabled" aria-disabled="true"><i class='bx bx-time-five' aria-hidden="true"></i> Le bouton « Rejoindre » s'active 15 minutes avant</span>
                        @endif
                    @elseif ($next->groupe->location)
                        <p class="mb-0"><i class='bx bx-map' aria-hidden="true"></i> Au centre — {{ $next->groupe->location }}</p>
                    @endif
                    @if ($next->notes)<p class="small text-muted mt-2 mb-0">{{ $next->notes }}</p>@endif
                </div>
            @endif

            <div class="detail-panel mb-4">
                <h2 class="h5 mb-3">Séances à venir</h2>
                @forelse ($upcoming as $seance)
                    <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-bottom">
                        <span class="flex-grow-1 {{ $seance->is_cancelled ? 'text-decoration-line-through text-muted' : '' }}"><strong>{{ ucfirst($seance->when()) }}</strong><br><small class="text-muted">{{ $seance->groupe->name }}@if ($seance->title) — {{ $seance->title }}@endif</small></span>
                        @if ($seance->is_cancelled)
                            <span class="tag tag-muted">Annulée</span>
                        @elseif ($seance->isJoinable())
                            <a href="{{ $seance->joinUrl() }}" class="btn btn-accent btn-sm" target="_blank" rel="noopener">Rejoindre</a>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">Aucune séance programmée pour l'instant.</p>
                @endforelse
            </div>

            <div class="detail-panel mb-4">
                <h2 class="h5 mb-3">Supports de cours</h2>
                @forelse ($supports as $support)
                    <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-bottom">
                        <i class='bx {{ $support->icon() }} fs-4' aria-hidden="true"></i>
                        <span class="flex-grow-1">{{ $support->title }}<br><small class="text-muted">{{ $support->kindLabel() }}@if ($support->sizeLabel()) · {{ $support->sizeLabel() }}@endif</small></span>
                        @if ($support->url)
                            <a href="{{ $support->url }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">Ouvrir</a>
                        @else
                            <a href="{{ route('client.calendrier.support', $support) }}" class="btn btn-sm btn-outline-primary">Télécharger</a>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mb-0">Aucun support partagé pour l'instant.</p>
                @endforelse
            </div>

            @foreach ($groupes->filter(fn ($g) => $g->notes || $g->schedule) as $groupe)
                <div class="detail-panel mb-4">
                    <h2 class="h6">{{ $groupe->name }} — informations pratiques <a href="{{ route('client.groupes.show', $groupe) }}" class="small fw-normal ms-2">Discussion du groupe</a></h2>
                    @if ($groupe->schedule)<p class="mb-1"><i class='bx bx-time' aria-hidden="true"></i> {{ $groupe->schedule }}</p>@endif
                    @if ($groupe->notes)<p class="mb-0">{!! nl2br(e($groupe->notes)) !!}</p>@endif
                </div>
            @endforeach
        @endif
    @endcomponent
@endsection
