{{-- Moyen de paiement au centre, choix groupé (Espèces / Mobile money / Banque). $id, $name, $selected, $class (facultatif). --}}
<select class="{{ $class ?? 'form-select form-select-lg' }}" id="{{ $id }}" name="{{ $name }}" required>
    @foreach (\App\Enums\PaymentMethod::grouped() as $group => $options)
        <optgroup label="{{ $group }}">
            @foreach ($options as $value => $label)
                <option value="{{ $value }}" @selected($selected === $value)>{{ $label }}</option>
            @endforeach
        </optgroup>
    @endforeach
</select>
