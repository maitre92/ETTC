@extends('layouts.admin')

@section('title', 'Permissions de l\'équipe')

{{-- Permissions de l'équipe (demande du centre, 2026-11-23) : à gauche les subalternes, à droite les droits que
     l'administrateur peut leur donner (parmi les siens), groupés par module ; ceux de leur rôle sont déjà acquis. --}}
@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Administration</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Permissions de l'équipe</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    @if ($team->isEmpty())
        <div class="card"><div class="card-body text-muted">Aucun compte dans votre équipe. Créez d'abord les comptes (Utilisateurs) : secrétaire, gestionnaire, formateurs.</div></div>
    @else
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-group me-2'></i>MON ÉQUIPE</h6></div>
                    <div class="list-group list-group-flush">
                        @foreach ($team as $member)
                            <a href="{{ route('equipe.permissions', ['user' => $member->id]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $selected?->id === $member->id ? 'active' : '' }}">
                                <span>{{ $member->name }}<span class="d-block small {{ $selected?->id === $member->id ? '' : 'text-muted' }}">{{ $member->roleName()?->label() }}{{ $member->is_active ? '' : ' · désactivé' }}</span></span>
                                @if ($member->permissions->count())<span class="badge bg-warning text-dark">+{{ $member->permissions->count() }}</span>@endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                @if ($selected)
                    <form method="POST" action="{{ route('equipe.permissions.update', $selected) }}" class="card">
                        @csrf @method('PUT')
                        <div class="card-header card-header-brand d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h6 class="mb-0 text-white"><i class='bx bx-key me-2'></i>{{ mb_strtoupper($selected->name) }} — {{ mb_strtoupper($selected->roleName()?->label() ?? '') }}</h6>
                            <button type="submit" class="btn btn-light btn-sm"><i class='bx bx-save me-1'></i>Enregistrer</button>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">Cochez les droits à <strong>ajouter</strong>. Les cases grisées sont déjà données par son rôle. Vous ne pouvez donner que des droits que vous avez vous-même ; chaque changement est inscrit dans le journal d'activité.</p>
                            @foreach ($grid as $module => $permissions)
                                <fieldset class="mb-3">
                                    <legend class="fs-6 fw-semibold text-primary mb-1">{{ $module }}</legend>
                                    @foreach ($permissions as $permission)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission['name'] }}" id="p-{{ $permission['name'] }}"
                                                   @checked($permission['inherited'] || $permission['granted']) @disabled($permission['inherited'])>
                                            <label class="form-check-label {{ $permission['inherited'] ? 'text-muted' : '' }}" for="p-{{ $permission['name'] }}">
                                                {{ $permission['label'] }}
                                                @if ($permission['inherited'])<span class="badge bg-light text-dark ms-1">par son rôle</span>@elseif ($permission['granted'])<span class="badge bg-warning-subtle ms-1">ajouté</span>@endif
                                            </label>
                                        </div>
                                    @endforeach
                                </fieldset>
                            @endforeach
                            <button type="submit" class="btn btn-primary"><i class='bx bx-save me-1'></i>Enregistrer</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
@endsection
