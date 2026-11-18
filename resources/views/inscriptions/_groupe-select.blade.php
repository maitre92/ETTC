{{-- Liste des groupes actifs pour placer un apprenant ($inscription, $groupes, $selectId). Les groupes du même mode
     et du même niveau sont proposés en premier. --}}
@php
    $sorted = $groupes->sortBy(fn ($g) => [$g->mode !== $inscription->mode, $g->niveau_id && $g->niveau_id !== $inscription->niveau_id, $g->name]);
@endphp
<select class="form-select" id="{{ $selectId }}" name="groupe_id">
    <option value="">— Aucun groupe —</option>
    @foreach ($sorted as $choice)
        <option value="{{ $choice->id }}" @selected($inscription->groupe_id === $choice->id)>{{ $choice->name }} · {{ mb_strtolower($choice->mode->label()) }}{{ $choice->niveau ? ' · '.$choice->niveau->name : '' }}</option>
    @endforeach
</select>
