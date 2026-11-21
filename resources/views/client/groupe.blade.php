@extends('layouts.site')

{{-- Page d'un groupe pour l'apprenant : formateur, prochaines séances (Rejoindre), discussion (il écrit à son formateur et
     à son groupe), supports. --}}
@section('content')
    @component('client._layout', ['title' => 'Groupe '.$groupe->name])
        @php $next = $upcoming->firstWhere('is_cancelled', false); @endphp
        <div class="detail-panel mb-4 border-start border-4 border-warning">
            <p class="mb-1"><i class='bx bx-chalkboard' aria-hidden="true"></i> Formateur : <strong>{{ $groupe->teacherName() ?? 'communiqué bientôt' }}</strong> · {{ $groupe->mode->label() }} · {{ $groupe->niveau?->name ?? 'Tous niveaux' }}</p>
            @if ($next)
                <p class="mb-2">Prochaine séance : <strong>{{ ucfirst($next->when()) }}</strong>@if ($next->title) — {{ $next->title }}@endif</p>
                @if ($next->joinUrl())
                    @if ($next->isJoinable())
                        <a href="{{ $next->joinUrl() }}" class="btn btn-accent" target="_blank" rel="noopener"><i class='bx bx-video' aria-hidden="true"></i> Rejoindre la séance</a>
                    @else
                        <span class="small text-muted"><i class='bx bx-time-five' aria-hidden="true"></i> Le bouton « Rejoindre » apparaît 15 minutes avant (Mon calendrier).</span>
                    @endif
                @elseif ($groupe->location)
                    <span><i class='bx bx-map' aria-hidden="true"></i> Au centre — {{ $groupe->location }}</span>
                @endif
            @else
                <p class="mb-0 text-muted">Aucune séance programmée pour l'instant.</p>
            @endif
        </div>

        <div class="detail-panel mb-4" id="discussion">
            <h2 class="h5 mb-3"><i class='bx bx-conversation' aria-hidden="true"></i> Discussion du groupe</h2>
            <div class="client-thread mb-3">
                @forelse ($groupe->messages as $msg)
                    @php $mine = $msg->client_id === auth('client')->id(); @endphp
                    <div class="client-thread-item {{ $msg->fromTeam() ? 'is-team' : '' }} {{ $mine ? 'is-mine' : '' }}">
                        <small class="text-muted"><strong class="text-body">{{ $mine ? 'Vous' : $msg->authorName() }}</strong>@if ($msg->fromTeam()) · {{ $msg->user_id === $groupe->formateur_id ? 'Formateur' : 'Centre' }}@endif · {{ $msg->created_at->format('d/m/Y H:i') }}</small>
                        <div>{!! nl2br(e($msg->body)) !!}</div>
                    </div>
                @empty
                    <p class="text-muted mb-0">Pas encore de message. Posez votre question à votre formateur : il la verra dans son espace.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('client.groupes.messages.store', $groupe) }}">
                @csrf
                <label for="g-body" class="form-label">Votre message <small class="text-muted">(lu par votre formateur et votre groupe)</small></label>
                <textarea class="form-control @error('body') is-invalid @enderror" id="g-body" name="body" rows="3" maxlength="2000" required aria-describedby="g-body-error">{{ old('body') }}</textarea>
                @error('body')<div class="invalid-feedback" id="g-body-error">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-accent mt-2"><i class='bx bx-send' aria-hidden="true"></i> Envoyer</button>
            </form>
        </div>

        <div class="detail-panel mb-4">
            <h2 class="h5 mb-3">Supports de cours</h2>
            @forelse ($groupe->supports as $support)
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
    @endcomponent
@endsection
