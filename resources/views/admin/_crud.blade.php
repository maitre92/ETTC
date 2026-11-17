{{-- Écran d'administration d'un contenu simple au gabarit du thème (CLAUDE.md §7), dérivé de referentiels/_simple du
     gabarit Gestion_magasin : liste DataTables avec pastilles de statut, création et modification en modale, suppression
     confirmée. Paramètres :
       title, section (fil d'Ariane), crumbs (facultatif : [[libellé, url]] avant le titre), tabs (facultatif : vue
       d'onglets incluse sous le fil d'Ariane), storeUrl et createAbility (facultatifs, si la création dépend d'un
       paramètre), heading, icon, intro (facultatif), items, model (classe), routePrefix, idField
       (champ caché qui distingue les modales en cas d'erreur), deleteTitle, newLabel, labelOf (closure : libellé d'un
       élément), modalSize ('' ou 'modal-lg'), statusField (booléen de statut), statusLabels ([vrai, faux]),
       fields : name, label, type (text|textarea|markdown|number|select|icon|switch), required, max, placeholder,
                options (select/icon : valeur => libellé), help, col (classe de colonne, défaut col-12), default
       columns : label, value (closure qui renvoie du HTML déjà échappé). --}}
@php
    $statusField = $statusField ?? 'is_published';
    $statusLabels = $statusLabels ?? ['Publié', 'Masqué'];
    $modalSize = $modalSize ?? 'modal-lg';
    $onCount = $items->where($statusField, true)->count();
    // Identifiant HTML sûr (un préfixe de route « formations.formules » contient un point, lu comme une classe CSS).
    $domId = str_replace('.', '-', $routePrefix);
@endphp
<div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
    <div class="breadcrumb-title pe-3">{{ $section ?? 'Contenus du site' }}</div>
    <div class="ps-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 p-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                @foreach ($crumbs ?? [] as [$crumbLabel, $crumbUrl])
                    <li class="breadcrumb-item"><a href="{{ $crumbUrl }}">{{ $crumbLabel }}</a></li>
                @endforeach
                <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
            </ol>
        </nav>
    </div>
</div>
<hr />
@if (! empty($tabs))
    @include($tabs)
@endif

{{-- Zone actualisée en AJAX après chaque enregistrement ou suppression (public/assets/js/crud-ajax.js) : liste et
     modales. Le message de confirmation est relu dans data-crud-status. --}}
<div data-crud="{{ $domId }}" data-crud-columns="{{ count($columns) }}">
<span data-crud-status hidden>{{ session('status') }}</span>
<div class="card">
    <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h6 class="mb-0 text-white"><i class='{{ $icon }} me-2'></i>{{ $heading }}</h6>
        @can('create', $createAbility ?? $model)
            <div class="d-flex flex-wrap gap-2">
                @if (Route::has($routePrefix.'.bulk'))
                    <a href="{{ route($routePrefix.'.bulk') }}" class="btn btn-light btn-sm d-flex align-items-center gap-1"><i class='bx bx-table'></i> Saisie en lot / import</a>
                @endif
                <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#create-{{ $domId }}">
                    <i class='bx bx-plus'></i> Ajouter
                </button>
            </div>
        @endcan
    </div>
    <div class="card-body">
        @if (! empty($intro))
            <p class="text-muted small">{{ $intro }}</p>
        @endif
        <div class="nv-chips mb-3" id="{{ $domId }}-filter">
            <button type="button" class="nv-chip active" data-token="">Tous <b>{{ $items->count() }}</b></button>
            <button type="button" class="nv-chip" data-token="statut-on">{{ $statusLabels[0] }}s <b>{{ $onCount }}</b></button>
            <button type="button" class="nv-chip" data-token="statut-off">{{ $statusLabels[1] }}s <b>{{ $items->count() - $onCount }}</b></button>
        </div>
        <div class="table-responsive">
            <table class="table" id="{{ $domId }}-table">
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th>{{ $column['label'] }}</th>
                        @endforeach
                        <th>STATUT</th>
                        <th width="10%">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            @foreach ($columns as $column)
                                <td class="cell-wrap text-break">{!! $column['value']($item) !!}</td>
                            @endforeach
                            <td>
                                <span class="d-none">{{ $item->{$statusField} ? 'statut-on' : 'statut-off' }}</span>
                                @if ($item->{$statusField})
                                    <span class="badge bg-success-subtle">{{ $statusLabels[0] }}</span>
                                @else
                                    <span class="badge bg-secondary-subtle">{{ $statusLabels[1] }}</span>
                                @endif
                            </td>
                            <td class="d-flex gap-1">
                                @can('update', $item)
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#edit-{{ $domId }}-{{ $item->id }}" title="Modifier">
                                        <i class='bx bx-edit'></i>
                                    </button>
                                @endcan
                                @can('delete', $item)
                                    <form method="POST" action="{{ route($routePrefix.'.destroy', $item) }}" class="js-confirm-delete" data-crud-form data-no-guard data-title="{{ $deleteTitle }}" data-name="{{ $labelOf($item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-primary btn-sm" title="Supprimer">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@php
    // Une modale par formulaire : null = création. Les erreurs rouvrent la bonne modale (champ caché $idField).
    $forms = collect([null])->merge($items->filter(fn ($item) => auth()->user()->can('update', $item)));
