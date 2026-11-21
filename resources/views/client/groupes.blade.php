@extends('layouts.site')

{{-- Mes groupes (plusieurs) : un lien par groupe vers sa page (formateur, séances, supports, discussion). --}}
@section('content')
    @component('client._layout', ['title' => 'Mes groupes'])
        @forelse ($groupes as $groupe)
            <a href="{{ route('client.groupes.show', $groupe) }}" class="detail-panel mb-3 d-flex flex-wrap align-items-center gap-3 text-reset text-decoration-none">
                <i class='bx bx-group fs-1 text-primary' aria-hidden="true"></i>
                <span class="flex-grow-1">
                    <strong class="d-block">{{ $groupe->name }}</strong>
                    <small class="text-muted">{{ $groupe->mode->label() }} · {{ $groupe->niveau?->name ?? 'Tous niveaux' }}@if ($groupe->teacherName()) · avec {{ $groupe->teacherName() }}@endif</small>
                </span>
                @if ($unread[$groupe->id] ?? 0)<span class="tag tag-info">{{ $unread[$groupe->id] }} nouveau(x) message(s)</span>@endif
                <i class='bx bx-chevron-right fs-3' aria-hidden="true"></i>
            </a>
        @empty
            <div class="detail-panel">
                <p class="mb-0 text-muted">Vous serez placé dans un groupe après votre inscription et votre paiement. Vous y retrouverez votre formateur, vos séances, vos supports et la discussion avec votre groupe.</p>
            </div>
        @endforelse
    @endcomponent
@endsection