@endphp
@foreach ($forms as $item)
    @continue($item === null && ! auth()->user()->can('create', $createAbility ?? $model))
    @php
        $modalId = $item ? "edit-{$domId}-{$item->id}" : "create-{$domId}";
        $failed = $errors->any() && ($item ? old($idField) == $item->id : ! old($idField));
        $sfx = $item ? '-'.$item->id : '';
    @endphp
    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable {{ $modalSize }}">
            <div class="modal-content">
                <form method="POST" action="{{ $item ? route($routePrefix.'.update', $item) : ($storeUrl ?? route($routePrefix.'.store')) }}" data-crud-form data-no-guard>
                    @csrf
                    @if ($item)
                        @method('PUT')
                        <input type="hidden" name="{{ $idField }}" value="{{ $item->id }}">
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title"><i class='{{ $icon }} me-2'></i>{{ $item ? 'Modifier — '.$labelOf($item) : $newLabel }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            @foreach ($fields as $field)
                                @php
                                    $name = $field['name'];
                                    $type = $field['type'] ?? 'text';
                                    $id = $name.$sfx;
                                    $value = $failed ? old($name) : ($item ? $item->{$name} : ($field['default'] ?? null));
                                    $value = $value instanceof \BackedEnum ? $value->value : $value;
                                    $invalid = $failed && $errors->has($name);
                                @endphp
                                <div class="{{ $field['col'] ?? 'col-12' }}">
                                    @if ($type === 'switch')
                                        <div class="form-check form-switch mt-md-4">
                                            <input class="form-check-input" type="checkbox" role="switch" id="{{ $id }}" name="{{ $name }}" value="1" @checked($value)>
                                            <label class="form-check-label" for="{{ $id }}">{{ $field['label'] }}</label>
                                        </div>
                                    @else
                                        <label for="{{ $id }}" class="form-label fw-semibold">{{ $field['label'] }}@if ($field['required'] ?? false) <span class="text-danger">*</span>@endif</label>
                                        @if (in_array($type, ['textarea', 'markdown'], true))
                                            <textarea class="form-control @if ($type === 'markdown') font-monospace small @endif @if ($invalid) is-invalid @endif" id="{{ $id }}" name="{{ $name }}"
                                                      rows="{{ $type === 'markdown' ? 8 : 3 }}" maxlength="{{ $field['max'] ?? 5000 }}" placeholder="{{ $field['placeholder'] ?? '' }}" @required($field['required'] ?? false)>{{ $value }}</textarea>
                                        @elseif (in_array($type, ['select', 'icon'], true))
                                            <select class="form-select {{ $type === 'icon' ? 'icon-select' : 'single-select' }}" id="{{ $id }}" name="{{ $name }}" @required($field['required'] ?? false)>
                                                @unless ($field['required'] ?? false)
                                                    <option value="">— Aucun —</option>
                                                @endunless
                                                @foreach ($field['options'] as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="{{ $type === 'number' ? 'number' : 'text' }}" class="form-control @if ($invalid) is-invalid @endif" id="{{ $id }}" name="{{ $name }}"
                                                   value="{{ $value }}" @if ($type === 'number') min="0" step="1" @else maxlength="{{ $field['max'] ?? 255 }}" @endif placeholder="{{ $field['placeholder'] ?? '' }}" @required($field['required'] ?? false)>
                                        @endif
                                        @if ($invalid)<div class="{{ in_array($type, ['select', 'icon'], true) ? 'text-danger small mt-1' : 'invalid-feedback' }}">{{ $errors->first($name) }}</div>@endif
                                        @if (! empty($field['help']) || $type === 'markdown')
                                            <div class="form-text">{{ $field['help'] ?? '' }} @if ($type === 'markdown') Markdown : <code>## Titre</code>, <code>**gras**</code>, <code>- liste</code>.@endif</div>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @if ($failed)
        {{-- Sans JavaScript AJAX (envoi classique) : la modale en erreur se rouvre au chargement. --}}
        <span data-crud-reopen="{{ $modalId }}" hidden></span>
    @endif
@endforeach
</div>

@push('scripts')
    {{-- Tableau, filtres, icônes et envois AJAX : public/assets/js/crud-ajax.js (CrudAjax.init). --}}
    <script src="{{ asset('assets/js/crud-ajax.js') }}?v={{ filemtime(public_path('assets/js/crud-ajax.js')) }}"></script>
    <script>CrudAjax.init(document.querySelector('[data-crud="{{ $domId }}"]'));</script>
    @include('partials.confirm-delete')
@endpush
